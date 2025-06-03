<?php

namespace SplitPHP\DbMigrations;

use Exception;

final class TableBlueprint
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

    $this->name = $name;
    $this->columns = [];
    $this->indexes = [];
    $this->foreignKeys = [];
  }

  public function info()
  {
    return (object) get_object_vars($this);
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

  // Shortcut column definition functions:
  public function id($columnName)
  {
    return $this->Column($columnName)
      ->primary()
      ->autoIncrement();
  }

  public function string($columnName, $length = 255)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_STRING,
      length: $length
    );
  }

  public function text($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TEXT
    );
  }

  public function int($columnName)
  {
    return $this->Column($columnName);
  }

  public function bigInt($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BIGINT
    );
  }

  public function decimal($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DECIMAL
    );
  }

  public function float($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_FLOAT
    );
  }

  public function date($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DATE
    );
  }

  public function datetime($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DATETIME
    );
  }

  public function time($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TIME
    );
  }

  public function timestamp($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TIMESTAMP
    );
  }

  public function boolean($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BOOL
    );
  }

  public function blob($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BINARY
    );
  }

  public function json($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_JSON
    );
  }

  public function uuid($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_UUID
    );
  }
}
