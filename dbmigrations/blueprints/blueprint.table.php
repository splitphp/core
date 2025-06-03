<?php

namespace SplitPHP\DbMigrations;

use Exception;

final class TableBlueprint extends Blueprint
{
  private $name;
  private $columns;
  private $indexes;
  private $foreignKeys;

  public function __construct(string $name)
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.column.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.index.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.foreignkey.php';

    unset($this->tableRef);
    $this->name = $name;
    $this->columns = [];
    $this->indexes = [];
    $this->foreignKeys = [];
  }

  public function Column(
    string $name,
    string $type = MigrationVocab::DATATYPE_INT,
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
    string $type = MigrationVocab::IDX_INDEX
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
