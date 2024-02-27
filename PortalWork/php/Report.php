<?php
// this page was used in the project to initialize the database object for Reports

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\AssociationOverride;
use Doctrine\ORM\Mapping\AssociationOverrides;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Query\Expr\Base;
use UC\Portal\Model\AttributeHolder;

#[Entity]
#[Table(name: "Reports")]
class Report extends AttributeHolder
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Id, Column(type: "integer"), GeneratedValue(strategy: "AUTO")]
    public int $id;

    #[Column(type: "string")]
    public string $name;

    #[Column(type: "string")]
    public string $title;

    #[Column(type: "string", nullable: true)]
    public string $collectBy = "";

    #[Column(type: "integer", nullable: true)]
    public int $maxResults;

    #[ManyToOne(targetEntity: "EntityType")]
    #[JoinColumn(name: "entityTypeId", referencedColumnName: "id")]
    public \UC\Portal\Model\EntityType $entityType;

    #[Column(type: "text", nullable: true)]
    public string $filters = "{}";

    #[Column(type: "text", nullable: true)]
    public string $columns = "{}";

    #[Column(type: "text", nullable: true)]
    public string $jsonConfig = "{}";

    #[Column(type: "boolean", nullable: true, options: ["default" => 0])]
    public bool $isDeleted;
}