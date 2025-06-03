<?php

namespace SplitPHP\DbMigrations;

abstract class Blueprint
{
  protected $tableRef;

  public function Column(
    string $name,
    string $type = 'int',
    ?int $length = null
  ) {
    return $this->tableRef->Column(
      name: $name,
      type: $type,
      length: $length
    );
  }

  public function Index(
    string $name,
    string $type = 'INDEX'
  ) {
    return $this->tableRef->Index(
      name: $name,
      type: $type
    );
  }

  public function Foreign(array|string $columns)
  {
    return $this->tableRef->Foreign($columns);
  }

  public final function info()
  {
    return (object) get_object_vars($this);
  }

  // Shortcut column definition functions:
  public final function id($columnName)
  {
    return $this->Column($columnName)
      ->primary()
      ->autoIncrement();
  }

  public final function string($columnName, $length = 255)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_STRING,
      length: $length
    );
  }

  public final function text($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TEXT
    );
  }

  public final function int($columnName)
  {
    return $this->Column($columnName);
  }

  public final function bigInt($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BIGINT
    );
  }

  public final function decimal($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DECIMAL
    );
  }

  public final function float($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_FLOAT
    );
  }

  public final function date($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DATE
    );
  }

  public final function datetime($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_DATETIME
    );
  }

  public final function time($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TIME
    );
  }

  public final function timestamp($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_TIMESTAMP
    );
  }

  public final function boolean($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BOOL
    );
  }

  public final function blob($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_BINARY
    );
  }

  public final function json($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_JSON
    );
  }

  public final function uuid($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: MigrationVocab::DATATYPE_UUID
    );
  }
}
