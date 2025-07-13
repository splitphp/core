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
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\ModLoader;

/**
 * Class Migrations
 *
 * It is a CLI that handles database migrations, including status, applying and rolling back migrations.
 */
class Migrations extends Cli
{
  /**
   * @var Sql The SQL builder instance for generating SQL queries.
   */
  private $sqlBuilder;

  /**
   * @var string|null The name of the currently selected database.
   * This is used to track which database is currently being operated on.
   */
  private ?string $dbname = null;

  /**
   * @var array An array to keep track of databases that have been created during the migration process.
   * This is used to avoid creating the same database multiple times.
   */
  private array $createdDatabases = [];

  /**
   * Initializes the Migrations CLI, setting up the necessary commands and configurations.
   *
   * @throws Exception If the database connection is not enabled or if there are issues with the migration setup.
   */
  public function init(): void
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform migrations.");

    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . Database::getRdbmsName() . "/class.sql.php");

    require_once CORE_PATH . '/dbmanager/class.migration.php';

    Dbmetadata::checkUserRequiredAccess('Migrations', true);

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
      $migrations = $this->listMigrationsFromFiles($module ?? null);

      $counter = 0;
      Utils::printLn(">> Reading pending migrations...");
      Utils::printLn();
      // Apply all listed migrations:
      foreach ($migrations as $mdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("Limit reached, stopping applying migrations.");
          return;
        }
        $this->applyMigration($mdata, $counter);
      }
      Utils::printLn(">> Migrations applied successfully.");
      Utils::printLn();
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

      $migrations = $this->listMigrationsFromFiles($module ?? null);

      $counter = 0;
      // Apply all listed migrations:
      foreach ($migrations as $mdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("Limit reached, stopping applying migrations.");
          return;
        }
        $this->rollbackMigration($mdata, $counter);
      }
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
      $this->selectDatabase();
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

  /**
   * Displays the status of migrations, including total migrations, applied migrations,
   * and pending migrations, along with detailed information about each migration.
   *
   * @param array $all An array of all migration objects.
   * @param array $applied An array of applied migration objects.
   * @param bool $noColor Whether to disable colored output.
   */
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
   * @param object $mdata The migration data object containing the migration metadata.
   * @param int &$counter A reference to the counter that tracks the number of applied migrations.
   */
  private function applyMigration($mdata, &$counter)
  {
    $module = $mdata->module ?? null;

    $mobj = ObjLoader::load($mdata->filepath);
    $mobj->apply();

    $operations = $mobj->getOperations();
    if (empty($operations)) return;

    $customDb = $mobj->getSelectedDatabase();
    if ($dbIsChanged = !empty($customDb) && $customDb != Database::getName()) {
      $previousDb = Database::getName();
      Database::setName($customDb);
    }

    $this->selectDatabase();

    if (!$this->alreadyApplied($mdata->filepath)) {
      Utils::printLn("\033[33m>>" . ($module ? " [Mod: '{$module}']" : "") . " Applying migration: \033[32m'{$mdata->name}'\033[0m:");
      Utils::printLn("--------------------------------------------------------");
      Utils::printLn();

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
        $sql = $o->blueprint->obtainSQL();
        $o->up = $sql->up;
        $o->down = $sql->down;

        // Prepend pre-sql statements:
        if (!empty($o->presql)) {
          $o->up->prepend($o->presql);
          $o->down->prepend($o->presql);
        }

        // Append post-sql statements:
        if (!empty($o->postsql)) {
          $o->up->append($o->postsql);
          $o->down->append($o->postsql);
        }

        echo '"' . $o->up->sqlstring . "\"\n\n";

        // Perform the operation:
        Database::getCnn('main')->runMany($o->up);

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

    if ($dbIsChanged) {
      // Restore the previous database connection if it was changed:
      Database::setName($previousDb);
      $this->selectDatabase();
    }
  }

  /**
   * Rolls back a migration by loading the migration object from the specified file path,
   * executing its down method, and removing the migration record from the database.
   *
   * @param object $mdata The migration data object containing the migration metadata.
   * @param int &$counter A reference to the counter that tracks the number of rolled back migrations.
   */
  private function rollbackMigration($mdata, int &$counter)
  {
    $module = $mdata->module ?? null;

    Utils::printLn(">>" . ($module ? " [Mod: '{$module}']" : "") . " Rolling back migration: '{$mdata->name}':");
    Utils::printLn("--------------------------------------------------------");
    Utils::printLn();

    $mobj = ObjLoader::load($mdata->filepath);
    $mobj->apply();

    $operations = $mobj->getOperations();
    if (empty($operations)) return;

    $customDb = $mobj->getSelectedDatabase();
    if (!empty($customDb) && $customDb != Database::getName())
      Database::setName($customDb);

    $this->selectDatabase();

    if (!$this->alreadyApplied($mdata->filepath)) return;

    // Handle operations:
    foreach ($operations as $o) {
      $sql = $o->blueprint->obtainSQL();
      $o->down = $sql->down;

      // Prepend pre-sql statements:
      if (!empty($o->presql)) $o->down->prepend($o->presql);


      // Append post-sql statements:
      if (!empty($o->postsql)) $o->down->append($o->postsql);

      echo '"' . $o->down->sqlstring . "\"\n\n";

      // Perform the operation:
      Database::getCnn('main')->runMany($o->down);
    }

    $this->getDao('_SPLITPHP_MIGRATION')
      ->filter('mkey')->equalsTo($mdata->mkey)
      ->delete();

    Dao::flush();
    $counter++;
  }

  /**
   * Lists all migrations from the specified module or all, if no module is specified.
   *
   * @param string|null $module The name of the module to list migrations from, or null for all migrations.
   * @return array An array of migration objects.
   */
  private function listMigrationsFromFiles($module): array
  {
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

    return $migrations;
  }

  /**
   * Checks if a migration has already been applied by comparing the file's content hash
   * with the stored migration keys in the database.
   *
   * @param string $fpath The file path of the migration to check.
   * @return bool Returns true if the migration has already been applied, false otherwise.
   */
  private function alreadyApplied($fpath): bool
  {
    $mkey = hash('sha256', file_get_contents($fpath));

    return !empty($this->getDao('_SPLITPHP_MIGRATION')
      ->filter('mkey')->equalsTo($mkey)
      ->first());
  }

  /**
   * Selects a specific database for the current connection.
   *
   * @throws Exception If the database cannot be selected.
   */
  private function selectDatabase(): void
  {
    // Check if the database exists, if not, create it:
    if ($this->dbname != Database::getName() && !in_array(Database::getName(), $this->createdDatabases)) {
      $sql = $this->sqlBuilder
        ->createDatabase(Database::getName())
        ->output(true);

      Database::getCnn('main')->runMany($sql);
      Dbmetadata::createMigrationControl();
      $this->createdDatabases[] = Database::getName();
    }

    $this->dbname = Database::getName();
  }
}
