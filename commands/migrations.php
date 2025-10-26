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
use SplitPHP\Database\Sql;
use SplitPHP\Database\Dao;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\ModLoader;
use Throwable;

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
  private Sql $sqlBuilder;

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
      Utils::printLn("\033[33m>> Reading pending migrations...\033[0m");
      Utils::printLn();
      // Apply all listed migrations:
      foreach ($migrations as $mdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("\033[33m>> HALT: Limit reached, stopping applying migrations.\033[0m");
          return;
        }
        $this->applyMigration($mdata, $counter);
      }
      Utils::printLn("\033[33m>> Migrations applied successfully.\033[0m");
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

      Utils::printLn("\033[33m>> Reading applied migrations...\033[0m");
      echo PHP_EOL;

      $filters = [
        '$sort_by' => 1,
        '$sort_direction' => 'DESC'
      ];
      if (!empty($module)) {
        $filters['module'] = $module;
      }
      $counter = 0;
      $migrations = $this->getDao('_SPLITPHP_MIGRATION')
        ->bindParams($filters)
        ->fetch(
          function ($mobj) use ($limit, &$counter) {
            if (isset($limit) && $counter >= $limit) {
              Utils::printLn("\033[33m>> HALT: Limit reached, stopping migrations rollback.\033[0m");
              return false;
            }
            $this->rollbackMigration($mobj, $counter);
          },
          "SELECT 
              id,
              name, 
              date_exec, 
              filepath, 
              mkey, 
              module
            FROM `_SPLITPHP_MIGRATION`"
        );

      if (count($migrations) == $counter)
        Utils::printLn("\033[33m>> Migrations rolled back successfully.\033[0m");
      Utils::printLn();
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
    $columns = [
      '#' => 3,
      'Module' => 12,
      'Migration' => 50,
      'Status' => 9,
      'Applied At' => 19,
    ];
    Utils::printLn("\033[33m>> Migrations Status Summary:\033[0m");
    Utils::printLn();
    Utils::printLn("* Total migrations:       \033[32m" . count($all) . "\033[0m");
    Utils::printLn("* Total applied:          \033[32m" . count($applied) . "\033[0m");
    Utils::printLn("* Total pending:          \033[32m" . (count($all) - count($applied)) . "\033[0m");
    Utils::printLn();
    Utils::printLn();

    Utils::printLn("\033[33m>> Migrations Status Details:\033[0m");
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
        . $this->padStatus(isset($applied[$mdata->filepath]) ? "Applied" : "Pending", 9, $noColor) . ' | '
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
    $this->createDatabase();

    if (!$this->alreadyApplied($mdata->filepath)) {
      $operations = $mobj->getOperations();
      if (empty($operations)) return;

      Utils::printLn("\033[33m>>>" . ($module ? " [Mod: '{$module}']" : "") . " Applying migration: \033[32m'{$mdata->name}'\033[0m:");
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
      $executedControl = [];
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

        if (!empty($o->up->sqlstring)) {
          echo '"' . $o->up->sqlstring . "\"\n\n";

          // Perform the operation:
          try {
            Database::getCnn('main')->runMany($o->up);
          } catch (Throwable $thrw) {
            Utils::printLn("\033[33m>> HALT: An error has occured. Rolling back this migration.\033[0m");
            Utils::printLn();
            // Rollback Migration:
            if (!empty($executedControl)) {
              for ($i = count($executedControl) - 1; $i >= 0; $i--) {
                $opDown = $executedControl[$i]->down;
                echo '"' . $opDown->sqlstring . "\"\n\n";
                Database::getCnn('main')->runMany($opDown);
              }
            }

            throw $thrw;
          }
        }

        // Save the operation in the database:
        $this->getDao('_SPLITPHP_MIGRATION_OPERATION')
          ->insert([
            'id_migration' => $migration->id,
            'up' => $o->up->sqlstring,
            'down' => $o->down->sqlstring,
          ]);

        $executedControl[] = $o;
      }

      Dao::flush();
      $counter++;
    }

    if ($mobj->getPreviousDatabase() !== null)
      Database::setName($mobj->getPreviousDatabase());

    ObjLoader::unload($mdata->filepath);
    unset($mobj);
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

    Utils::printLn("\033[33m>>>" . ($module ? " [Mod: '{$module}']" : "") . " Rolling back migration: \033[32m'{$mdata->name}'\033[0m:");
    Utils::printLn("--------------------------------------------------------");
    Utils::printLn();

    $operations = $this->getDao('_SPLITPHP_MIGRATION_OPERATION')
      ->filter('mId')->equalsTo($mdata->id)
      ->fetch(
        function (&$o) {
          $o = $this->sqlBuilder->write($o->down)->output(true);
        },
        "SELECT 
            o.down
          FROM `_SPLITPHP_MIGRATION_OPERATION` o
          WHERE o.id_migration = ?mId?
          ORDER BY o.id DESC"
      );

    if (empty($operations)) {
      Utils::printLn("\033[33m>> No operations to roll back for migration: \033[32m'{$mdata->name}'\033[0m");
      return;
    }
    // Handle operations:
    foreach ($operations as $o) {
      if (empty($o->sqlstring)) continue;

      echo '"' . $o->sqlstring . "\"\n\n";

      // Perform the operation:
      Database::getCnn('main')->runMany($o);
    }

    $this->getDao('_SPLITPHP_MIGRATION')
      ->filter('id')->equalsTo($mdata->id)
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
  private function createDatabase(): void
  {
    // Check if the database exists, if not, create it:
    if (!in_array(Database::getName(), $this->createdDatabases)) {
      $sql = $this->sqlBuilder
        ->createDatabase(Database::getName())
        ->output(true);

      Database::getCnn('main')->runMany($sql);
      Dbmetadata::createMigrationControl();
      $this->createdDatabases[] = Database::getName();
    }
  }

  /**
   * Applies color to the status text based on its value.
   *
   * @param string $status The status text to color.
   * @param bool $noColor If true, disables colored output.
   * @return string The colored status text.
   */
  private function coloredStatus($status, $noColor)
  {
    static $useColor = null;

    if ($useColor === null) {
      $useColor = Cli::supportsAnsi();
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

  /**
   * Pads the status text to a specified length and applies color if supported.
   *
   * @param string $status The status text to pad and color.
   * @param int $length The length to pad the status text to.
   * @param bool $noColor If true, disables colored output.
   * @return string The padded and colored status text.
   */
  private function padStatus($status, $length, $noColor)
  {
    // Primeiro faz o pad no texto puro
    $padded = str_pad($status, $length);
    // Depois aplica a cor
    return $this->coloredStatus($padded, $noColor);
  }
}
