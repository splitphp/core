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

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\AppLoader;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Utils;
use SplitPHP\Database\Dao;
use SplitPHP\Database\DbConnections;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\DbMigrations\TableBlueprint;
use SplitPHP\Helpers;
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

    require_once CORE_PATH . '/dbmanager/class.migration.php';

    DbMetadata::checkUserRequiredAccess('Migrations', true);
    Dbmetadata::createMigrationControl();

    // Apply Command:
    $this->addCommand('apply', function ($args) {
      if (isset($args['--limit'])) {
        if (!is_numeric($args['--limit']) || $args['--limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['--limit'];
      }

      if (isset($args['--module'])) {
        if (!is_string($args['--module']) || is_numeric($args['--module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['--module'];
      }

      // List all migrations to be applied:
      $migrations = [];
      foreach (ModLoader::listMigrations($module ?? null) as $modMigrations) {
        $migrations = [...$migrations, ...$modMigrations];
      }

      if (empty($module)) {
        $migrations = [...$migrations, ...AppLoader::listMigrations()];

        usort($migrations, function ($a, $b) {
          // Extract just the filename (no directory)
          $aName = basename($a->filepath);
          $bName = basename($b->filepath);

          // Find position of first underscore
          $posA = strpos($aName, '_');
          $posB = strpos($bName, '_');

          $tsA = (int) substr($aName, 0, $posA);
          $tsB = (int) substr($bName, 0, $posB);

          // Numeric comparison (PHP 7+ spaceship operator)
          return $tsA <=> $tsB;
        });
      }

      $counter = 0;
      // Apply all listed migrations:
      foreach ($migrations as $mdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("Limit reached, stopping applying migrations.");
          return;
        }
        $this->applyMigration($mdata, $counter);
      }
    });

    // Rollback Command:
    $this->addCommand('rollback', function ($args) {
      $limit = null;
      if (isset($args['--limit'])) {
        if (!is_numeric($args['--limit']) || $args['--limit'] < 1)
          throw new Exception("Invalid limit value. It must be a positive numeric value.");

        $limit = (int)$args['--limit'];
      }

      $module = null;
      if (isset($args['--module'])) {
        if (!is_string($args['--module']) || is_numeric($args['--module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['--module'];
      }

      $moduleFilter = '';
      $dao = $this->getDao('_SPLITPHP_MIGRATION');

      if (!empty($module)) {
        $dao = $dao->filter('module')->equalsTo($module);
        $moduleFilter = "WHERE m.module = ?module?";
      }

      $sql = "SELECT 
            m.id AS id,
            m.name AS name,
            m.filepath AS filepath,
            m.date_exec AS date_exec,
            m.module AS module,
            o.down AS down
          FROM _SPLITPHP_MIGRATION m
          JOIN _SPLITPHP_MIGRATION_OPERATION o ON m.id = o.id_migration
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

          Utils::printLn(">>" . ($operation->module ? " [Mod: '{$operation->module}']" : "") . " Rolling back migration: '" . $operation->name . "' applied at " . $operation->date_exec . ":\n");

          Dao::flush();
        }

        $opDown = $this->sqlBuilder
          ->write($operation->down, overwrite: true)
          ->output(true);

        Utils::printLn("\"{$operation->down}\"");
        Utils::printLn("--------------------------------------------------------");
        Utils::printLn();

        // Perform the operation:
        DbConnections::retrieve('main')->runMany($opDown);

        $counter = count($execControl);

        $this->getDao('_SPLITPHP_MIGRATION')
          ->filter('id')->equalsTo($operation->id)
          ->delete();
      }, $sql);
    });

    // Status Command:
    $this->addCommand('status', function ($args) {
      $module = null;
      if (isset($args['--module'])) {
        if (!is_string($args['--module']) || is_numeric($args['--module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['--module'];
      }

      // List all migrations:
      $all = [];
      foreach (ModLoader::listMigrations($module ?? null) as $modMigrations) {
        $all = [...$all, ...$modMigrations];
      }

      if (empty($module)) {
        $all = [...$all, ...AppLoader::listMigrations()];
      }

      // List applied migrations:
      $dao = $this->getDao('_SPLITPHP_MIGRATION');
      if (!empty($module)) {
        $dao = $dao->filter('module')->equalsTo($module);
      }

      $applied = [];
      $dao->fetch(function ($mObj) use (&$applied) {
        $applied[$mObj->filepath] = $mObj;
      });

      $this->showStatusResults($all, $applied, in_array('--no-color', $args));
    });

    // Help Command:
    $this->addCommand('help', function () {
      Utils::printLn("Usage:");
      Utils::printLn("  migrations:[option] [...parameters]");
      Utils::printLn();

      Utils::printLn("AVAILABLE OPTIONS:");
      Utils::printLn("  apply       [--limit=<number>] [--module=<name>]   Apply pending migrations to the database.");
      Utils::printLn("                                                     Already applied migrations will be skipped.");
      Utils::printLn();
      Utils::printLn("  rollback    [--limit=<number>] [--module=<name>]   Roll back previously applied migrations.");
      Utils::printLn();
      Utils::printLn("  status      [--module=<name>] [--no-color]         Show the current status of migrations.");
      Utils::printLn("                                                     Displays applied and pending migrations per module.");
      Utils::printLn();
      Utils::printLn("  help                                               Show this help message.");
      Utils::printLn();

      Utils::printLn("PARAMETERS:");
      Utils::printLn("  --limit=<number>     Limit the number of migrations to apply or roll back.");
      Utils::printLn("                       If omitted, applies/rollbacks all available migrations.");
      Utils::printLn("  --module=<name>      Specify the module whose migrations should be applied or rolled back.");
      Utils::printLn("                       If omitted, all available migrations are considered.");
      Utils::printLn("  --no-color           Disable colored output. Useful when redirecting output to files or");
      Utils::printLn("                       running in terminals that do not support ANSI colors.");
    });
  }

  private function showStatusResults(array $all, array $applied, $noColor = false)
  {
    function supportsAnsi()
    {
      return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    function coloredStatus($status, $noColor)
    {
      static $useColor = null;

      if ($useColor === null) {
        $useColor = supportsAnsi();
      }

      $cleanStatus = trim($status);

      if (!$useColor || $noColor) {
        return $status; // Sem cor, terminal sem suporte
      }

      if ($cleanStatus === 'Applied') {
        return "\033[32m{$status}\033[0m"; // Verde
      } else {
        return "\033[33m{$status}\033[0m"; // Amarelo
      }
    }

    function padStatus($status, $length, $noColor)
    {
      // Primeiro faz o pad no texto puro
      $padded = str_pad($status, $length);
      // Depois aplica a cor
      return coloredStatus($padded, $noColor);
    }

    $columns = [
      '#' => 3,
      'Module' => 12,
      'Migration' => 50,
      'Status' => 9,
      'Applied At' => 19,
    ];
    Utils::printLn(">> Migrations Status Summary:");
    Utils::printLn();
    Utils::printLn("* Total migrations:       " . count($all));
    Utils::printLn("* Total applied:          " . count($applied));
    Utils::printLn("* Total pending:          " . count($all) - count($applied));
    Utils::printLn();
    Utils::printLn();

    Utils::printLn(">> Migrations Status Details:");
    Utils::printLn();

    // Print the header:
    Utils::printLn(Utils::buildSeparator($columns));
    echo '| '
      . Utils::pad('#', 3) . ' | '
      . Utils::pad('Module', 12) . ' | '
      . Utils::pad('Migration', 50) . ' | '
      . Utils::pad('Status', 9) . ' | '
      . Utils::pad('Applied At', 19) . ' |' . PHP_EOL;
    Utils::printLn(Utils::buildSeparator($columns));

    // Print each migration status:
    foreach ($all as $idx => $mdata) {
      echo '| '
        . Utils::pad($idx + 1, 3) . ' | '
        . Utils::pad($mdata->module, 12) . ' | '
        . Utils::pad($mdata->name, 50) . ' | '
        . padStatus(isset($applied[$mdata->filepath]) ? "Applied" : "Pending", 9, $noColor) . ' | '
        . Utils::pad(($applied[$mdata->filepath]->date_exec ?? ""), 19) . ' |' . PHP_EOL;
    }
    Utils::printLn(Utils::buildSeparator($columns));
  }

  /**
   * Applies a migration by loading the migration object from the specified file path,
   * executing its apply method, and saving the operations in the database.
   *
   * @param string $fpath The file path of the migration to be applied.
   */
  private function applyMigration($mdata, &$counter)
  {
    $module = $mdata->module ?? null;

    if ($this->alreadyApplied($mdata->filepath)) return;

    Utils::printLn(">>" . ($module ? " [Mod: '{$module}']" : "") . " Applying migration: '{$mdata->name}':");
    Utils::printLn("--------------------------------------------------------");
    Utils::printLn();

    $mobj = ObjLoader::load($mdata->filepath);
    $mobj->apply();
    $operations = $mobj->getOperations();

    if (empty($operations)) return;

    // Save the migration key in the database:
    $migration = $this->getDao('_SPLITPHP_MIGRATION')
      ->insert([
        'name' => $mdata->name,
        'date_exec' => date('Y-m-d H:i:s'),
        'filepath' => $mdata->filepath,
        'mkey' => $mdata->mkey,
        'module' => $module
      ]);

    // Handle operations:
    foreach ($operations as $o) {
      $this->obtainUpAndDown($o);

      // Prepend pre-sql statements:
      if (!empty($o->presql)) {
        $o->up->preppend($o->presql);
        $o->down->preppend($o->presql);
      }

      // Append INSERT from seeds, if any:
      if ($o->type == 'table' && !empty($o->blueprint->seeds)) {
        foreach ($o->blueprint->seeds as $seed) {
          $upAndDown = $seed->obtainSql();
          $o->up->append($upAndDown->up);
          $o->down->append($upAndDown->down);
        }
      }

      // Append post-sql statements:
      if (!empty($o->postsql)) {
        $o->up->append($o->postsql);
        $o->down->append($o->postsql);
      }

      echo '"' . $o->up->sqlstring . "\"\n\n";

      // Perform the operation:
      DbConnections::retrieve('main')->runMany($o->up);

      // Save the operation in the database:
      $this->getDao('_SPLITPHP_MIGRATION_OPERATION')
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

    return !empty($this->getDao('_SPLITPHP_MIGRATION')
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
    switch ($operation->type) {
      case 'procedure':
        $this->obtainProcedureUpAndDown($operation);
        return;
      case 'table':
        $this->obtainTableUpAndDown($operation);
        return;
      default:
        throw new Exception("Unknown operation type: " . $operation->type);
    }
  }

  /**
   * Obtains the SQL statements for the "up" and "down" operations of a table migration.
   *
   * @param object $operation The migration operation object to be modified.
   */
  private function obtainTableUpAndDown(&$operation)
  {
    $blueprint = $operation->blueprint;
    $dbMapper = Helpers::DbMapper();

    // -> Drop Operation:
    if ($blueprint->isToDrop()) {
      $operation->up = $this->dropTableOperation($blueprint);
      $operation->down = $this->createTableOperation($dbMapper->tableBlueprint($blueprint->getName()));
    }

    // -> Alter Operation:
    elseif (in_array($blueprint->getName(), Dbmetadata::listTables())) {
      $currentTbInfo = $dbMapper->tableBlueprint($blueprint->getName());

      $sqlUp = $this->sqlBuilder->alter(
        tbName: $blueprint->getName()
      );

      $sqlDown = clone $this->sqlBuilder;

      // Handle columns: 
      if (!empty($blueprint->getColumns())) {
        $this->handleColumns($sqlUp, $sqlDown, $blueprint->getColumns(), $currentTbInfo);
      }

      // Handle indexes: 
      if (!empty($blueprint->getIndexes())) {
        $this->handleIndexes($sqlUp, $sqlDown, $blueprint->getIndexes(), $currentTbInfo);
      }

      // Handle foreign keys:
      if (!empty($blueprint->getForeignKeys())) {
        $this->handleForeignKeys($sqlUp, $sqlDown, $blueprint, $currentTbInfo);
      }

      $operation->up = $sqlUp->output(true);
      $operation->down = $sqlDown->output(true);
    }

    // -> Create Operation: 
    else {
      $operation->up = $this->createTableOperation($blueprint);
      $operation->down = $this->dropTableOperation($blueprint);
      return;
    }
  }

  /**
   * Obtains the SQL statements for the "up" and "down" operations of a procedure migration.
   *
   * @param object $operation The migration operation object to be modified.
   */
  private function obtainProcedureUpAndDown(&$operation)
  {
    $blueprint = $operation->blueprint;
    $dbMapper = Helpers::DbMapper();

    // -> Drop Operation:
    if ($blueprint->isToDrop() && in_array($blueprint->getName(), Dbmetadata::listProcedures())) {
      $operation->up = $this->sqlBuilder
        ->dropProcedure(name: $blueprint->getName())
        ->output(true);

      $currentState = $dbMapper->procedureBlueprint($blueprint->getName());
      $operation->down = $this->sqlBuilder
        ->createProcedure(
          name: $currentState->getName(),
          args: $currentState->getArgs(),
          output: $currentState->getOutput(),
          instructions: $currentState->getInstructions()
        )->output(true);
    }

    // -> Alter Operation:
    elseif (in_array($blueprint->getName(), Dbmetadata::listProcedures())) {
      $currentState = $dbMapper->procedureBlueprint($blueprint->getName());

      $sqlDown = clone $this->sqlBuilder;

      $sqlUp = $this->sqlBuilder
        ->dropProcedure(
          name: $currentState->getName(),
        )
        ->createProcedure(
          name: $blueprint->getName(),
          args: $blueprint->getArgs(),
          output: $blueprint->getOutput(),
          instructions: $blueprint->getInstructions()
        );

      // Set the "down" operation to the current state:
      $sqlDown
        ->dropProcedure(
          name: $blueprint->getName(),
        )
        ->createProcedure(
          name: $currentState->getName(),
          args: $currentState->getArgs(),
          output: $currentState->getOutput(),
          instructions: $currentState->getInstructions()
        );

      $operation->up = $sqlUp->output(true);
      $operation->down = $sqlDown->output(true);
    }

    // -> Create Operation:
    else {
      $operation->up = $this->sqlBuilder
        ->createProcedure(
          name: $blueprint->getName(),
          args: $blueprint->getArgs(),
          output: $blueprint->getOutput(),
          instructions: $blueprint->getInstructions()
        )->output(true);

      $operation->down = $this->sqlBuilder
        ->dropProcedure(name: $blueprint->getName())
        ->output(true);
    }
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
