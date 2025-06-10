<?php

namespace SplitPHP\DbMigrations;

use Exception;
use SplitPHP\Database\DbVocab;

final class IndexBlueprint extends Blueprint
{
  private $name;
  private $type;
  private $columns;

  public final function __construct(TableBlueprint $tableRef, string $name, string $type)
  {
    if (!in_array($type, DbVocab::INDEX_TYPES))
      throw new Exception("Invalid type '{$type}' for index named: '{$name}'. Available types: " . implode(', ', DbVocab::INDEX_TYPES));

    $tbInfo = $tableRef->info();
    foreach ($tbInfo->indexes as $idx) {
      // Check if this index is already defined for the table:
      if ($idx->info()->name == $name)
        throw new Exception("Index '{$name}' is already defined for table '{$tbInfo->name}'.");

      // If it's a primary key, check if there is another primary key already defined for the table:
      if ($type == DbVocab::IDX_PRIMARY && $idx->info()->type == DbVocab::IDX_PRIMARY)
        throw new Exception("Table '{$tbInfo->name}' already has one primary key defined, you can't define another one.");
    }
    $this->tableRef = $tableRef;
    $this->name = $name;
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
}
