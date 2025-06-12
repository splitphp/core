<?php

namespace SplitPHP\DbMigrations;

use Exception;
use SplitPHP\Database\DbVocab;
use SplitPHP\Database\Sql;

final class IndexBlueprint extends Blueprint
{
  private $type;
  private $columns;

  public final function __construct(TableBlueprint $tableRef, string $name, string $type)
  {
    if (!in_array($type, DbVocab::INDEX_TYPES))
      throw new Exception("Invalid type '{$type}' for index named: '{$name}'. Available types: " . implode(', ', DbVocab::INDEX_TYPES));

    $tbname = $tableRef->getName();
    foreach ($tableRef->getIndexes() as $idx) {
      // Check if this index is already defined for the table:
      if ($idx->getName() == $name)
        throw new Exception("Index '{$name}' is already defined for table '{$tbname}'.");

      // If it's a primary key, check if there is another primary key already defined for the table:
      if ($type == DbVocab::IDX_PRIMARY && $idx->getType() == DbVocab::IDX_PRIMARY)
        throw new Exception("Table '{$tbname}' already has one primary key defined, you can't define another one.");
    }
    $this->tableRef = $tableRef;
    $this->name = $type == DbVocab::IDX_PRIMARY ? Sql::INDEX_DICT[DbVocab::IDX_PRIMARY] : $name;
    $this->type = $type;
    $this->columns = [];
  }

  public function onColumn(string $name)
  {
    if (is_numeric($name))
      throw new Exception("Invalid column name '{$name}' for index '{$this->name}'.");

    $this->columns[] = $name;
  }

  public function setColumns(array $columns)
  {
    $columns = array_values($columns);

    for ($i = 0; $i < count($columns); $i++)
      if (!is_string($columns[$i]) || is_numeric($columns[$i]))
        throw new Exception("Invalid column name '{$columns[$i]}' among columns set for index '{$this->name}'.");

    $this->columns = array_merge($this->columns, $columns);
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getColumns(): array
  {
    return $this->columns;
  }
}
