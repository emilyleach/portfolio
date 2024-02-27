<?php
// this service was used to contain all report related functions

use Doctrine\ORM\AbstractQuery;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use UC\Portal\Model\Report;


class ReportService
{

    private $entityManager;

    private $twig;


    function __construct($entityManager, $twig)
    {
        $this->entityManager = $entityManager;
        //HTML Purify to allow certain HTML tags only in our rich textareas
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', 'assets');
        $config->set('HTML.Allowed', 'p,h1,h2,h3,h4,h5,h6,strong,b,em,i,u,strike,ul,ol,li,br,hr,span,a[href|title],img[src|alt],table,tr,td,th,pre,code');
        $purifier = new HTMLPurifier($config);
        $this->twig = $twig;
    }

    public function getReportList(): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('report')
            ->from('UC\Portal\Model\Report', 'report');

        $query = $qb->getQuery();
        $result = $query->getResult();

        return $result;
    }
    public function getReport($reportId): Report
    {
        $report = $this->entityManager->find('UC\Portal\Model\Report', $reportId);

        if($report == null)
        {
            throw new ResourceNotFoundException("Entity Not Found: Report id={$reportId} ");
        }

        return $report;
    }

    public function deleteReport($postVar)
    {
        $entity = $this->getReport($postVar['deletedAttr']);
        $entity->isDeleted = "true";
        $this->entityManager->flush();

        return $entity;
    }

    public function updateReport($postVar): int
    {
        $reportId = $postVar["reportId"];
        $report = null;

        //See if we are updating or adding a report.
        if ($reportId == "") {
            $report = new Report();
            $this->entityManager->persist($report);
        } else {
            $report = $this->getReport($reportId);
        }

        $report->name = $postVar['prop_name'];
        $report->title = $postVar['prop_title'];
        if ($postVar['prop_entityTypeId'] != '') {
            $entityService = new \UC\Portal\Service\EntityService($this->entityManager, $this->twig);
            $report->entityType = $entityService->getEntityTypeById($postVar['prop_entityTypeId']);
        }
        $report->jsonConfig = $postVar['prop_json'];

        if (isset($postVar['prop_max']) && $postVar['prop_max'] != '') {
            $report->maxResults = $postVar['prop_max'];
        } else {
            $report->maxResults = 0;
        }


        $this->entityManager->flush();
        return $report->id;
    }

    private function mergeReturnArray($a,$b)
    {
        $returnArray = [];
        $returnArray['selects'] = array_merge_recursive($a['selects'],$b['selects']);
        $returnArray['joins'] = array_merge_recursive($a['joins'],$b['joins']);
        $returnArray['filters'] = array_merge_recursive($a['filters'],$b['filters']);
        $returnArray['sorts'] = array_merge_recursive($a['sorts'],$b['sorts']);
        $returnArray['subJoins'] = array_merge_recursive($a['subJoins'],$b['subJoins']);
        $returnArray['columns'] = array_merge_recursive($a['columns'],$b['columns']);

        return $returnArray;
    }

    private function generateColumnMap($columnConfig, $startTable = "t0"): array
    {
        $columns = [];
        // t0_

        foreach($columnConfig as $element)
        {
            $columns[$element->key] = $startTable;
            if(isset($element->values))
            {
                $nestedRows = $this->generateColumnMap($element->values,$startTable . ";" . $element->key);
                $columns = array_merge($columns,$nestedRows);
            }
        }

        return $columns;
    }

    public function sendReportToProcesses(Report $report, $params)
    {
        $configObject = json_decode($report->jsonConfig);

        $columns = $configObject->values;
        if (isset($configObject->filter))
        {
            $filters = $configObject->filter;
        }
        else
        {
            $filters = "";
        }
        if (isset($configObject->collectBy))
        {
            $collectBy = $configObject->collectBy;
        }
        else
        {
            $collectBy = "";
        }


        $columnMap = $this->generateColumnMap($columns);


        $startingModel = $report->entityType->class;
        $columnArrayResults = $this->processValues($columns,"t0", $filters, $params);

        if($collectBy != null && $collectBy != "")
        {
            $columnArrayResults['sorts'] = array_merge([ $collectBy => 'ASC'], $columnArrayResults['sorts']);
        }

        $queryResults = $this->getQueryResults($columnArrayResults, $startingModel);


        $chunkedData = [];
        $dataCounter = 0;
        $prevColumnValue = "";
        $chunkCount = 0;
        foreach ($queryResults as $resultKey => $result)
        {
            if (isset($result[$collectBy]))
            {
                if ($result[$collectBy] != $prevColumnValue && $dataCounter > 0)
                {
                    $chunkCount += 1;
                }
                $prevColumnValue = $result[$collectBy];
            }
            $dataCounter += 1;
            $chunkedData["table_".$chunkCount]['row_'.$dataCounter] = $result;
        }

        $lumpedData = [];
        foreach($chunkedData as $tableKey=>$table)
        {
            foreach ($table as $row)
            {
                $lumpedData[$tableKey][$row['t0__id']][] = $row;
            }
        }

        ini_set('xdebug.var_display_max_depth', 99);

        $fixedReportDisplay = $this->fixReportDisplay($lumpedData, $columnMap);


        $returnValue = new \stdClass();
        $returnValue->columns = $columnArrayResults['columns'];
        $returnValue->data = $fixedReportDisplay;
        $returnValue->columnMap = $columnMap;

        return $returnValue;
    }

    public function processValues($values,$table, $queryFilters = "", $params = "")
    {
        $returnValue = [];
        $returnValue['selects'] = [];
        $returnValue['joins'] = [];
        $returnValue['filters'] = [];
        $returnValue['sorts'] = [];
        $returnValue['subJoins'] = [];
        $returnValue['columns'] = [];



        foreach ($values as $value)
        {
            if ($queryFilters != "")
            {
                foreach ($queryFilters as $queryFilter)
                {

                    if (isset($queryFilter->value) && $queryFilter->value == $value->key)
                    {

                        if (isset($queryFilter->exposeOnUrl) && $queryFilter->exposeOnUrl && isset($params[$queryFilter->key]))
                        {
                            $filterValue = $params[$queryFilter->key];
                        }
                        else
                        {
                            $filterValue = $queryFilter->constant;
                        }

                        if (isset($value->property))
                        {
                            $returnValue['filters'][$value->key] = "{$table}.{$value->property} {$queryFilter->comparison} '{$filterValue}'";
                        }
                        else
                        {
                            $returnValue['filters'][$value->key] = "{$value->key}_av.value {$queryFilter->comparison} '{$filterValue}'";
                        }
                    }
                }
            }
            if(isset($value->entity))
            {
                $returnValue['joins'][$value->key] = "{$table}.{$value->entity}";
                $returnValue['selects'][$value->key] = "{$value->key}.id as {$value->key}__id";
                if(isset($value->values))
                {

                    $newReturnArray = $this->processValues($value->values,$value->key, $queryFilters, $params);
                    $returnValue = $this->mergeReturnArray($returnValue,$newReturnArray);
                }
            }
            if(isset($value->filter))
            {
                foreach ($value->filter as $filter)
                {
                    if (isset($filter->exposeOnUrl) && $filter->exposeOnUrl && isset($params[$filter->key]))
                    {
                        $filterValue = $params[$filter->key];
                    }
                    else
                    {
                        $filterValue = $filter->constant;
                    }
                    if (isset($value->property))
                    {
                        $returnValue['filters'][$value->key] = "{$value->key}.{$filter->value} {$filter->comparison} {$filterValue}";
                    }
                    else
                    {
                        $returnValue['filters'][$value->key] = "{$value->key}_av.value {$filter->comparison} {$filterValue}";
                    }
                }

            }
            if(isset($value->name))
            {
                $returnValue['columns'][$value->key]['name'] = $value->name;
                $returnValue['columns'][$value->key]['key'] = $value->key;
                $returnValue['columns'][$value->key]['table'] = $table;

                if(property_exists($value,'hidden'))
                {
                    $returnValue['columns'][$value->key]['hidden'] = $value->hidden;
                }

                if(isset($value->format))
                {
                    $returnValue['columns'][$value->key]['format'] = $value->format;
                }
            }
            if(isset($value->property))
            {

                $returnValue['selects'][$value->key] = "{$table}.{$value->property}  as {$value->key}";
                $returnValue['selects'][$value->key."_id"] = "concat('{$table}__',{$table}.id) as {$value->key}__id";
            }
            if (isset($value->attribute))
            {
                $returnValue['joins'][$value->key."_av"] = "{$table}.attributeValues";
                $returnValue['joins'][$value->key."_a"] = "{$value->key}_av.attribute";
                $returnValue['filters'][$value->key."_a"] = "{$value->key}_a.key = '{$value->attribute}'";
                if (isset($value->attributeEntity))
                {
                    $returnValue['subJoins'][$value->key] = [];
                    $returnValue['subJoins'][$value->key]['class'] = "UC\Portal\Model\\{$value->attributeEntity}";
                    $returnValue['subJoins'][$value->key]['condition'] = "{$value->key}_av.value = {$value->key}.id";
                    if(isset($value->values))
                    {

                        $newReturnArray = $this->processValues($value->values,$value->key,$queryFilters,$params);
                        $returnValue = $this->mergeReturnArray($returnValue,$newReturnArray);
                    }
                }
                else
                {
                    $returnValue['selects'][$value->key] = "{$value->key}_av.value as {$value->key}";
                }
            }
            if(isset($value->sortOrder))
            {
                if(isset($value->sortDirection))
                {
                    if (isset($value->property))
                    {
                        $returnValue['sorts'][$value->sortOrder] = ["{$table}.{$value->property}", $value->sortDirection];
                    }
                    else
                    {
                        $returnValue['sorts'][$value->sortOrder] = ["{$value->key}_av.value", $value->sortDirection];
                    }

                }
                else // ["{$lastPart['lastTable']}.{$lastPart['part']}", $column['sortDirection']]
                {
                    if (isset($value->property))
                    {
                        $returnValue['sorts'][$value->sortOrder] = ["{$table}.{$value->property}", "ASC"];
                    }
                    else
                    {
                        $returnValue['sorts'][$value->sortOrder] = ["{$value->key}_av.value", "ASC"];
                    }
                }
            }
        }

        return $returnValue;
    }

    public function getQueryResults($columnMetaData, $startingModel)
    {
        $selectStringArray = [];
        $selectStringArray[] = 't0.id as t0__id';
        foreach ($columnMetaData['selects'] as $select)
        {
            $selectStringArray[] = $select;
        }

        $selectString = implode(",", $selectStringArray);

        $qb = $this->entityManager->createQueryBuilder();
        $qb = $qb->select($selectString);

        $qb = $qb->from($startingModel, "t0");

        foreach ($columnMetaData['joins'] as $key=>$join)
        {
            $qb = $qb
                ->join($join, $key);
        }
        foreach ($columnMetaData['subJoins'] as $key=>$join)
        {
            $qb = $qb
                ->join($join['class'], $key, \Doctrine\ORM\Query\Expr\Join::WITH, $join['condition']);
        }

        foreach ($columnMetaData['filters'] as $filter)
        {
            $qb = $qb
                ->andWhere($filter);
        }

        foreach ($columnMetaData['sorts'] as $sortKey=>$sortObject)
        {
            $qb = $qb
              ->addOrderBy($sortObject[0], $sortObject[1]);
        }

        $qb = $qb
            ->getQuery();

//        $dql = $qb->getDQL();

//        var_dump($dql);
//        die();
        $result = $qb
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return $result;
    }

    public function fixReportDisplay($chunkedData, $columnMap): array
    {
        $fixedReportDisplay = [];
        foreach ($chunkedData as $tableKey=>$table)
        { // $table is each table in the data
            foreach ($table as $tableRowKey=>$tableRowArray)
            { // $tableRowArray includes the rows that the table actually displays
                foreach ($tableRowArray as $arrayRowKey=>$rowContents)
                { // $rowContents is each row in the array of rows that is being combined into one row in the table (0,1,2, etc.)
                    foreach ($columnMap as $columnKey=>$columnMapPart)
                    { // $columnContents is the content inside each column in this row.
                            $columnMapArray = explode(';',$columnMapPart);
                            $columnIdentifier = '';

                            foreach($columnMapArray as $part)
                            {
                                $identifierKey = $part . "__id";
                                if(array_key_exists($identifierKey,$rowContents))
                                {
                                    $columnIdentifier = $columnIdentifier . $part . $rowContents[$identifierKey] . ";";
                                }
                            }
                        $chunkedData[$tableKey][$tableRowKey][$arrayRowKey]["{$columnKey}__columnIdentifier"] = $columnIdentifier;

                    }
                }
            }

        }
        return $chunkedData;
    }

}


