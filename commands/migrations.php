<?php

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\AppLoader;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Utils;
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

    $this->addCommand('apply', function ($args) {
      if (isset($args['limit'])) {
        if (!is_numeric($args['limit']) || $args['limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['limit'];
      }

      if (isset($args['module'])) {
        if (in_array('--only-application', $args))
          throw new Exception("You cannot use --only-application and also define a module. Please specify only one of them.");

        if (!is_string($args['module']) || is_numeric($args['module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['module'];
      }

      if (in_array('--only-application', $args)) {
        if (isset($args['module']))
          throw new Exception("You cannot use --only-application and also define a module. Please specify only one of them.");

        $onlyApp = true;
      }

      $counter = 0;
      // Apply migrations from Modules:
      if (empty($onlyApp))
        foreach (ModLoader::listMigrations($module ?? null) as $modMigrations)
          foreach ($modMigrations as $fpath) {
            if (isset($limit) && $counter >= $limit) return;
            $this->applyMigration($fpath);
            $counter++;
          }

      // Apply migrations from Main Application:
      if (empty($module))
        foreach (AppLoader::listMigrations() as $fpath) {
          if (isset($limit) && $counter >= $limit) return;
          $this->applyMigration($fpath);
          $counter++;
        }
    });

    $this->addCommand('rollback', function ($args) {
      if (isset($args['limit'])) {
        if (!is_numeric($args['limit']) || $args['limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['limit'];
      }

      if (isset($args['module'])) {
        if (in_array('--only-application', $args))
          throw new Exception("You cannot use --only-application and also define a module. Please specify only one of them.");

        if (!is_string($args['module']) || is_numeric($args['module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['module'];
      }

      if (in_array('--only-application', $args)) {
        if (isset($args['module']))
          throw new Exception("You cannot use --only-application and also define a module. Please specify only one of them.");

        $onlyApp = true;
      }

      if (DB_TRANSACTIONAL == 'on')
        DbConnections::retrieve('main')->startTransaction();

      try {
        $counter = 0;
        // Rollback migrations from Main Application:
        if (empty($module))
          $this->rollbackAppMigrations($counter, $limit ?? null);

        // Rollback migrations from Modules:
        if (empty($onlyApp))
          foreach (ModLoader::listMigrations($module ?? null) as $modMigrations)
            $this->rollbackModuleMigrations(
              $modMigrations,
              $counter,
              $limit ?? null
            );
        if (DB_TRANSACTIONAL == 'on')
          DbConnections::retrieve('main')->commitTransaction();
      } catch (Exception $exc) {
        if (DB_TRANSACTIONAL == 'on')
          DbConnections::retrieve('main')->rollbackTransaction();

        throw $exc;
      }
    });
  }

  private function applyMigration($fpath)
  {
    if ($this->alreadyApplied($fpath)) return;

    if (DB_TRANSACTIONAL == 'on')
      DbConnections::retrieve('main')->startTransaction();

    $sepIdx = strpos(basename($fpath), '_');
    $mName = substr(basename($fpath), $sepIdx + 1, strrpos(basename($fpath), '.') - $sepIdx - 1);
    $mName = str_replace('-', ' ', $mName);
    Utils::printLn("* Applying migration: '" . ucwords($mName) . "'");

    try {
      $mobj = ObjLoader::load($fpath);
      $mobj->apply();
      $operations = $mobj->info()->operations;

      if (empty($operations)) return;

      // Save the migration key in the database:
      $migration = $this->getDao('SPLITPHP_MIGRATION')
        ->insert([
          'date_exec' => date('Y-m-d H:i:s'),
          'filepath' => $fpath,
          'mkey' => hash('sha256', file_get_contents($fpath))
        ]);

      // Handle operations:
      foreach ($operations as $o) {
        $this->obtainUpAndDown($o);

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

      if (DB_TRANSACTIONAL == 'on')
        DbConnections::retrieve('main')->commitTransaction();
    } catch (Exception $exc) {
      var_dump($exc);
      if (DB_TRANSACTIONAL == 'on')
        DbConnections::retrieve('main')->rollbackTransaction();

      throw $exc;
    }
  }

  private function alreadyApplied($fpath)
  {
    $mkey = hash('sha256', file_get_contents($fpath));

    return !empty($this->getDao('SPLITPHP_MIGRATION')
      ->filter('mkey')->equalsTo($mkey)
      ->first());
  }

  private function obtainUpAndDown(&$operation)
  {
    print_r(Dbmetadata::listTables());
    $tbInfo = $operation->table->info();
    // Drop Table:
    if ($tbInfo->dropFlag) {
      $operation->up = $this->dropTableOperation($tbInfo);
      // Obtain the table creation operation for down:
      $operation->down = $this->createTableOperation($this->obtainPreviousTableState($tbInfo->name));
    }
    // Alter Table:
    elseif (in_array($tbInfo->name, Dbmetadata::listTables())) {
      $prevTbInfo = $this->obtainPreviousTableState($tbInfo->name);

      $sqlUp = $this->sqlBuilder->alter(
        tbName: $tbInfo->name
      );

      $sqlDown = clone $this->sqlBuilder;

      // Handle columns: 
      if (!empty($tbInfo->columns)) {
        $this->handleColumns($sqlUp, $sqlDown, $tbInfo->columns, $prevTbInfo);
      }

      // Handle indexes: 
      if (!empty($tbInfo->indexes)) {
        $this->handleIndexes($sqlUp, $sqlDown, $tbInfo->indexes, $prevTbInfo);
      }

      // Handle foreign keys:
      if (!empty($tbInfo->foreignKeys)) {
        $this->handleForeignKeys($sqlUp, $sqlDown, $tbInfo, $prevTbInfo);
      }

      $operation->up = $sqlUp->output(true);
      $operation->down = $sqlDown->output(true);
    }
    // Create Table: 
    else {
      // If the table does not exist, we create it.
      $operation->up = $this->createTableOperation($tbInfo);
      $operation->down = $this->dropTableOperation($tbInfo);
      return;
    }
  }

  private function createTableOperation($tbInfo)
  {
    $sqlBuilder = $this->sqlBuilder;
    $autoIncrements = [];

    $columns = [];
    foreach ($tbInfo->columns as $colBlueprint) {
      $col = $colBlueprint->info();

      if ($col->autoIncrementFlag) {
        $autoIncrements[] = $col->name;
      }

      $columns[] = [
        'name' => $col->name,
        'type' => $col->type,
        'length' => $col->length,
        'nullable' => $col->nullableFlag,
        'defaultValue' => $col->defaultValue,
        'charset' => $col->charset,
        'collation' => $col->collation,
      ];
    }

    $sqlBuilder->create(
      tbName: $tbInfo->name,
      columns: $columns,
      charset: $tbInfo->charset,
      collation: $tbInfo->collation
    );

    // Create SQL to apply indexes:
    if (!empty($tbInfo->indexes)) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->name
        );

      foreach ($tbInfo->indexes as $idxBlueprint) {
        $idx = $idxBlueprint->info();

        $sqlBuilder->addIndex(
          name: $idx->name,
          type: $idx->type,
          columns: $idx->columns
        );
      }
    }

    // Create SQL to apply auto increment:
    if (!empty($autoIncrements)) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->name
        );
      foreach ($autoIncrements as $colName) {
        $sqlBuilder->columnAutoIncrement(
          columnName: $colName
        );
      }
    }

    // Create SQL to apply foreign keys:
    if (!empty($tbInfo->foreignKeys)) {
      $sqlBuilder
        ->alter(
          tbName: $tbInfo->name
        );

      foreach ($tbInfo->foreignKeys as $fkBlueprint) {
        $fk = $fkBlueprint->info();

        $sqlBuilder->addConstraint(
          localColumns: $fk->columns,
          referencedTable: $fk->referencedTable,
          referencedColumns: $fk->referencedColumns,
          onUpdateAction: $fk->onUpdateAction,
          onDeleteAction: $fk->onDeleteAction
        );
      }
    }

    return $sqlBuilder->output(true);
  }

  private function dropTableOperation($tbInfo)
  {
    return $this->sqlBuilder->dropTable(
      tbName: $tbInfo->name
    )->output(true);
  }

  private function obtainPreviousTableState($tbName)
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
    $tbmetadata = Dbmetadata::tbInfo($tbName);

    $tbInfo = new TableBlueprint(
      name: $tbmetadata->table,
      charset: $tbmetadata->charset,
      collation: $tbmetadata->collation
    );

    // Set table's columns:
    $this->setPreviousTableColumns($tbmetadata, $tbInfo);

    // Set table's indexes:
    $this->setPreviousTableIndexes($tbmetadata, $tbInfo);

    // Set table's foreign keys:
    $this->setPreviousTableForeignKeys($tbmetadata, $tbInfo);

    return $tbInfo->info();
  }

  private function prepareDefaultVal($val)
  {
    if ($val === 'CURRENT_TIMESTAMP') return new SqlExpression('CURRENT_TIMESTAMP');

    elseif ($val === 'NULL') return null;

    elseif (is_string($val) && !is_numeric($val))
      return "'{$val}'";

    else return $val;
  }

  private function setPreviousTableColumns($tbmetadata, &$tbInfo)
  {
    foreach ($tbmetadata->columns as $col) {
      $colInfo = $tbInfo->Column(
        name: $col->Field,
        type: $col->Datatype,
        length: $col->Length
      );

      if (!is_null($col->Charset))
        $colInfo->setCharset($col->Charset);

      if (!is_null($col->Collation))
        $colInfo->setCollation($col->Collation);

      if ('YES' === $col->Null)
        $colInfo->nullable();

      if ($col->Extra === 'auto_increment')
        $colInfo->autoIncrement();

      if (!empty($col->Default))
        $colInfo->setDefaultValue($this->prepareDefaultVal($col->Default));
    }
  }

  private function setPreviousTableIndexes($tbmetadata, &$tbInfo)
  {
    if (empty($tbmetadata->indexes)) return;

    foreach ($tbmetadata->indexes as $idx) {
      $idxInfo = $tbInfo->Index(
        name: $idx->name,
        type: $idx->type
      )
        ->setColumns(array_map(function ($c) {
          return $c->column_name;
        }, $idx->columns));
    }
  }

  private function setPreviousTableForeignKeys($tbmetadata, &$tbInfo)
  {
    if (empty($tbmetadata->relatedTo)) return;

    $fks = [];
    foreach ($tbmetadata->relatedTo as $fk) {
      if (!array_key_exists($fk->CONSTRAINT_NAME, $fks)) {
        $fks[$fk->CONSTRAINT_NAME] = (object)[
          'referenced_table' => $fk->REFERENCED_TABLE_NAME,
          'on_update_action' => $fk->UPDATE_RULE,
          'on_delete_action' => $fk->DELETE_RULE,
          'columns' => [],
          'referenced_columns' => []
        ];
      }

      $fks[$fk->CONSTRAINT_NAME]->columns[] = $fk->COLUMN_NAME;
      $fks[$fk->CONSTRAINT_NAME]->referenced_columns[] = $fk->REFERENCED_COLUMN_NAME;
    }

    foreach ($fks as $fk) {
      $fkInfo = $tbInfo->ForeignKey($fk->columns)
        ->references($fk->referenced_columns)
        ->atTable($fk->referenced_table);

      if (!is_null($fk->on_update_action))
        $fkInfo->onUpdateAction($fk->on_update_action);

      if (!is_null($fk->on_delete_action))
        $fkInfo->onDeleteAction($fk->on_delete_action);
    }
  }

  private function handleColumns(&$sqlUp, &$sqlDown, array $columns, $prevTbInfo)
  {
    $hasColumn = $prevTbInfo->hasColumn;
    foreach ($columns as $colBlueprint) {
      $col = $colBlueprint->info();
      // Drop column:
      if ($col->dropFlag && $hasColumn($col->name)) {
        $sqlUp->dropColumn($col->name);

        $prevCol = $prevTbInfo->columns[$col->name]->info();
        $sqlDown->addColumn(
          name: $prevCol->name,
          type: $prevCol->type,
          length: $prevCol->length,
          nullable: $prevCol->nullableFlag,
          unsigned: $prevCol->unsignedFlag,
          defaultValue: $prevCol->defaultValue,
          autoIncrement: $prevCol->autoIncrementFlag
        );
      }
      // Change column:
      elseif ($hasColumn($col->name)) {
        $sqlUp->changeColumn(
          name: $col->name,
          type: $col->type,
          length: $col->length,
          nullable: $col->nullableFlag,
          unsigned: $col->unsignedFlag,
          defaultValue: $col->defaultValue,
          autoIncrement: $col->autoIncrementFlag
        );

        $prevCol = $prevTbInfo->columns[$col->name]->info();
        $sqlDown->changeColumn(
          name: $prevCol->name,
          type: $prevCol->type,
          length: $prevCol->length,
          nullable: $prevCol->nullableFlag,
          unsigned: $prevCol->unsignedFlag,
          defaultValue: $prevCol->defaultValue,
          autoIncrement: $prevCol->autoIncrementFlag
        );
      }
      // Add column:
      else {
        $sqlUp->addColumn(
          name: $col->name,
          type: $col->type,
          length: $col->length,
          nullable: $col->nullableFlag,
          unsigned: $col->unsignedFlag,
          defaultValue: $col->defaultValue,
          autoIncrement: $col->autoIncrementFlag
        );

        $sqlDown->dropColumn($col->name);
      }
    }
  }

  private function handleIndexes(&$sqlUp, &$sqlDown, array $indexes, $prevTbInfo)
  {
    foreach ($indexes as $idxBlueprint) {
      $idx = $idxBlueprint->info();
      // Drop index:
      if ($idx->dropFlag && $prevTbInfo->hasIndex($idx->name)) {
        $sqlUp->dropIndex($idx->name);

        $prevIdx = $prevTbInfo->columns[$idx->name]->info();
        $sqlDown->addIndex(
          name: $prevIdx->name,
          type: $prevIdx->type,
          columns: $prevIdx->columns
        );
      }
      // Change index:
      elseif ($prevTbInfo->hasIndex($idx->name)) {
        $sqlUp->dropIndex($idx->name);
        $sqlUp->addIndex(
          name: $idx->name,
          type: $idx->type,
          columns: $idx->columns
        );

        $prevIdx = $prevTbInfo->columns[$idx->name]->info();
        $sqlDown->dropIndex($prevIdx->name);
        $sqlDown->addIndex(
          name: $prevIdx->name,
          type: $prevIdx->type,
          columns: $prevIdx->columns
        );
      }
      // Add index:
      else {
        $sqlUp->addIndex(
          name: $idx->name,
          type: $idx->type,
          columns: $idx->columns
        );

        $sqlDown->dropIndex($idx->name);
      }
    }
  }

  private function handleForeignKeys(&$sqlUp, &$sqlDown, $tbInfo, $prevTbInfo)
  {
    foreach ($tbInfo->foreignKeys as $fkBlueprint) {
      $fk = $fkBlueprint->info();
      // Drop foreign key:
      if ($fk->dropFlag && $prevTbInfo->hasForeignKey($fk->name)) {
        $sqlUp->dropConstraint($fk->name);

        $prevFk = $prevTbInfo->columns[$fk->name]->info();
        $sqlDown->addConstraint(
          localColumns: $prevFk->columns,
          referencedTable: $prevFk->referencedTable,
          referencedColumns: $prevFk->referencedColumns,
          onUpdateAction: $prevFk->onUpdateAction,
          onDeleteAction: $prevFk->onDeleteAction
        );
      }
      // Change foreign key:
      elseif ($prevTbInfo->hasForeignKey($fk->name)) {
        $sqlUp->dropConstraint($fk->name);
        $sqlUp->addConstraint(
          localColumns: $fk->columns,
          referencedTable: $fk->referencedTable,
          referencedColumns: $fk->referencedColumns,
          onUpdateAction: $fk->onUpdateAction,
          onDeleteAction: $fk->onDeleteAction
        );

        $prevFk = $prevTbInfo->columns[$fk->name]->info();
        $sqlDown->dropConstraint($prevFk->name);
        $sqlDown->addConstraint(
          localColumns: $prevFk->columns,
          referencedTable: $prevFk->referencedTable,
          referencedColumns: $prevFk->referencedColumns,
          onUpdateAction: $prevFk->onUpdateAction,
          onDeleteAction: $prevFk->onDeleteAction
        );
      }
      // Add foreign key:
      else {
        $sqlUp->addConstraint(
          localColumns: $fk->columns,
          referencedTable: $fk->referencedTable,
          referencedColumns: $fk->referencedColumns,
          onUpdateAction: $fk->onUpdateAction,
          onDeleteAction: $fk->onDeleteAction
        );

        $sqlDown->dropConstraint($fk->name);
      }
    }
  }

  private function rollbackAppMigrations(int &$counter, ?int $limit = null)
  {
    $execControl = [];
    $this->getDao('SPLITPHP_MIGRATION')
      ->fetch(
        function ($operation) use (&$counter, $limit, &$execControl) {
          if (isset($limit) && $counter >= $limit) {
            Utils::printLn("Limit reached, stopping rollback.");
            return false;
          }

          $opDown = $this->sqlBuilder
            ->write($operation->down, overwrite: true)
            ->output(true);

          // Perform the operation:
          DbConnections::retrieve('main')->runMany($opDown);

          if (!in_array($operation->id, $execControl)) {
            $execControl[] = $operation->id;
            $sepIdx = strpos(basename($operation->filepath), '_');
            $mName = substr(basename($operation->filepath), $sepIdx + 1, strrpos(basename($operation->filepath), '.') - $sepIdx - 1);
            $mName = str_replace('-', ' ', $mName);
            $mName = ucwords($mName);
            Utils::printLn("* Rolling back migration: '" . $mName . "' applied at " . $operation->date_exec);
          }

          $counter = count($execControl);
        },
        "SELECT 
            m.id AS id,
            m.filepath AS filepath,
            m.date_exec AS date_exec,
            o.up AS up,
            o.down AS down
          FROM SPLITPHP_MIGRATION m
          JOIN SPLITPHP_MIGRATION_OPERATION o ON m.id = o.id_migration
          WHERE m.filepath LIKE '%" . MAINAPP_PATH . "%'
          ORDER BY m.date_exec DESC"
      );

    // Delete executed migrations:
    if (!empty($execControl)) {
      $this->getDao('SPLITPHP_MIGRATION')
        ->filter('id')->in($execControl)
        ->delete();
    }
  }

  private function rollbackModuleMigrations(array $modMigrations, int &$counter, ?int $limit = null)
  {
    $execControl = [];
    foreach ($modMigrations as $fpath) {
      $this->getDao('SPLITPHP_MIGRATION')
        ->fetch(
          function ($operation) use (&$counter, $limit, &$execControl) {
            if (isset($limit) && $counter > $limit) {
              Utils::printLn("Limit reached, stopping rollback.");
              return false;
            }

            if (!in_array($operation->id, $execControl)) {
              $execControl[] = $operation->id;

              $sepIdx = strpos(basename($operation->filepath), '_');
              $mName = substr(basename($operation->filepath), $sepIdx + 1, strrpos(basename($operation->filepath), '.') - $sepIdx - 1);
              $mName = str_replace('-', ' ', $mName);
              $mName = ucwords($mName);
              Utils::printLn("* Rolling back migration: '" . $mName . "' applied at " . $operation->date_exec);
            }

            $opDown = $this->sqlBuilder
              ->write($operation->down, overwrite: true)
              ->output(true);

            // Perform the operation:
            DbConnections::retrieve('main')->runMany($opDown);

            $counter = count($execControl);
          },
          "SELECT 
              m.id AS id,
              m.filepath AS filepath,
              m.date_exec AS date_exec,
              o.up AS up,
              o.down AS down
            FROM SPLITPHP_MIGRATION m
            JOIN SPLITPHP_MIGRATION_OPERATION o ON m.id = o.id_migration
            WHERE m.filepath = '{$fpath}'
            ORDER BY m.date_exec DESC"
        );
    }

    // Delete executed migrations:
    if (!empty($execControl)) {
      $this->getDao('SPLITPHP_MIGRATION')
        ->filter('id')->in($execControl)
        ->delete();
    }
  }
}
