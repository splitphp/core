<?php

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\AppLoader;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Database\DbConnections;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Database\SqlExpression;
use SplitPHP\DbMigrations\TableBlueprint;
use SplitPHP\ModLoader;

class Migrations extends Cli
{
  private $sqlBuilder;
  private $existingTables = [];

  public function init()
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform migrations.");

    if (DbMetadata::hasUserAccessToInformationSchema() === false)
      throw new Exception("Database main user does not have access/permission to information_schema. Migrations cannot be performed.");

    require CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "class.sql.php");

    Dbmetadata::createMigrationControl();

    $this->addCommand('apply', function () {
      // Apply migrations from Modules:
      foreach (ModLoader::listMigrations() as $module)
        foreach ($module as $fpath)
          $this->applyMigration($fpath);

      // Apply migrations from Main Application:
      foreach (AppLoader::listMigrations() as $fpath)
        $this->applyMigration($fpath);
    });
  }

  private function applyMigration($fpath)
  {
    if ($this->alreadyApplied($fpath)) return;

    if (DB_TRANSACTIONAL == 'on')
      DbConnections::retrieve('main')->startTrasaction();

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
    $tbInfo = $operation->table->info();
    // Drop Table:
    if ($tbInfo->dropFlag) {
      $operation->up = $this->dropTableOperation($tbInfo);
      // Obtain the table creation operation for down:
      $operation->down = $this->createTableOperation($this->obtainPreviousTableState($tbInfo->name));
    }
    // Alter Table:
    elseif ($this->tableExists($tbInfo->name)) {
      $prevTbInfo = $this->obtainPreviousTableState($tbInfo->name);

      $sqlUp = $this->sqlBuilder->alter(
        tbName: $tbInfo->name
      );

      $sqlDown = $this->sqlBuilder->alter(
        tbName: $tbInfo->name
      );

      // Handle columns: 
      if (!empty($tbInfo->columns)) {
        $this->handleColumns($sqlUp, $tbInfo->columns, $prevTbInfo);
      }

      // Handle indexes: 
      if (!empty($tbInfo->indexes)) {
        $this->handleIndexes($sqlUp, $tbInfo->indexes, $prevTbInfo);
      }

      // Handle foreign keys:
      if (!empty($tbInfo->foreignKeys)) {
        $this->handleForeignKeys($sqlUp, $tbInfo, $prevTbInfo);
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

  private function tableExists($tbName)
  {
    if (empty($this->existingTables)) {
      $this->existingTables = Dbmetadata::listTables();
    }

    return in_array($tbName, $this->existingTables);
  }

  private function createTableOperation($tbInfo)
  {
    $sqlBuilder = $this->sqlBuilder;

    $columns = [];
    foreach ($tbInfo->columns as $colBlueprint) {
      $col = $colBlueprint->info();

      $columns[] = [
        'name' => $col->name,
        'type' => $col->type,
        'length' => $col->length,
        'nullable' => $col->nullableFlag,
        'defaultValue' => $col->defaultValue,
        'charset' => $col->charset,
        'collation' => $col->collation,
        'autoIncrement' => $col->autoIncrementFlag
      ];
    }

    $sqlBuilder->create(
      tbName: $tbInfo->name,
      columns: $columns,
      charset: $tbInfo->charset,
      collation: $tbInfo->collation
    );

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
    if ($val === 'NULL') return null;
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

  private function handleColumns(&$sql, array $columns, $prevTbInfo)
  {
    foreach ($columns as $colBlueprint) {
      $col = $colBlueprint->info();
      // Drop column:
      if ($col->dropFlag && $prevTbInfo->hasColumn($col->name)) {
        $sql->dropColumn($col->name);
      }
      // Change column:
      elseif ($prevTbInfo->hasColumn($col->name)) {
        $sql->changeColumn(
          name: $col->name,
          type: $col->type,
          length: $col->length,
          nullable: $col->nullableFlag,
          unsigned: $col->unsignedFlag,
          defaultValue: $col->defaultValue,
          charset: $col->charset,
          collation: $col->collation,
          autoIncrement: $col->autoIncrementFlag
        );
      }
      // Add column:
      else {
        $sql->addColumn(
          name: $col->name,
          type: $col->type,
          length: $col->length,
          nullable: $col->nullableFlag,
          unsigned: $col->unsignedFlag,
          defaultValue: $col->defaultValue,
          charset: $col->charset,
          collation: $col->collation,
          autoIncrement: $col->autoIncrementFlag
        );
      }
    }
  }

  private function handleIndexes(&$sql, array $indexes, $prevTbInfo)
  {
    foreach ($indexes as $idxBlueprint) {
      $idx = $idxBlueprint->info();
      // Drop index:
      if ($idx->dropFlag && $prevTbInfo->hasIndex($idx->name)) {
        $sql->dropIndex($idx->name);
      }
      // Change index:
      elseif ($prevTbInfo->hasIndex($idx->name)) {
        $sql->dropIndex($idx->name);
        $sql->addIndex(
          name: $idx->name,
          type: $idx->type,
          columns: $idx->columns
        );
      }
      // Add index:
      else {
        $sql->addIndex(
          name: $idx->name,
          type: $idx->type,
          columns: $idx->columns
        );
      }
    }
  }

  private function handleForeignKeys(&$sql, $tbInfo, $prevTbInfo)
  {
    foreach ($tbInfo->foreignKeys as $fkBlueprint) {
      $fk = $fkBlueprint->info();
      // Drop foreign key:
      if ($fk->dropFlag && $prevTbInfo->hasForeignKey($fk->name)) {
        $sql->dropConstraint($fk->name);
      }
      // Change foreign key:
      elseif ($prevTbInfo->hasForeignKey($fk->name)) {
        $sql->dropConstraint($fk->name);
        $sql->addConstraint(
          localColumns: $fk->columns,
          referencedTable: $fk->referencedTable,
          referencedColumns: $fk->referencedColumns,
          onUpdateAction: $fk->onUpdateAction,
          onDeleteAction: $fk->onDeleteAction
        );
      }
      // Add foreign key:
      else {
        $sql->addConstraint(
          localColumns: $fk->columns,
          referencedTable: $fk->referencedTable,
          referencedColumns: $fk->referencedColumns,
          onUpdateAction: $fk->onUpdateAction,
          onDeleteAction: $fk->onDeleteAction
        );
      }
    }
  }
}
