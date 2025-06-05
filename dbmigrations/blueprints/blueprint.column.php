<?php

namespace SplitPHP\DbMigrations;

use Exception;
use SplitPHP\Database\SqlExpression;
use SplitPHP\Database\DbVocab;

final class ColumnBlueprint extends Blueprint
{
  private $name;
  private $type;
  private $length;
  private $nullableFlag;
  private $charset;
  private $collation;
  private $defaultValue;
  private $autoIncrementFlag;

  public function __construct(
    TableBlueprint $tableRef,
    string $name,
    string $type,
    ?int $length = null
  ) {
    if (!in_array($type, DbVocab::DATATYPES_ALL))
      throw new Exception("Invalid type '{$type}' for column '{$name}'. Allowed types are: " . implode(', ', DbVocab::DATATYPES_ALL));

    $tbInfo = $tableRef->info();
    foreach ($tbInfo->columns as $clm)
      if ($clm->info()->name == $name)
        throw new Exception("The column '{$name}' is already defined in this migration for the table '{$tbInfo->name}'.");

    $this->tableRef = $tableRef;
    $this->name = $name;
    $this->type = $type;
    $this->length = $length;
    $this->nullableFlag = false;
    $this->autoIncrementFlag = false;
    $this->charset = 'utf8mb4';
    $this->collation = 'utf8mb4_general_ci';
  }

  public function setCharset(string $charset)
  {
    $this->charset = $charset;
    return $this;
  }

  public function setCollation(string $collation)
  {
    $this->collation = $collation;
    return $this;
  }

  public function nullable()
  {
    $this->nullableFlag = true;
    return $this;
  }

  public function autoIncrement()
  {
    $this->autoIncrementFlag = true;
    return $this;
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

    $this->defaultValue = $val;

    return $this;
  }

  public function primary()
  {
    $this->Index('pk', DbVocab::IDX_PRIMARY)
      ->onColumn($this->name);
    return $this;
  }
}
