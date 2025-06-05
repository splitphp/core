<?php

namespace SplitPHP\DbMigrations;

use SplitPHP\Database\DbVocab;

final class TableBlueprint extends Blueprint
{
  private $name;
  private $columns;
  private $indexes;
  private $foreignKeys;
  private $charset;
  private $collation;

  public function __construct(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_general_ci')
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.column.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.index.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.foreignkey.php';

    unset($this->tableRef);
    $this->name = $name;
    $this->charset = $charset;
    $this->collation = $collation;
    $this->columns = [];
    $this->indexes = [];
    $this->foreignKeys = [];
  }

  public function info()
  {
    $info = (object) get_object_vars($this);

    $info->hasColumn = function (string $colName) {
      return !empty(array_filter(
        $this->columns,
        fn($colBlueprint) => $colBlueprint->info()->name === $colName
      ));
    };

    $info->hasIndex = function (string $idxName) {
      return !empty(array_filter(
        $this->indexes,
        fn($idxBlueprint) => $idxBlueprint->info()->name === $idxName
      ));
    };

    $info->hasForeignKey = function (string $fkName) {
      return !empty(array_filter(
        $this->foreignKeys,
        fn($fkBlueprint) => $fkBlueprint->info()->name === $fkName
      ));
    };

    return $info;
  }

  public function Column(
    string $name,
    string $type = DbVocab::DATATYPE_INT,
    ?int $length = null
  ) {
    $columnBlueprint = new ColumnBlueprint(
      name: $name,
      type: $type,
      length: $length,
      tableRef: $this
    );
    $this->columns[$name] = $columnBlueprint;

    return $columnBlueprint;
  }

  public function Index(
    string $name,
    string $type = DbVocab::IDX_INDEX
  ) {
    $idxBlueprint = new IndexBlueprint(
      name: $name,
      type: $type,
      tableRef: $this
    );
    $this->indexes[$name] = $idxBlueprint;

    return $idxBlueprint;
  }

  public function Foreign(array|string $columns)
  {
    $fkBlueprint = new ForeignKeyBlueprint(
      columns: $columns,
      tableRef: $this
    );
    $this->foreignKeys[] = $fkBlueprint;

    return $fkBlueprint;
  }
}
