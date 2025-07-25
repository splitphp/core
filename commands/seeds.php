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

/**
 * Class Seeds
 *
 * This class provides commands to manage database seeds, including applying, rolling back, and checking the status of seeds.
 *
 * @package SplitPHP\Commands
 */
class Seeds extends Cli
{
  /** @var \SplitPHP\Database\Sql $sqlBuilder SQL builder instance for generating SQL queries. */
  private Sql $sqlBuilder;

  /**
   * Initializes the Seeds command class.
   *
   * This method sets up the SQL builder, loads necessary classes, and defines the commands for applying,
   * rolling back, and checking the status of seeds.
   *
   * @throws Exception If database connection is off or if there are issues with loading classes.
   */
  public function init(): void
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform seeding.");

    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . Database::getRdbmsName() . "/class.sql.php");

    require_once CORE_PATH . '/dbmanager/class.seed.php';

    // Apply Command:
    $this->addCommand('apply', function ($args) {
      Dbmetadata::createSeedControl();
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

      // List all seeds to be applied:
      $seeds = $this->listSeedsFromFiles($module ?? null);
      $counter = 0;
      Utils::printLn("\033[33m>> Reading pending seeds...\033[0m");
      Utils::printLn();
      // Apply all listed seeds:
      foreach ($seeds as $sdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("\033[33m>> HALT: Limit reached, stopping applying seeds.\033[0m");
          return;
        }
        $this->applySeed($sdata, $counter);
      }
      Utils::printLn("\033[33m>> Seeds applied successfully.\033[0m");
      Utils::printLn();
    });

    // Rollback Command:
    $this->addCommand('rollback', function ($args) {
      Dbmetadata::createSeedControl();
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

      Utils::printLn("\033[33m>> Reading applied seeds...\033[0m");
      echo PHP_EOL;

      $filters = [
        '$sort_by' => 1,
        '$sort_direction' => 'DESC'
      ];
      if (!empty($module)) {
        $filters['module'] = $module;
      }
      $counter = 0;
      $seeds = $this->getDao('_SPLITPHP_SEED')
        ->bindParams($filters)
        ->fetch(
          function ($sobj) use ($limit, &$counter) {
            if (isset($limit) && $counter >= $limit) {
              Utils::printLn("\033[33m>> HALT: Limit reached, stopping seeds rollback.\033[0m");
              return false;
            }
            $this->rollbackSeed($sobj, $counter);
          },
          "SELECT 
              id,
              name, 
              date_exec, 
              filepath, 
              skey, 
              module
            FROM `_SPLITPHP_SEED`"
        );

      if (count($seeds) == $counter)
        Utils::printLn("\033[33m>> Seeds rolled back successfully.\033[0m");
      Utils::printLn();
    });

    // Status Command:
    $this->addCommand('status', function ($args) {
      Dbmetadata::createSeedControl();
      $module = null;
      if (isset($args['--module'])) {
        if (!is_string($args['--module']) || is_numeric($args['--module']))
          throw new Exception("Invalid module name. It must be a non-numeric string.");

        $module = $args['--module'];
      }

      // List all seeds:
      $all = [];
      foreach (ModLoader::listSeeds($module ?? null) as $modSeeds) {
        $all = [...$all, ...$modSeeds];
      }

      if (empty($module)) {
        $all = [...$all, ...AppLoader::listSeeds()];
      }

      // List applied seeds:
      $dao = $this->getDao('_SPLITPHP_SEED');
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
      Utils::printLn("  apply       [--limit=<number>] [--module=<name>]   Apply pending seeds to the database.");
      Utils::printLn("                                                     Already applied seeds will be skipped.");
      Utils::printLn();
      Utils::printLn("  rollback    [--limit=<number>] [--module=<name>]   Roll back previously applied seeds.");
      Utils::printLn();
      Utils::printLn("  status      [--module=<name>] [--no-color]         Show the current status of seeds.");
      Utils::printLn("                                                     Displays applied and pending seeds per module.");
      Utils::printLn();
      Utils::printLn("  help                                               Show this help message.");
      Utils::printLn();

      Utils::printLn("PARAMETERS:");
      Utils::printLn("  --limit=<number>     Limit the number of seeds to apply or roll back.");
      Utils::printLn("                       If omitted, applies/rollbacks all available seeds.");
      Utils::printLn("  --module=<name>      Specify the module whose seeds should be applied or rolled back.");
      Utils::printLn("                       If omitted, all available seeds are considered.");
      Utils::printLn("  --no-color           Disable colored output. Useful when redirecting output to files or");
      Utils::printLn("                       running in terminals that do not support ANSI colors.");
    });
  }

  /**
   * Displays the status of all seeds, including applied and pending seeds.
   *
   * @param array $all An array containing all seed data.
   * @param array $applied An array containing applied seed data.
   * @param bool $noColor If true, disables colored output.
   */
  private function showStatusResults(array $all, array $applied, $noColor = false): void
  {
    $columns = [
      '#' => 3,
      'Module' => 12,
      'Seed' => 50,
      'Status' => 9,
      'Applied At' => 19,
    ];
    Utils::printLn("\033[33m>> Seeds Status Summary:\033[0m");
    Utils::printLn();
    Utils::printLn("* Total seeds:       \033[32m" . count($all) . "\033[0m");
    Utils::printLn("* Total applied:     \033[32m" . count($applied) . "\033[0m");
    Utils::printLn("* Total pending:     \033[32m" . (count($all) - count($applied)) . "\033[0m");
    Utils::printLn();
    Utils::printLn();

    Utils::printLn("\033[33m>> Seeds Status Details:\033[0m");
    Utils::printLn();

    // Print the header:
    Utils::printLn(Utils::buildSeparator($columns));
    echo '| '
      . Utils::pad('#', 3) . ' | '
      . Utils::pad('Module', 12) . ' | '
      . Utils::pad('Seed', 50) . ' | '
      . Utils::pad('Status', 9) . ' | '
      . Utils::pad('Applied At', 19) . ' |' . PHP_EOL;
    Utils::printLn(Utils::buildSeparator($columns));

    // Print each seed status:
    foreach ($all as $idx => $sdata) {
      echo '| '
        . Utils::pad($idx + 1, 3) . ' | '
        . Utils::pad($sdata->module, 12) . ' | '
        . Utils::pad($sdata->name, 50) . ' | '
        . $this->padStatus(isset($applied[$sdata->filepath]) ? "Applied" : "Pending", 9, $noColor) . ' | '
        . Utils::pad(($applied[$sdata->filepath]->date_exec ?? ""), 19) . ' |' . PHP_EOL;
    }
    Utils::printLn(Utils::buildSeparator($columns));
  }

  /**
   * Applies a seed by loading the seed object from the specified file path,
   * executing its apply method, and saving the operations in the database.
   *
   * @param string $fpath The file path of the seed to be applied.
   */
  private function applySeed($sdata, &$counter): void
  {
    $module = $sdata->module ?? null;

    $sobj = ObjLoader::load($sdata->filepath);
    $sobj->apply();

    if (!$this->alreadyApplied($sdata->filepath)) {
      $operations = $sobj->getOperations();
      if (empty($operations)) return;

      Utils::printLn("\033[33m>>" . ($module ? " [Mod: '{$module}']" : "") . " Applying seed: \033[32m'{$sdata->name}':\033[0m");
      Utils::printLn("--------------------------------------------------------");
      Utils::printLn();

      $seed = $this->getDao('_SPLITPHP_SEED')
        ->insert([
          'name' => $sdata->name,
          'date_exec' => date('Y-m-d H:i:s'),
          'filepath' => $sdata->filepath,
          'skey' => $sdata->skey,
          'module' => $module
        ]);

      // Handle operations:
      foreach ($operations as $o) {
        if (!$o->isAllowedInEnv(APP_ENV)) {
          Utils::printLn("\033[33m>>> Skipping seed operation: " . $o->getName() . " - Not allowed in current environment: \033[32m'" . APP_ENV . "'\033[0m");
          continue;
        }

        $builtSql = $o->obtainSql();

        if (!empty($builtSql->up->sqlstring)) {
          echo '"' . $builtSql->up->sqlstring . "\"\n\n";

          // Perform the operation:
          Database::getCnn('main')->runSql($builtSql->up);
        }

        // Save the operation in the database:
        $this->getDao('_SPLITPHP_SEED_OPERATION')->insert([
          'id_seed' => $seed->id,
          'up' => $builtSql->up->sqlstring,
          'down' => $builtSql->down->sqlstring,
        ]);
      }

      Dao::flush();
      $counter++;
    }

    if ($sobj->getPreviousDatabase() !== null)
      Database::setName($sobj->getPreviousDatabase());

    ObjLoader::unload($sdata->filepath);
    unset($sobj);
  }

  /**
   * Rolls back a migration by loading the migration object from the specified file path,
   * executing its down method, and removing the migration record from the database.
   *
   * @param object $sdata The migration data object containing the migration metadata.
   * @param int &$counter A reference to the counter that tracks the number of rolled back migrations.
   */
  private function rollbackSeed($sdata, int &$counter)
  {
    $module = $sdata->module ?? null;

    Utils::printLn("\033[33m>>" . ($module ? " [Mod: '{$module}']" : "") . " Rolling back seed: \033[32m'{$sdata->name}'\033[0m:");
    Utils::printLn("--------------------------------------------------------");
    Utils::printLn();

    $operations = $this->getDao('_SPLITPHP_SEED_OPERATION')
      ->filter('sId')->equalsTo($sdata->id)
      ->find(
        "SELECT 
            o.down
          FROM `_SPLITPHP_SEED_OPERATION` o
          WHERE o.id_seed = ?sId?
          ORDER BY o.id DESC"
      );

    if (empty($operations)) {
      Utils::printLn("\033[33m>>> No operations found for this seed. Skipping rollback.\033[0m");
      return;
    }
    // Handle operations:
    foreach ($operations as $o) {
      if (empty($o->sqlstring)) continue;

      $o = $this->sqlBuilder->write($o->down)->output(true);

      echo '"' . $o->sqlstring . "\"\n\n";

      // Perform the operation:
      Database::getCnn('main')->runMany($o);
    }

    $this->getDao('_SPLITPHP_SEED')
      ->filter('id')->equalsTo($sdata->id)
      ->delete();

    Dao::flush();
    $counter++;
  }

  /**
   * Checks if a seed has already been applied by comparing the file's content hash
   * with the stored seed keys in the database.
   *
   * @param string $fpath The file path of the seed to check.
   * @return bool Returns true if the seed has already been applied, false otherwise.
   */
  private function alreadyApplied($fpath)
  {
    $skey = hash('sha256', file_get_contents($fpath));

    return !empty($this->getDao('_SPLITPHP_SEED')
      ->filter('skey')->equalsTo($skey)
      ->first());
  }

  /**
   * Lists all seeds from the specified module or all, if no module is specified.
   *
   * @param string|null $module The name of the module to list seeds from, or null for all seeds.
   * @return array An array of seed objects.
   */
  private function listSeedsFromFiles($module): array
  {
    $seeds = [];
    foreach (ModLoader::listSeeds($module ?? null) as $modSeeds) {
      $seeds = [...$seeds, ...$modSeeds];
    }

    if (empty($module)) {
      $seeds = [...$seeds, ...AppLoader::listSeeds()];

      usort($seeds, function ($a, $b) {
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

    return $seeds;
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
