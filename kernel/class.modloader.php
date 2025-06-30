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

namespace SplitPHP;

use DirectoryIterator;
use SplitPHP\Utils;
use SplitPHP\Helpers;

class ModLoader
{
  private const MOD_FULLPATH = ROOT_PATH . MODULES_PATH;
  private static $maps = [];

  public static function init()
  {
    Helpers::MemUsage()->logMemory("ModLoader::init() - before mapping modules");
    self::mapModules();
    Helpers::MemUsage()->logMemory("ModLoader::init() - after mapping modules");
    Helpers::MemUsage()->logMemory("ModLoader::init() - before loading module event listeners");
    self::loadModEventListeners();
    Helpers::MemUsage()->logMemory("ModLoader::init() - after loading module event listeners");
  }

  public static function getMaps(?string $modName = null)
  {
    if (!empty($modName)) return self::$maps[$modName];
    else return self::$maps;
  }

  public static function loadService(string $path)
  {
    Helpers::MemUsage()->logMemory("ModLoader::loadService() - before loading service at path: {$path}");
    $mapdata = self::findModuleByPath($path);
    if (empty($mapdata)) return null;

    // From its map, try to find its service
    $servicePath = "{$mapdata->modulepath}/{$mapdata->services_basepath}/{$mapdata->itemPath}.php";
    if (file_exists($servicePath))
      $obj = ObjLoader::load($servicePath);

    Helpers::MemUsage()->logMemory("ModLoader::loadService() - after loading service at path: {$path}");
    return $obj ?? null;
  }

  public static function loadTemplate(string $path, array $varlist = [])
  {
    Helpers::MemUsage()->logMemory("ModLoader::loadTemplate() - before loading template");
    if (!empty($varlist)) extract(Utils::escapeOutput($varlist));

    $metadata = self::findModuleByPath($path);
    if (empty($metadata)) return null;

    $tplPath = "{$metadata->modulepath}/{$metadata->templates_basepath}/{$metadata->itemPath}.php";
    if (!file_exists($tplPath)) return null;

    ob_start();
    include $tplPath;
    Helpers::MemUsage()->logMemory("ModLoader::loadTemplate() - after loading template");
    return ob_get_clean();
  }

  public static function loadSQL(?string $sql = null)
  {
    Helpers::MemUsage()->logMemory("ModLoader::loadSQL() - before loading SQL file");
    if (empty($sql)) return null;
    $metadata = self::findModuleByPath($sql);
    if (empty($metadata)) {
      Helpers::MemUsage()->logMemory("ModLoader::loadSQL() - after not finding SQL's module metadata");
      return $sql;
    }

    $sqlPath = "{$metadata->modulepath}/{$metadata->sql_basepath}/{$metadata->itemPath}.sql";
    if (!file_exists($sqlPath)) return $sql;

    Helpers::MemUsage()->logMemory("ModLoader::loadSQL() - after loading SQL file");
    return file_get_contents($sqlPath);
  }

  public static function listEventFiles()
  {
    Helpers::MemUsage()->logMemory("ModLoader::listEventFiles() - before listing event files");
    $paths = [];
    foreach (self::$maps as $mapdata) {
      $dirPath = $mapdata->modulepath . "/" . $mapdata->events_basepath;

      if (is_dir($dirPath)) {
        $dirHandle = opendir($dirPath);
        while (($f = readdir($dirHandle)) !== false) {
          // Combine $dirPath and $file to retrieve fully qualified class path:
          $filepath = "{$dirPath}/{$f}";

          if ($f != '.' && $f != '..' && is_file($filepath))
            $paths[] = $filepath;
        }

        closedir($dirHandle);
      }
    }

    Helpers::MemUsage()->logMemory("ModLoader::listEventFiles() - after listing event files");
    return $paths;
  }

  public static function findCli(array $cmdElements)
  {
    Helpers::MemUsage()->logMemory("ModLoader::findCli() - before finding CLI");
    $mapdata = self::$maps[$cmdElements[0]] ?? null;
    if (empty($mapdata)) return null;

    $basePath = "{$mapdata->modulepath}/{$mapdata->commands_basepath}";

    if (is_file("{$basePath}.php")) {
      Helpers::MemUsage()->logMemory("ModLoader::findCli() - after finding CLI");
      return (object) [
        'cliPath' => "{$basePath}.php",
        'cliName' => $mapdata->commands_basepath,
        'cmd' => ":" . implode(':', array_slice($cmdElements, 1))
      ];
    }

    $basePath .= '/';

    foreach ($cmdElements as $i => $cmdPart) {
      if (is_dir($basePath . $cmdPart))
        $basePath .= $cmdPart . '/';
      elseif (is_file("{$basePath}{$cmdPart}.php")) {
        Helpers::MemUsage()->logMemory("ModLoader::findCli() - after finding CLI");
        return (object) [
          'cliPath' => "{$basePath}{$cmdPart}.php",
          'cliName' => $cmdPart,
          'cmd' => ":" . implode(':', array_slice($cmdElements, $i + 1))
        ];
      }
    }

    Helpers::MemUsage()->logMemory("ModLoader::findCli() - after not finding CLI");
    return null;
  }

  public static function findWebService(array $urlElements)
  {
    Helpers::MemUsage()->logMemory("ModLoader::findWebService() - before finding web service");
    if (array_key_exists($urlElements[0], self::$maps) == false) return null;

    $mapdata = self::$maps[$urlElements[0]];

    $basePath = "{$mapdata->modulepath}/{$mapdata->routes_basepath}";

    if (is_file("{$basePath}.php")) {
      Helpers::MemUsage()->logMemory("ModLoader::findWebService() - after finding web service");
      return (object) [
        'webServicePath' => "{$basePath}.php",
        'webServiceName' => $mapdata->routes_basepath,
        'route' => "/" . implode('/', array_slice($urlElements, 1))
      ];
    }

    $basePath .= '/';

    foreach ($urlElements as $i => $urlPart) {
      if (is_dir($basePath . $urlPart))
        $basePath .= $urlPart . '/';
      elseif (is_file("{$basePath}{$urlPart}.php")) {
        Helpers::MemUsage()->logMemory("ModLoader::findWebService() - after finding web service");
        return (object) [
          'webServicePath' => "{$basePath}{$urlPart}.php",
          'webServiceName' => $urlPart,
          'route' => "/" . implode('/', array_slice($urlElements, $i + 1))
        ];
      }
    }

    Helpers::MemUsage()->logMemory("ModLoader::findWebService() - after not finding web service");
    return null;
  }

  public static function listMigrations(?string $filterModule = null)
  {
    Helpers::MemUsage()->logMemory("ModLoader::listMigrations() - before listing migrations");
    $paths = [];

    foreach (self::$maps as $modName => $mapdata) {
      if (!empty($filterModule) && $modName != $filterModule) continue;

      $basepath = "{$mapdata->modulepath}/{$mapdata->dbmigrations_basepath}";

      $paths[$modName] = [];

      if (is_dir($basepath)) {
        $dirHandle = opendir($basepath);
        while (($f = readdir($dirHandle)) !== false) {
          if (!Utils::regexTest('/^\d{10}_/', $f)) continue;

          $filepath = "{$basepath}/{$f}";

          // Combine $dirPath and $file to retrieve fully qualified class path:
          if ($f != '.' && $f != '..' && is_file($filepath)) {
            // Find the migration name from the file path:
            $sepIdx = strpos(basename($filepath), '_');
            $mName = substr(basename($filepath), $sepIdx + 1, strrpos(basename($filepath), '.') - $sepIdx - 1);
            $mName = str_replace('-', ' ', $mName);
            $mName = ucwords($mName);

            $paths[$modName][] = (object) [
              'module' => $modName,
              'filepath' => $filepath,
              'mkey' => hash('sha256', file_get_contents($filepath)),
              'filename' => $f,
              'name' => $mName
            ];
          }
        }

        closedir($dirHandle);
      }

      usort($paths[$modName], function ($a, $b) {
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

    Helpers::MemUsage()->logMemory("ModLoader::listMigrations() - after listing migrations");
    return $paths;
  }

  private static function findModuleByPath(string $path)
  {
    Helpers::MemUsage()->logMemory("ModLoader::findModuleByPath() - before trying to find module by path");
    // Check for invalid module path:
    if (strpos($path, '/') === false) {
      Helpers::MemUsage()->logMemory("ModLoader::findModuleByPath() - after not finding module by path");
      return null;
    }
    // Break module path into pieces:
    $pathData = explode('/', $path);
    if ($pathData[0] == '') unset($pathData[0]);

    // Find the module name
    $modName = array_splice($pathData, 0, 1);
    $modName = $modName[0];
    Helpers::MemUsage()->logMemory("ModLoader::findModuleByPath() - after trying to find module by path");
    if (empty($modName) || !array_key_exists($modName, self::$maps))
      return null;

    return (object)[
      ...(array) self::$maps[$modName],
      'itemPath' => implode('/', $pathData),
      'itemName' => end($pathData)
    ];
  }

  private static function mapModules()
  {
    foreach (new DirectoryIterator(self::MOD_FULLPATH) as $mod) {
      // skip "." and ".." and anything that isn’t a directory
      if ($mod->isDot() || !$mod->isDir()) continue;

      // get the directory name and full path
      $dirName = $mod->getFilename();
      $dirPath = $mod->getPathname();

      // look for map.ini inside it
      $iniFile = $dirPath . DIRECTORY_SEPARATOR . 'map.ini';
      if (is_file($iniFile)) $moddata = parse_ini_file($iniFile);

      $moddata = $moddata ?? [];

      self::$maps[$dirName] = (object) [
        'modulename' => $dirName,
        'modulepath' => $dirPath,
        'routes_basepath' => @$moddata['ROUTES_BASEPATH'] ?: 'routes',
        'services_basepath' => @$moddata['SERVICES_BASEPATH'] ?: 'services',
        'templates_basepath' => @$moddata['TEMPLATES_BASEPATH'] ?: 'templates',
        'commands_basepath' => @$moddata['COMMANDS_BASEPATH'] ?: 'commands',
        'eventlisteners_basepath' => @$moddata['EVENTLISTENERS_BASEPATH'] ?: 'eventlisteners',
        'events_basepath' => @$moddata['EVENTS_BASEPATH'] ?: 'events',
        'sql_basepath' => @$moddata['SQL_BASEPATH'] ?: 'sql',
        'dbmigrations_basepath' => @$moddata['DBMIGRATIONS_BASEPATH'] ?: 'dbmigrations',
      ];

      unset($moddata);
    }
  }

  private static function loadModEventListeners()
  {
    foreach (self::$maps as $mod) {
      $lstPath = "{$mod->modulepath}/{$mod->eventlisteners_basepath}";
      if (is_file("{$lstPath}.php")) {
        ObjLoader::load("{$lstPath}.php");
        continue;
      }

      if (!file_exists($lstPath)) continue;

      foreach (new DirectoryIterator($lstPath) as $lst) {
        // skip "." and ".." and anything that is a directory
        if ($lst->isDot() || $lst->isDir()) continue;

        $path = $lst->getPathname();
        ObjLoader::load($path);
      }
    }
  }
}
