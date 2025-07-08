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

namespace SplitPHP\DbManager;

use SplitPHP\ObjLoader;
use SplitPHP\Helpers;
use SplitPHP\Database\DbVocab;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Utils;

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
   * @var object The SQL builder instance.
   */
  private $sqlBuilder;

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
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.column.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.index.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.foreignkey.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.seedcoupled.php';
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';

    unset($this->tableRef);
    $this->name = $name;
    $this->label = $label ?? $name;
    $this->charset = $charset;
    $this->collation = $collation;
    $this->columns = [];
    $this->indexes = [];
    $this->foreignKeys = [];
    $this->seeds = [];
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
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
   * @return CoupledSeedBlueprint The created Seed instance.
   */
  public function Seed(int $batchSize = 1): CoupledSeedBlueprint
  {
    $seed = new CoupledSeedBlueprint($this, $batchSize);
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

  /**
   * Obtains the SQL statements for the "up" and "down" operations of a table migration.
   *
   * @return object The SQL statements for the table migration.
   */
  public final function obtainSQL(): object
  {
    $result = (object) [
      'up' => null,
      'down' => null,
    ];

    if (empty($this->columns) && empty($this->indexes) && empty($this->foreignKeys)) {
      $result->up = $this->sqlBuilder->write('', $this->name)->output(true);
      $result->down = $this->sqlBuilder->write('', $this->name)->output(true);
    } else {
      $dbMapper = Helpers::DbMapper();

      // -> Drop Operation:
      if ($this->isToDrop()) {
        $result->up = $this->dropTableOperation($this);
        $result->down = $this->createTableOperation($dbMapper->tableBlueprint($this->name));
      }

      // -> Alter Operation:
      elseif (in_array($this->name, Dbmetadata::listTables())) {
        $currentTbInfo = $dbMapper->tableBlueprint($this->name);

        $sqlUp = $this->sqlBuilder->alter(
          tbName: $this->name
        );

        $sqlDown = clone $this->sqlBuilder;

        // Handle columns: 
        if (!empty($this->columns)) {
          $this->handleColumns($sqlUp, $sqlDown, $this->columns, $currentTbInfo);
        }

        // Handle indexes: 
        if (!empty($this->indexes)) {
          $this->handleIndexes($sqlUp, $sqlDown, $this->indexes, $currentTbInfo);
        }

        // Handle foreign keys:
        if (!empty($this->foreignKeys)) {
          $this->handleForeignKeys($sqlUp, $sqlDown, $this, $currentTbInfo);
        }

        $result->up = $sqlUp->output(true);
        $result->down = $sqlDown->output(true);
      }

      // -> Create Operation: 
      else {
        $result->up = $this->createTableOperation($this);
        $result->down = $this->dropTableOperation($this);
      }
    }

    // Handle seeds:
    if (!empty($this->seeds)) {
      foreach ($this->seeds as $seed) {
        if (!$seed->isAllowedInEnv(Utils::getEnv())) {
          Utils::printLn(">> Skipping seed: " . $seed->getName() . " - Not allowed in current environment: '" . Utils::getEnv() . "'");
          continue; // Skip seeds not allowed in the current environment
        }
        $upAndDown = $seed->obtainSQL();
        $result->up->append($upAndDown->up);
        $result->down->append($upAndDown->down);
      }
    }

    return $result;
  }

  /**
   * Creates the SQL statements to create a table based on the provided table
   * information.
   *
   * @param TableBlueprint $blueprint The table information object containing details about the table to be created.
   * This function also handles the addition of indexes, auto-increment and foreign keys.
   *                               
   * @return string The SQL statement to create the table.
   */
  private function createTableOperation(TableBlueprint $blueprint)
  {
    $sqlBuilder = $this->sqlBuilder;
    $autoIncrements = [];

    $columns = [];
    foreach ($blueprint->getColumns() as $col) {

      if ($col->hasAutoIncrement()) {
        $autoIncrements[] = $col->getName();
      }

      $columns[] = [
        'name' => $col->getName(),
        'type' => $col->getType(),
        'length' => $col->getLength(),
        'nullable' => $col->isNullable(),
        'unsigned' => $col->isUnsigned(),
        'defaultValue' => $col->getDefaultValue(),
        'charset' => $col->getCharset(),
        'collation' => $col->getCollation(),
      ];
    }

    // Create SQL to create the table:
    $sqlBuilder->create(
      tbName: $blueprint->getName(),
      columns: $columns,
      charset: $blueprint->getCharset(),
      collation: $blueprint->getCollation()
    );

    // Create SQL to apply indexes:
    if (!empty($blueprint->getIndexes())) {
      $sqlBuilder
        ->alter(
          tbName: $blueprint->getName()
        );

      foreach ($blueprint->getIndexes() as $idx) {
        $sqlBuilder->addIndex(
          name: $idx->getName(),
          type: $idx->getType(),
          columns: $idx->getColumns()
        );
      }
    }

    // Create SQL to apply auto increment:
    if (!empty($autoIncrements)) {
      $sqlBuilder
        ->alter(
          tbName: $blueprint->getName()
        );
      foreach ($autoIncrements as $colName) {
        $sqlBuilder->columnAutoIncrement(
          columnName: $colName
        );
      }
    }

    // Create SQL to apply foreign keys:
    if (!empty($blueprint->getForeignKeys())) {
      $sqlBuilder
        ->alter(
          tbName: $blueprint->getName()
        );

      foreach ($blueprint->getForeignKeys() as $fk) {
        $sqlBuilder->addConstraint(
          name: $fk->getName(),
          localColumns: $fk->getLocalColumns(),
          refTable: $fk->getReferencedTable(),
          refColumns: $fk->getReferencedColumns(),
          onUpdateAction: $fk->getOnUpdateAction(),
          onDeleteAction: $fk->getOnDeleteAction()
        );
      }
    }

    return $sqlBuilder->output(true);
  }

  /**
   * Creates the SQL statements to drop a table based on the provided table
   * information. This function also handles the removal of foreign keys, auto-increment and indexes.
   *
   * @param TableBlueprint $blueprint The table information object containing details
   *                               about the table to be dropped.
   * @return string The SQL statement to drop the table.
   */
  private function dropTableOperation(TableBlueprint $blueprint)
  {
    $sqlBuilder = $this->sqlBuilder;

    // Create SQL to apply foreign keys:
    if (!empty($blueprint->getForeignKeys())) {
      $sqlBuilder
        ->alter(
          tbName: $blueprint->getName()
        );

      foreach ($blueprint->getForeignKeys() as $fk)
        $sqlBuilder->dropConstraint($fk->getName());
    }

    // Create SQL to apply auto increment:
    $autoIncrementStarted = false;
    foreach ($blueprint->getColumns() as $col) {
      if ($col->hasAutoIncrement()) {
        if (!$autoIncrementStarted) {
          $autoIncrementStarted = true;
          $sqlBuilder
            ->alter(
              tbName: $blueprint->getName()
            );
        }

        $sqlBuilder->columnAutoIncrement(
          columnName: $col->getName(),
          drop: true
        );
      }
    }

    // Create SQL to apply indexes:
    if (!empty($blueprint->getIndexes())) {
      $sqlBuilder
        ->alter(
          tbName: $blueprint->getName()
        );

      foreach ($blueprint->getIndexes() as $idx) {
        $sqlBuilder->dropIndex(
          name: $idx->getName(),
        );
      }
    }

    // Create SQL to drop the table:
    return $sqlBuilder->dropTable(
      tbName: $blueprint->getName()
    )->output(true);
  }

  /**
   * Handles the addition, modification, or removal of columns in a table based on the provided column blueprints.
   *
   * @param object $sqlUp The SQL builder object for the "up" operation.
   * @param object $sqlDown The SQL builder object for the "down" operation.
   * @param array $columns An array of column blueprints to be processed.
   * @param TableBlueprint $currentTbInfo The current state of the table as a TableBlueprint object.
   */
  private function handleColumns(&$sqlUp, &$sqlDown, array $columns, TableBlueprint $currentTbInfo)
  {
    foreach ($columns as $col) {
      // -> Drop Operation:
      if ($col->isToDrop() && !empty($currentTbInfo->getColumns($col->getName()))) {
        $sqlUp->dropColumn($col->getName());

        $currentColState = $currentTbInfo->getColumns($col->getName());
        $sqlDown->addColumn(
          name: $currentColState->getName(),
          type: $currentColState->getType(),
          length: $currentColState->getLength(),
          nullable: $currentColState->isNullable(),
          unsigned: $currentColState->isUnsigned(),
          defaultValue: $currentColState->getDefaultValue(),
          autoIncrement: $currentColState->hasAutoIncrement()
        );
      }

      // -> Change Operation:
      elseif (!empty($currentTbInfo->getColumns($col->getName()))) {
        $sqlUp->changeColumn(
          name: $col->getName(),
          type: $col->getType(),
          length: $col->getLength(),
          nullable: $col->isNullable(),
          unsigned: $col->isUnsigned(),
          defaultValue: $col->getDefaultValue(),
          autoIncrement: $col->hasAutoIncrement()
        );

        $currentColState = $currentTbInfo->getColumns($col->getName());
        $sqlDown->changeColumn(
          name: $currentColState->getName(),
          type: $currentColState->getType(),
          length: $currentColState->getLength(),
          nullable: $currentColState->isNullable(),
          unsigned: $currentColState->isUnsigned(),
          defaultValue: $currentColState->getDefaultValue(),
          autoIncrement: $currentColState->hasAutoIncrement()
        );
      }

      // -> Add Operation:
      else {
        $sqlUp->addColumn(
          name: $col->getName(),
          type: $col->getType(),
          length: $col->getLength(),
          nullable: $col->isNullable(),
          unsigned: $col->isUnsigned(),
          defaultValue: $col->getDefaultValue(),
          autoIncrement: $col->hasAutoIncrement()
        );

        $sqlDown->dropColumn($col->getName());
      }
    }
  }

  /**
   * Handles the addition, modification, or removal of indexes in a table based on the provided index blueprints.
   *
   * @param object $sqlUp The SQL builder object for the "up" operation.
   * @param object $sqlDown The SQL builder object for the "down" operation.
   * @param array $indexes An array of index blueprints to be processed.
   * @param TableBlueprint $currentTbInfo The current state of the table as a TableBlueprint object.
   */
  private function handleIndexes(&$sqlUp, &$sqlDown, array $indexes, TableBlueprint $currentTbInfo)
  {
    foreach ($indexes as $idx) {
      // -> Drop Operation:
      if ($idx->isToDrop() && !empty($currentTbInfo->getIndexes($idx->getName()))) {
        $sqlUp->dropIndex($idx->getName());

        $currentIdxState = $currentTbInfo->getIndexes($idx->getName());
        $sqlDown->addIndex(
          name: $currentIdxState->getName(),
          type: $currentIdxState->getType(),
          columns: $currentIdxState->getColumns()
        );
      }

      // -> Change Operation:
      elseif (!empty($currentTbInfo->getIndexes($idx->getName()))) {
        // Drop current:
        $sqlUp->dropIndex($idx->getName());
        // Re-add modified index:
        $sqlUp->addIndex(
          name: $idx->getName(),
          type: $idx->getType(),
          columns: $idx->getColumns()
        );

        $currentIdxState = $currentTbInfo->getIndexes($idx->getName());
        // Drop modified:
        $sqlDown->dropIndex($idx->getName());
        // Re-add index as it previously was:
        $sqlDown->addIndex(
          name: $currentIdxState->getName(),
          type: $currentIdxState->getType(),
          columns: $currentIdxState->getColumns()
        );
      }

      // -> Add Operation:
      else {
        $sqlUp->addIndex(
          name: $idx->getName(),
          type: $idx->getType(),
          columns: $idx->getColumns()
        );

        $sqlDown->dropIndex($idx->getName());
      }
    }
  }

  /**
   * Handles the addition, modification, or removal of foreign keys in a table based on the provided foreign key blueprints.
   *
   * @param object $sqlUp The SQL builder object for the "up" operation.
   * @param object $sqlDown The SQL builder object for the "down" operation.
   * @param TableBlueprint $blueprint The table information object containing details about the table.
   * @param TableBlueprint $currentTbInfo The current state of the table as a TableBlueprint object.
   */
  private function handleForeignKeys(&$sqlUp, &$sqlDown, TableBlueprint $blueprint, TableBlueprint $currentTbInfo)
  {
    foreach ($blueprint->getForeignKeys() as $fk) {
      // -> Drop Operation:
      if ($fk->isToDrop() && !empty($currentTbInfo->getForeignKeys($fk->getName()))) {
        $sqlUp->dropConstraint($fk->getName());

        $currentFkState = $currentTbInfo->getForeignKeys($fk->getName());
        $sqlDown->addConstraint(
          name: $currentFkState->getName(),
          localColumns: $currentFkState->getLocalColumns(),
          refTable: $currentFkState->getReferencedTable(),
          refColumns: $currentFkState->getReferencedColumns(),
          onUpdateAction: $currentFkState->getOnUpdateAction(),
          onDeleteAction: $currentFkState->getOnDeleteAction()
        );
      }

      // -> Change Operation:
      elseif (!empty($currentTbInfo->getForeignKeys($fk->getName()))) {
        // Drop current:
        $sqlUp->dropConstraint($fk->getName());
        // Re-add modified foreign key:
        $sqlUp->addConstraint(
          name: $fk->getName(),
          localColumns: $fk->getLocalColumns(),
          refTable: $fk->getReferencedTable(),
          refColumns: $fk->getReferencedColumns(),
          onUpdateAction: $fk->getOnUpdateAction(),
          onDeleteAction: $fk->getOnDeleteAction()
        );

        $currentFkState = $currentTbInfo->getForeignKeys($fk->getName());
        // Drop modified:
        $sqlDown->dropConstraint($currentFkState->getName());
        // Re-add foreign key as it previously was:
        $sqlDown->addConstraint(
          name: $currentFkState->getName(),
          localColumns: $currentFkState->getLocalColumns(),
          refTable: $currentFkState->getReferencedTable(),
          refColumns: $currentFkState->getReferencedColumns(),
          onUpdateAction: $currentFkState->getOnUpdateAction(),
          onDeleteAction: $currentFkState->getOnDeleteAction()
        );
      }

      // -> Add Operation:
      else {
        $sqlUp->addConstraint(
          name: $fk->getName(),
          localColumns: $fk->getLocalColumns(),
          refTable: $fk->getReferencedTable(),
          refColumns: $fk->getReferencedColumns(),
          onUpdateAction: $fk->getOnUpdateAction(),
          onDeleteAction: $fk->getOnDeleteAction()
        );

        $sqlDown->dropConstraint($fk->getName());
      }
    }
  }
}
