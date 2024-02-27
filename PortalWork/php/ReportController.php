<?php
// this controller was used to hold and manage all routing for report related links and pages



require_once __DIR__ . "/../../vendor/autoload.php";

use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use UC\Portal\Model\Availability;
use UC\Portal\Model\Report;
use UC\Portal\Service;
use UC\Portal\Service\ApprovalService;
use UC\Portal\Service\ReportService;

class ReportController extends \UC\Portal\Controller\BaseController
{

    private ReportService $reportService;

    function __construct($entityManager, $twig)
    {
        parent::__construct($entityManager,$twig);
        $this->entityManager = $entityManager;
        $this->twig = $twig;
        $this->reportService = new ReportService($entityManager, $twig);
    }

    #[Route(path: '/report/list', name: 'reportList')]
    public function reportList($parameters)
    {
        $reportList = $this->reportService->getReportList();
        $html = $this->twig->render('/reports/listReports.html.twig', [
            'reportList' => $reportList,
        ]);

        return new Response($html);
    }

    #[Route(path: '/report/add', name: 'reportAdd')]
    public function reportAdd($parameters)
    {
        $report = new Report();

        $html = $this->twig->render('reports/editReport.html.twig', [
            'report' => $report,
        ]);

        return new \Symfony\Component\HttpFoundation\Response($html);
    }

    #[Route(path: '/report/update', name: 'reportUpdate')]
    public function reportUpdate($parameters)
    {
        $reportId = $this->reportService->updateReport($_POST);
        return new RedirectResponse("/report/{$reportId}/edit");
    }

    #[Route(path: '/report/delete', name: 'reportDelete', methods: ['POST'])]
    public function reportDelete($parameters)
    {
        $this->reportService->deleteReport($_POST);
    }

    #[Route(path: '/report/{id}/edit', name: 'reportEdit',  requirements: ["id"=>"\d+"])]
    public function formEdit($parameters)
    {
        $report = $this->reportService->getReport($parameters["id"]);

        $this->denyAccessUnlessGranted(["REPORT_EDIT"],$report);

        $html = $this->twig->render('reports/editReport.html.twig', [
            'report' => $report,
        ]);

        return new \Symfony\Component\HttpFoundation\Response($html);

    }

    #[Route(path: '/report/{id}/view', name: 'viewReport')]
    public function viewReport($parameters)
    {
        $report = $this->reportService->getReport($parameters['id']);
        $this->denyAccessUnlessGranted(["REPORT_VIEW"],$report);


        $data = $this->reportService->sendReportToProcesses($report, $_GET);


        $html = $this->twig->render('/reports/generateReport.html.twig', [
            'report' => $report,
            'columns' => $data->columns,
            'columnMap' => $data->columnMap,
            'data' => $data->data
        ]);
        return new \Symfony\Component\HttpFoundation\Response($html);
    }

    #[Route(path: '/report/{id}/view/json', name: 'viewReportJson')]
    public function viewReportJson($parameters)
    {
        $report = $this->reportService->getReport($parameters['id']);
        $this->denyAccessUnlessGranted(["REPORT_VIEW"],$report);


        $data = $this->reportService->sendReportToProcesses($report, $_GET);


        $html = $this->twig->render('/reports/generateReport.json.twig', [
            'report' => $report,
            'columns' => $data->columns,
            'columnMap' => $data->columnMap,
            'data' => $data->data
        ]);
        return new \Symfony\Component\HttpFoundation\Response($html);
    }
}
