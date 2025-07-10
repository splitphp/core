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

class Seeds extends Cli
{
  private $sqlBuilder;

  public function init()
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform seeding.");

    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");

    require_once CORE_PATH . '/dbmanager/class.seed.php';

    Dbmetadata::createSeedControl();

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

      // List all seeds to be applied:
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

      $counter = 0;
      // Apply all listed seeds:
      foreach ($seeds as $sdata) {
        if (isset($limit) && $counter >= $limit) {
          Utils::printLn("Limit reached, stopping applying seeds.");
          return;
        }
        $this->applySeed($sdata, $counter);
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
      $dao = $this->getDao('_SPLITPHP_SEED');

      if (!empty($module)) {
        $dao = $dao->filter('module')->equalsTo($module);
        $moduleFilter = "WHERE s.module = ?module?";
      }

      $sql = "SELECT 
            s.id AS id,
            s.name AS name,
            s.filepath AS filepath,
            s.date_exec AS date_exec,
            s.module AS module,
            o.down AS down
          FROM _SPLITPHP_SEED s
          JOIN _SPLITPHP_SEED_OPERATION o ON s.id = o.id_seed
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

          Utils::printLn(">>" . ($operation->module ? " [Mod: '{$operation->module}']" : "") . " Rolling back seed: '" . $operation->name . "' applied at " . $operation->date_exec . ":\n");

          Dao::flush();
        }

        $opDown = $this->sqlBuilder
          ->write($operation->down, overwrite: true)
          ->output(true);

        Utils::printLn("\"{$operation->down}\"");
        Utils::printLn("--------------------------------------------------------");
        Utils::printLn();

        // Perform the operation:
        Database::getCnn('main')->runSql($opDown);

        $counter = count($execControl);

        $this->getDao('_SPLITPHP_SEED')
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
      'Seed' => 50,
      'Status' => 9,
      'Applied At' => 19,
    ];
    Utils::printLn(">> Seeds Status Summary:");
    Utils::printLn();
    Utils::printLn("* Total seeds:       " . count($all));
    Utils::printLn("* Total applied:     " . count($applied));
    Utils::printLn("* Total pending:     " . count($all) - count($applied));
    Utils::printLn();
    Utils::printLn();

    Utils::printLn(">> Seeds Status Details:");
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
        . padStatus(isset($applied[$sdata->filepath]) ? "Applied" : "Pending", 9, $noColor) . ' | '
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
  private function applySeed($sdata, &$counter)
  {
    $module = $sdata->module ?? null;

    if ($this->alreadyApplied($sdata->filepath)) return;

    Utils::printLn(">>" . ($module ? " [Mod: '{$module}']" : "") . " Applying seed: '{$sdata->name}':");
    Utils::printLn("--------------------------------------------------------");
    Utils::printLn();

    $sobj = ObjLoader::load($sdata->filepath);
    $sobj->apply();
    $operations = $sobj->getOperations();

    if (empty($operations)) return;

    // Handle operations:
    $opsToSave = [];
    foreach ($operations as $o) {
      if ($o->isAllowedInEnv(APP_ENV)) {
        Utils::printLn(">> Executing seed operation: " . $o->getName());
      } else {
        Utils::printLn(">> Skipping seed operation: " . $o->getName() . " - Not allowed in current environment: '" . APP_ENV . "'");
        continue;
      }

      $builtSql = $o->obtainSql();

      echo '"' . $builtSql->up->sqlstring . "\"\n\n";

      // Perform the operation:
      Database::getCnn('main')->runSql($builtSql->up);

      // Save the operation in the database:
      $opsToSave[] = [
        'up' => $builtSql->up->sqlstring,
        'down' => $builtSql->down->sqlstring,
      ];
    }

    if (empty($opsToSave)) {
      Utils::printLn(">> No allowed operations to run for this seed.");
    } else {
      // Save the seed key in the database:
      $seed = $this->getDao('_SPLITPHP_SEED')
        ->insert([
          'name' => $sdata->name,
          'date_exec' => date('Y-m-d H:i:s'),
          'filepath' => $sdata->filepath,
          'skey' => $sdata->skey,
          'module' => $module
        ]);

      foreach ($opsToSave as &$op)
        $op['id_seed'] = $seed->id;

      $this->getDao('_SPLITPHP_SEED_OPERATION')->insert($opsToSave);
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
    $skey = hash('sha256', file_get_contents($fpath));

    return !empty($this->getDao('_SPLITPHP_SEED')
      ->filter('skey')->equalsTo($skey)
      ->first());
  }
}
