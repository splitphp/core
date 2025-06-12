<?php

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\AppLoader;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Utils;
use SplitPHP\Database\Dao;
use SplitPHP\Database\Sql;
use SplitPHP\Database\DbConnections;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Database\SqlExpression;
use SplitPHP\DbMigrations\TableBlueprint;
use SplitPHP\ModLoader;

class Migrations extends Cli
{
  private $sqlBuilder;

  public function init()
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform migrations.");

    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");

    require_once CORE_PATH . '/dbmigrations/class.migration.php';

    DbMetadata::checkUserRequiredAccess('Migrations', true);
    Dbmetadata::createMigrationControl();

    // Apply Command:
    $this->addCommand('apply', function ($args) {
      if (isset($args['limit'])) {
        if (!is_numeric($args['limit']) || $args['limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['limit'];
      }

      if (isset($args['module'])) {
        if (!is_string($args['module']) || is_numeric($args['module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['module'];
      }

      // List all migrations to be applied:
      $migrations = [];
      foreach (ModLoader::listMigrations($module ?? null) as $modMigrations) {
        $migrations = [...$migrations, ...$modMigrations];
      }
      $migrations = [...$migrations, ...(empty($module) ? AppLoader::listMigrations() : [])];

      $counter = 0;
      // Apply all listed migrations:
      foreach ($migrations as $mpath) {
        if (isset($limit) && $counter >= $limit) return;
        $this->applyMigration($mpath, $counter);
      }
    });

    // Rollback Command:
    $this->addCommand('rollback', function ($args) {
      $limit = null;
      if (isset($args['limit'])) {
        if (!is_numeric($args['limit']) || $args['limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['limit'];
      }

      if (isset($args['module'])) {
        if (!is_string($args['module']) || is_numeric($args['module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['module'];
      }

      $mfpaths = empty($module) ? [] : ModLoader::listMigrations($module);

      $moduleFilter = '';
      $dao = $this->getDao('SPLITPHP_MIGRATION');

      if (!empty($mfpaths)) {
        $dao = $dao->filter('filepath')->in($mfpaths);
        $moduleFilter = "WHERE m.filepath IN ?filepath?";
      }

      $sql = "SELECT 
            m.id AS id,
            m.name AS name,
            m.filepath AS filepath,
            m.date_exec AS date_exec,
            o.down AS down
          FROM SPLITPHP_MIGRATION m
          JOIN SPLITPHP_MIGRATION_OPERATION o ON m.id = o.id_migration
          {$moduleFilter}
          ORDER BY o.id DESC";

      $counter = 0;
      $execControl = [];

      $dao->fetch(function ($operation) use (&$counter, $limit, &$execControl) {
        if (!is_null($limit) && $counter >= $limit) {
          Utils::printLn("Limit reached, stopping rollback.");
          return false;
        }

        if (!in_array($operation->id, $execControl)) {
          $execControl[] = $operation->id;

          Utils::printLn("* Rolling back migration: '" . $operation->name . "' applied at " . $operation->date_exec . ":\n");

          Dao::flush();
        }

        $opDown = $this->sqlBuilder
          ->write($operation->down, overwrite: true)
          ->output(true);

        echo '"' . $operation->down . "\"\n\n";

        // Perform the operation:
        DbConnections::retrieve('main')->runMany($opDown);

        $counter = count($execControl);

        $this->getDao('SPLITPHP_MIGRATION')
          ->filter('id')->equalsTo($operation->id)
          ->delete();
      }, $sql);
    });
  }

  /**
   * Applies a migration by loading the migration object from the specified file path,
   * executing its apply method, and saving the operations in the database.
   *
   * @param string $fpath The file path of the migration to be applied.
   */
  private function applyMigration($fpath, &$counter)
  {
    if ($this->alreadyApplied($fpath)) return;

    // Find the migration name from the file path:
    $sepIdx = strpos(basename($fpath), '_');
    $mName = substr(basename($fpath), $sepIdx + 1, strrpos(basename($fpath), '.') - $sepIdx - 1);
    $mName = str_replace('-', ' ', $mName);
    $mName = ucwords($mName);
    Utils::printLn("* Applying migration: '" . $mName . "':");
    Utils::printLn();

    $mobj = ObjLoader::load($fpath);
    $mobj->apply();
    $operations = $mobj->getOperations();

    if (empty($operations)) return;

    // Save the migration key in the database:
    $migration = $this->getDao('SPLITPHP_MIGRATION')
      ->insert([
        'name' => $mName,
        'date_exec' => date('Y-m-d H:i:s'),
        'filepath' => $fpath,
        'mkey' => hash('sha256', file_get_contents($fpath))
      ]);

    // Handle operations:
    foreach ($operations as $o) {
      $this->obtainUpAndDown($o);
      echo '"' . $o->up->sqlstring . "\"\n\n";

      // Perform the operation:
      DbConnections::retrieve('main')->runMany($o->up);

      // Save the operation in the database:
      $this->getDao('SPLITPHP_MIGRATION_OPERATION')
        ->insert([
          'id_migration' => $migration->id,
          'up' => $o->up->sqlstring,
          'down' => $o->down->sqlstring,
        ]);
    }

    Dao::flush();
    $counter++;
  }

  /**
   * Checks if a migration has already been applied by comparing the file's content hash
   * with the stored migration keys in the database.
   *
   * @param string $fpath The file path of the migration to check.
   * @return bool Returns true if the migration has already been applied, false otherwise.
   */
  private function alreadyApplied($fpath)
  {
    $mkey = hash('sha256', file_get_contents($fpath));

    return !empty($this->getDao('SPLITPHP_MIGRATION')
      ->filter('mkey')->equalsTo($mkey)
      ->first());
  }

  /**
   * This function obtains the SQL statements for the "up" and "down" operations
   * of a migration operation, based on the current state of the table.
   *
   * @param object $operation The migration operation object to be modified.
   */
  private function obtainUpAndDown(&$operation)
  {
    $tbInfo = $operation->table;

    // -> Drop Operation:
    if ($tbInfo->isToDrop()) {
      $operation->up = $this->dropTableOperation($tbInfo);
      $operation->down = $this->createTableOperation($this->tbCurrentStateBlueprint($tbInfo->getName()));
    }

    // -> Alter Operation:
    elseif (in_array($tbInfo->getName(), Dbmetadata::listTables())) {
      $currentTbInfo = $this->tbCurrentStateBlueprint($tbInfo->getName());

      $sqlUp = $this->sqlBuilder->alter(
        tbName: $tbInfo->getName()
      );

      $sqlDown = clone $this->sqlBuilder;

      // Handle columns: 
      if (!empty($tbInfo->getColumns())) {
        $this->handleColumns($sqlUp, $sqlDown, $tbInfo->getColumns(), $currentTbInfo);
      }

      // Handle indexes: 
      if (!empty($tbInfo->getIndexes())) {
        $this->handleIndexes($sqlUp, $sqlDown, $tbInfo->getIndexes(), $currentTbInfo);
      }

      // Handle foreign keys:
      if (!empty($tbInfo->getForeignKeys())) {
        $this->handleForeignKeys($sqlUp, $sqlDown, $tbInfo, $currentTbInfo);
      }

      $operation->up = $sqlUp->output(true);
      $operation->down = $sqlDown->output(true);
    }

    // -> Create Operation: 
    else {
      $operation->up = $this->createTableOperation($tbInfo);
      $operation->down = $this->dropTableOperation($tbInfo);
      return;
    }
  }

  /**
   * Creates the SQL statements to create a table based on the provided table
   * information.
   *
   * @param TableBlueprint $tbInfo The table information object containing details about the table to be created.
   * This function also handles the addition of indexes, auto-increment and foreign keys.
   *                               
   * @return string The SQL statement to create the table.
   */
  private function createTableOperation(TableBlueprint $tbInfo)
  {
    $sqlBuilder = $this->sqlBuilder;
    $autoIncrements = [];

    $columns = [];
    foreach ($tbInfo->getColumns() as $col) {

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
      tbName: $tbInfo->getName(),
      columns: $columns,
      charset: $tbInfo->getCharset(),
      collation: $tbInfo->getCollation()
    );

    // Create SQL to apply indexes:
    if (!empty($tbInfo->getIndexes())) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->getName()
        );

      foreach ($tbInfo->getIndexes() as $idx) {
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
          tbName: $tbInfo->getName()
        );
      foreach ($autoIncrements as $colName) {
        $sqlBuilder->columnAutoIncrement(
          columnName: $colName
        );
      }
    }

    // Create SQL to apply foreign keys:
    if (!empty($tbInfo->getForeignKeys())) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->getName()
        );

      foreach ($tbInfo->getForeignKeys() as $fk) {
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
   * @param TableBlueprint $tbInfo The table information object containing details
   *                               about the table to be dropped.
   * @return string The SQL statement to drop the table.
   */
  private function dropTableOperation(TableBlueprint $tbInfo)
  {
    $sqlBuilder = $this->sqlBuilder;

    // Create SQL to apply foreign keys:
    if (!empty($tbInfo->getForeignKeys())) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->getName()
        );

      foreach ($tbInfo->getForeignKeys() as $fk)
        $sqlBuilder->dropConstraint($fk->getName());
    }

    // Create SQL to apply auto increment:
    $autoIncrementStarted = false;
    foreach ($tbInfo->getColumns() as $col) {
      if ($col->hasAutoIncrement()) {
        if (!$autoIncrementStarted) {
          $autoIncrementStarted = true;
          $sqlBuilder
            ->alter(
              tbName: $tbInfo->getName()
            );
        }

        $sqlBuilder->columnAutoIncrement(
          columnName: $col->getName(),
          drop: true
        );
      }
    }

    // Create SQL to apply indexes:
    if (!empty($tbInfo->getIndexes())) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->getName()
        );

      foreach ($tbInfo->getIndexes() as $idx) {
        $sqlBuilder->dropIndex(
          name: $idx->getName(),
        );
      }
    }

    // Create SQL to drop the table:
    return $sqlBuilder->dropTable(
      tbName: $tbInfo->getName()
    )->output(true);
  }

  /**
   * Returns the current state of a table(before being modified) as a TableBlueprint object.
   *
   * @param string $tbName The name of the table to retrieve the current state for.
   * @return TableBlueprint A TableBlueprint object representing the current state of the table.
   */
  private function tbCurrentStateBlueprint(string $tbName): TableBlueprint
  {
    /**
     * Returns an object as follows:
     * stdClass(
     *  [table]      => (string)$tablename,
     *  [engine]      => (string)table's enginename (e.g. InnoDB),
     *  [charset]     => (string)table's charset (e.g. utf8mb4),
     *  [collation]   => (string)table's collation (e.g. utf8mb4_general_ci),
     *  [columns]     => [ /* array of stdClass, one per column found  ],
     *  [references] => [ /* assoc array: other_table_name => stdClass(from KEY_COLUMN_USAGE)  ],
     *  [key]        => (object)[ 'keyname'=>PRIMARY_COLUMN, 'keyalias'=>TABLENAME . "_" . PRIMARY_COLUMN ],
     *  [relatedTo]  => [ /* assoc array: referenced_table_name => stdClass(from KEY_COLUMN_USAGE)  ]
     *)
     */
    $tbmetadata = Dbmetadata::tbInfo($tbName, true);

    $tbInfo = new TableBlueprint(
      name: $tbmetadata['table'],
      charset: $tbmetadata['charset'],
      collation: $tbmetadata['collation']
    );

    // Set table's columns:
    $this->tbCurrentStateColumns($tbmetadata, $tbInfo);

    // Set table's indexes:
    $this->tbCurrentStateIndexes($tbmetadata, $tbInfo);

    // Set table's foreign keys:
    $this->tbCurrentStateForeignKeys($tbmetadata, $tbInfo);

    return $tbInfo;
  }

  /**
   * Prepares the default value for a column based on its type. Handles raw sql, NULL, strings, and numeric values.
   *
   * @param mixed $val The default value to be prepared.
   * @return mixed The prepared default value.
   */
  private function prepareDefaultVal($val)
  {
    if ($val === 'CURRENT_TIMESTAMP') return new SqlExpression('CURRENT_TIMESTAMP');

    elseif ($val === 'NULL') return null;

    elseif (is_string($val) && !is_numeric($val))
      return "'{$val}'";

    else return $val;
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's columns, 
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $tbInfo The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateColumns($tbmetadata, TableBlueprint &$tbInfo)
  {
    foreach ($tbmetadata['columns'] as $col) {
      $colInfo = $tbInfo->Column(
        name: $col['Field'],
        type: $col['Datatype'],
        length: $col['Length']
      );

      if (!is_null($col['Charset']))
        $colInfo->setCharset($col['Charset']);

      if (!is_null($col['Collation']))
        $colInfo->setCollation($col['Collation']);

      if ('YES' === $col['Null'])
        $colInfo->nullable();

      if ($col['Extra'] === 'auto_increment')
        $colInfo->autoIncrement();

      if (!empty($col['Default']))
        $colInfo->setDefaultValue($this->prepareDefaultVal($col['Default']));
    }
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's indexes,
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $tbInfo The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateIndexes($tbmetadata, TableBlueprint &$tbInfo)
  {
    if (empty($tbmetadata['indexes'])) return;

    foreach ($tbmetadata['indexes'] as $idx) {
      $tbInfo->Index(
        name: $idx['name'],
        type: $idx['type']
      )
        ->setColumns(array_map(function ($c) {
          return $c['column_name'];
        }, $idx['columns']));
    }
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's foreign keys,
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $tbInfo The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateForeignKeys($tbmetadata, TableBlueprint &$tbInfo)
  {
    if (empty($tbmetadata['relatedTo'])) return;

    $fks = [];
    foreach ($tbmetadata['relatedTo'] as $group) {
      foreach ($group as $fk) {
        if (!array_key_exists($fk['CONSTRAINT_NAME'], $fks)) {
          $fks[$fk['CONSTRAINT_NAME']] = (object)[
            'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
            'on_update_action' => array_flip(Sql::FKACTION_DICT)[$fk['UPDATE_RULE']],
            'on_delete_action' => array_flip(Sql::FKACTION_DICT)[$fk['DELETE_RULE']],
            'columns' => [],
            'referenced_columns' => []
          ];
        }

        $fks[$fk['CONSTRAINT_NAME']]->columns[] = $fk['COLUMN_NAME'];
        $fks[$fk['CONSTRAINT_NAME']]->referenced_columns[] = $fk['REFERENCED_COLUMN_NAME'];
      }
    }

    foreach ($fks as $fk) {
      $fkInfo = $tbInfo->Foreign($fk->columns)
        ->references($fk->referenced_columns)
        ->atTable($fk->referenced_table);

      if (!is_null($fk->on_update_action))
        $fkInfo->onUpdate($fk->on_update_action);

      if (!is_null($fk->on_delete_action))
        $fkInfo->onDelete($fk->on_delete_action);
    }
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
   * @param TableBlueprint $tbInfo The table information object containing details about the table.
   * @param TableBlueprint $currentTbInfo The current state of the table as a TableBlueprint object.
   */
  private function handleForeignKeys(&$sqlUp, &$sqlDown, TableBlueprint $tbInfo, TableBlueprint $currentTbInfo)
  {
    foreach ($tbInfo->getForeignKeys() as $fk) {
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
