<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
//                                                                ** SPLIT PHP FRAMEWORK **                                                                       //
// This file is part of *SPLIT PHP Framework*                                                                                                                     //
//                                                                                                                                                                //
// Why "SPLIT"? Firstly because the word "split" is a reference to micro-services and split systems architecture (of course you can make monoliths with it,       //
// if that's your thing). Furthermore, it is an acronym for these 5 bound concepts which are the bases that this framework leans on, which are: "Simplicity",     //
// "Purity", "Lightness", "Intuitiveness", "Target Minded"                                                                                                        //
//                                                                                                                                                                //
// See more info about it at: https://github.com/gabriel-guelfi/split-php                                                                                         //
//                                                                                                                                                                //
// MIT License                                                                                                                                                    //
//                                                                                                                                                                //
// Copyright (c) 2025 Lightertools Open Source Community                                                                                                          //
//                                                                                                                                                                //
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to          //
// deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or         //
// sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:                            //
//                                                                                                                                                                //
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.                                 //
//                                                                                                                                                                //
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS     //
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY           //
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.     //
//                                                                                                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

namespace SplitPHP\DbMigrations;

use SplitPHP\Database\DbVocab;

/**
 * Blueprint for creating database tables.
 */
final class TableBlueprint extends Blueprint
{
  /**
   * @var string The label for the table.
   */
  private $label;

  /**
   * @var array The columns for the table.
   */
  private $columns;

  /**
   * @var array The indexes for the table.
   */
  private $indexes;

  /**
   * @var array The foreign keys for the table.
   */
  private $foreignKeys;

  /**
   * @var string The charset for the table.
   */
  private $charset;

  /**
   * @var string The collation for the table.
   */
  private $collation;

  /**
   * @var array The seed data for the table.
   */
  private $seeds;

  /**
   * Constructor for the TableBlueprint class.
   *
   * @param string $name The name of the table.
   * @param string|null $label The label for the table (optional).
   * @param string $charset The charset for the table (default is 'utf8mb4').
   * @param string $collation The collation for the table (default is 'utf8mb4_general_ci').
   */
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
    $this->seeds = [];
  }

  /**
   * Get the label for this table.
   *
   * @return string The label.
   */
  public function getLabel(): string
  {
    return $this->label;
  }

  /**
   * Get the charset for this table.
   *
   * @return string The charset.
   */
  public function getCharset(): string
  {
    return $this->charset;
  }

  /**
   * Get the collation for this table.
   *
   * @return string The collation.
   */
  public function getCollation(): string
  {
    return $this->collation;
  }

  /**
   * Creates a new Column instance for this table.
   *
   * @param string $name The name of the column.
   * @param string $type The type of the column (default is DbVocab::DATATYPE_INT).
   * @param int|null $length The length of the column (optional).
   * @return ColumnBlueprint The created Column instance.
   */
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

  /**
   * Get the columns for this table.
   *
   * @param array|string|null $columns The columns to retrieve.
   * @return array|null|ColumnBlueprint The columns.
   */
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

  /**
   * Creates a new Index instance for this table.
   *
   * @param string $name The name of the index.
   * @param string $type The type of the index (default is DbVocab::IDX_INDEX).
   * @return IndexBlueprint The created Index instance.
   */
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

  /**
   * Get the indexes for this table.
   *
   * @param array|string|null $indexes The indexes to retrieve.
   * @return array|null|IndexBlueprint The indexes.
   */
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

  /**
   * Creates a new ForeignKey instance for this table.
   *
   * @param array|string $columns The columns to include in the foreign key.
   * @return ForeignKeyBlueprint The created ForeignKey instance.
   */
  public function Foreign(array|string $columns)
  {
    $fkBlueprint = new ForeignKeyBlueprint(
      columns: $columns,
      tableRef: $this
    );
    $this->foreignKeys[$fkBlueprint->getName()] = $fkBlueprint;

    return $fkBlueprint;
  }

  /**
   * Get the foreign keys for this table.
   *
   * @param array|string|null $foreignKeys The foreign keys to retrieve.
   * @return array|null|ForeignKeyBlueprint The foreign keys.
   */
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

  /**
   * Creates a new Seed instance for this table.
   *
   * @param int $batchSize The number of rows to insert in each batch.
   * @return Seed The created Seed instance.
   */
  public function Seed(int $batchSize = 1): Seed
  {
    $seed = new Seed($this, $batchSize);
    $this->seeds[$seed->getName()] = $seed;
    return $this->seeds[$seed->getName()];
  }

  /**
   * Get the seeds for this table.
   *
   * @return array The seeds.
   */
  public function getSeeds(): array
  {
    return $this->seeds;
  }
}
