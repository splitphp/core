<?php

namespace SplitPHP\DbMigrations;

use Exception;
use SplitPHP\Database\SqlExpression;
use SplitPHP\Database\DbVocab;

final class ColumnBlueprint extends Blueprint
{
  private $type;
  private $length;
  private $nullableFlag;
  private $charset;
  private $collation;
  private $defaultValue;
  private $autoIncrementFlag;
  private $unsignedFlag;

  public function __construct(
    TableBlueprint $tableRef,
    string $name,
    string $type,
    ?int $length = null
  ) {
    if (!in_array($type, DbVocab::DATATYPES_ALL))
      throw new Exception("Invalid type '{$type}' for column '{$name}'. Allowed types are: " . implode(', ', DbVocab::DATATYPES_ALL));

    $tbname = $tableRef->getName();
    foreach ($tableRef->getColumns() as $clm)
      if ($clm->getName() == $name)
        throw new Exception("The column '{$name}' is already defined in this migration for the table '{$tbname}'.");

    $this->tableRef = $tableRef;
    $this->name = $name;
    $this->type = $type;
    $this->length = $length;
    $this->nullableFlag = false;
    $this->autoIncrementFlag = false;
    $this->unsignedFlag = false;
    $this->charset = 'utf8mb4';
    $this->collation = 'utf8mb4_general_ci';
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getLength(): ?int
  {
    return $this->length;
  }

  public function setCharset(string $charset)
  {
    $this->charset = $charset;
    return $this;
  }

  public function getCharset(): string
  {
    return $this->charset;
  }

  public function setCollation(string $collation)
  {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation(): string
  {
    return $this->collation;
  }

  public function nullable()
  {
    $this->nullableFlag = true;
    return $this;
  }

  public function isNullable(): bool
  {
    return $this->nullableFlag;
  }
  
  public function autoIncrement()
  {
    $this->autoIncrementFlag = true;
    return $this;
  }

  public function hasAutoIncrement(): bool
  {
    return $this->autoIncrementFlag;
  }

  public function unsigned()
  {
    if ($this->type !== DbVocab::DATATYPE_INT && $this->type !== DbVocab::DATATYPE_BIGINT)
      throw new Exception("[Invalid unsigned error]: Column '{$this->name}' must be of type INT or BIGINT to be unsigned.");
    $this->unsignedFlag = true;
    return $this;
  }

  public function isUnsigned(): bool
  {
    return $this->unsignedFlag;
  }

  public function setDefaultValue($val)
  {
    if ($val === null && !$this->nullableFlag)
      throw new Exception("[Invalid default value error]: Column {$this->name} cannot be NULL.");

    if (
      $val instanceof SqlExpression
      && $val->equals(DbVocab::SQL_CURTIMESTAMP())
      && !in_array($this->type, DbVocab::DATATYPE_GROUPS['dateAndTime'])
    ) throw new Exception("[Invalid default value error]: Column {$this->name} cannot store a date or time value.");

    if (is_string($val) && !is_numeric($val))
      $val = "'{$val}'";

    $this->defaultValue = $val;

    return $this;
  }

  public function getDefaultValue()
  {
    return $this->defaultValue;
  }

  public function primary()
  {
    $this->Index('pk', DbVocab::IDX_PRIMARY)
      ->onColumn($this->name);
    return $this;
  }
}
