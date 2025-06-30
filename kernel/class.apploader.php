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

use SplitPHP\Utils;

class AppLoader
{
  private const APP_FULLPATH = ROOT_PATH . MAINAPP_PATH;
  private static $map;

  public static function init()
  {
    self::mapApplication();
    self::loadAppEventListeners();
  }

  public static function getMap()
  {
    return self::$map;
  }

  public static function loadService(string $path)
  {
    $mapdata = self::$map;

    // From app's map, try to find its service
    $servicePath = "{$mapdata->mainapp_path}/{$mapdata->services_basepath}/{$path}.php";
    if (file_exists($servicePath))
      return ObjLoader::load($servicePath);

    return null;
  }

  public static function loadTemplate(string $path, array $varlist = [])
  {
    if (!empty($varlist)) extract(Utils::escapeOutput($varlist));

    $mapdata = self::$map;

    $tplPath = "{$mapdata->mainapp_path}/{$mapdata->templates_basepath}/{$path}.php";
    if (!file_exists($tplPath)) return null;

    ob_start();
    include $tplPath;
    return ob_get_clean();
  }

  public static function loadSQL(string $sql)
  {
    $mapdata = self::$map;

    $sqlPath = "{$mapdata->mainapp_path}/{$mapdata->sql_basepath}/{$sql}.php";
    if (!file_exists($sqlPath)) return $sql;

    return file_get_contents($sqlPath);
  }

  public static function listEventFiles()
  {
    $dirPath = self::APP_FULLPATH . "/" . self::$map->events_basepath;

    $paths = [];

    if (is_dir($dirPath)) {
      $dirHandle = opendir($dirPath);
      while (($f = readdir($dirHandle)) !== false) {
        $filepath = "{$dirPath}/{$f}";

        // Combine $dirPath and $file to retrieve fully qualified class path:
        if ($f != '.' && $f != '..' && is_file($filepath))
          $paths[] = $filepath;
      }

      closedir($dirHandle);
    }
    return $paths;
  }

  public static function findCli(array $cmdElements)
  {
    $mapdata = self::$map;

    $basePath = "{$mapdata->mainapp_path}/{$mapdata->commands_basepath}";

    if (is_file("{$basePath}.php")) {
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
        return (object) [
          'cliPath' => "{$basePath}{$cmdPart}.php",
          'cliName' => $cmdPart,
          'cmd' => ":" . implode(':', array_slice($cmdElements, $i + 1))
        ];
      } else {
        return null;
      }
    }
  }

  public static function findWebService(array $urlElements)
  {
    $mapdata = self::$map;

    $basePath = "{$mapdata->mainapp_path}/{$mapdata->routes_basepath}";

    if (is_file("{$basePath}.php")) {
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
        return (object) [
          'webServicePath' => "{$basePath}{$urlPart}.php",
          'webServiceName' => $urlPart,
          'route' => "/" . implode('/', array_slice($urlElements, $i))
        ];
      } else {
        return null;
      }
    }
  }

  public static function listMigrations()
  {
    $mapdata = self::$map;
    $basepath = "{$mapdata->mainapp_path}/{$mapdata->dbmigrations_basepath}";

    $paths = [];

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

          $paths[] = (object) [
            'module' => null,
            'filepath' => $filepath,
            'mkey' => hash('sha256', file_get_contents($filepath)),
            'filename' => $f,
            'name' => $mName
          ];
        }
      }

      closedir($dirHandle);

      usort($paths, function ($a, $b) {
        // Extract just the filename (no directory)
        $aName = basename($a->filepath);
        $bName = basename($b->filepath);

        // Find position of first underscore
        $posA = strpos($aName, '_');
        $posB = strpos($bName, '_');

        // If there is no underscore, treat timestamp as 0
        $tsA = (int) substr($aName, 0, $posA);
        $tsB = (int) substr($bName, 0, $posB);

        // Numeric comparison (PHP 7+ spaceship operator)
        return $tsA <=> $tsB;
      });
    }

    return $paths;
  }

  private static function mapApplication()
  {
    // look for map.ini inside application
    $appMapPath = self::APP_FULLPATH . '/' . 'map.ini';
    if (is_file($appMapPath)) $mapdata = parse_ini_file($appMapPath);

    $mapdata = $mapdata ?? [];

    self::$map = (object) [
      'mainapp_path' => self::APP_FULLPATH,
      'routes_basepath' => @$mapdata['ROUTES_BASEPATH'] ?: 'routes',
      'services_basepath' => @$mapdata['SERVICES_BASEPATH'] ?: 'services',
      'templates_basepath' => @$mapdata['TEMPLATES_BASEPATH'] ?: 'templates',
      'commands_basepath' => @$mapdata['COMMANDS_BASEPATH'] ?: 'commands',
      'eventlisteners_basepath' => @$mapdata['EVENTLISTENERS_BASEPATH'] ?: 'eventlisteners',
      'events_basepath' => @$mapdata['EVENTS_BASEPATH'] ?: 'events',
      'sql_basepath' => @$mapdata['SQL_BASEPATH'] ?: 'sql',
      'dbmigrations_basepath' => @$mapdata['DBMIGRATION_BASEPATH'] ?: 'dbmigrations',
    ];
  }

  private static function loadAppEventListeners()
  {
    $mapdata = self::$map;

    $lstPath = "{$mapdata->mainapp_path}/{$mapdata->eventlisteners_basepath}";

    if (is_file("{$lstPath}.php")) {
      ObjLoader::load("{$lstPath}.php");
      return;
    }

    if (!file_exists($lstPath)) return;

    foreach (new DirectoryIterator($lstPath) as $lst) {
      // skip "." and ".." and anything that is a directory
      if ($lst->isDot() || $lst->isDir()) continue;

      $path = $lst->getPathname();
      ObjLoader::load($path);
    }
  }
}
