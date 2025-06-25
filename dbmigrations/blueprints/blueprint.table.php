<?php

namespace SplitPHP\DbMigrations;

use SplitPHP\Database\DbVocab;

final class TableBlueprint extends Blueprint
{
  private $label;
  private $columns;
  private $indexes;
  private $foreignKeys;
  private $charset;
  private $collation;

  public function __construct(string $name, ?string $label = null, string $charset = 'utf8mb4', string $collation = 'utf8mb4_general_ci')
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.column.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.index.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.foreignkey.php';

    unset($this->tableRef);
    $this->name = $name;
    $this->label = $label ?? $name;
    $this->charset = $charset;
    $this->collation = $collation;
    $this->columns = [];
    $this->indexes = [];
    $this->foreignKeys = [];
  }

  public function getLabel(): string
  {
    return $this->label;
  }

  public function getCharset(): string
  {
    return $this->charset;
  }

  public function getCollation(): string
  {
    return $this->collation;
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

  public function getColumns($columns = null): array|null|ColumnBlueprint
  {
    if ($columns === null) {
      return $this->columns;
    } elseif (is_string($columns) && array_key_exists($columns, $this->columns)) {
      return $this->columns[$columns];
    } elseif (is_array($columns)) {
      return array_filter(
        $this->columns,
        function ($key) use ($columns) {
          return in_array($key, $columns);
        },
        ARRAY_FILTER_USE_KEY
      );
    } else return null;
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

  public function getIndexes($indexes = null): array|null|IndexBlueprint
  {
    if ($indexes === null) {
      return $this->indexes;
    } elseif (is_string($indexes) && array_key_exists($indexes, $this->indexes)) {
      return $this->indexes[$indexes];
    } elseif (is_array($indexes)) {
      return array_filter(
        $this->indexes,
        function ($key) use ($indexes) {
          return in_array($key, $indexes);
        },
        ARRAY_FILTER_USE_KEY
      );
    } else return null;
  }

  public function Foreign(array|string $columns)
  {
    $fkBlueprint = new ForeignKeyBlueprint(
      columns: $columns,
      tableRef: $this
    );
    $this->foreignKeys[$fkBlueprint->getName()] = $fkBlueprint;

    return $fkBlueprint;
  }

  public function getForeignKeys($foreignKeys = null): array|null|ForeignKeyBlueprint
  {
    if ($foreignKeys === null) {
      return $this->foreignKeys;
    } elseif (is_string($foreignKeys) && array_key_exists($foreignKeys, $this->foreignKeys)) {
      return $this->foreignKeys[$foreignKeys];
    } elseif (is_array($foreignKeys)) {
      return array_filter(
        $this->foreignKeys,
        function ($key) use ($foreignKeys) {
          return in_array($key, $foreignKeys);
        },
        ARRAY_FILTER_USE_KEY
      );
    } else return null;
  }
}
