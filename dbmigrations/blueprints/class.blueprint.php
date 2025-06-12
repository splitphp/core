<?php

namespace SplitPHP\DbMigrations;

use SplitPHP\Database\DbVocab;

abstract class Blueprint
{
  private const INTERNAL_PROPS = [
    'tableRef'
  ];

  protected $tableRef;
  protected $name;
  protected $dropFlag = false;

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

  public final function drop()
  {
    $this->dropFlag = true;
    return $this;
  }

  public final function isToDrop(): bool
  {
    return $this->dropFlag;
  }

  public final function getName(): string
  {
    return $this->name;
  }

  public final function getTableRef(): ?TableBlueprint
  {
    return $this->tableRef;
  }

  // Shortcut column definition functions:
  public final function id($columnName)
  {
    return $this->Column($columnName)
      ->unsigned()
      ->primary()
      ->autoIncrement();
  }

  public final function string($columnName, $length = 255)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_STRING,
      length: $length
    );
  }

  public final function text($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TEXT
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
      type: DbVocab::DATATYPE_BIGINT
    );
  }

  public final function decimal($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DECIMAL
    );
  }

  public final function float($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_FLOAT
    );
  }

  public final function date($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DATE
    );
  }

  public final function datetime($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DATETIME
    );
  }

  public final function time($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TIME
    );
  }

  public final function timestamp($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TIMESTAMP
    );
  }

  public final function boolean($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_BOOL
    );
  }

  public final function blob($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_BLOB
    );
  }

  public final function json($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_JSON
    );
  }

  public final function uuid($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_UUID
    );
  }
}
