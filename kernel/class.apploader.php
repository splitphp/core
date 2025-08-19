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

/**
 * Class AppLoader
 *
 * This class is responsible for loading main application's stuff, its services, templates, SQL files, and event listeners.
 * It also provides methods to find CLI commands and web services based on the application's map.
 *
 * @package SplitPHP
 */
class AppLoader
{
  /**
   * @var string The full path to the main application directory.
   */
  private const APP_FULLPATH = ROOT_PATH . MAINAPP_PATH;

  /**
   * @var object The application's map data.
   */
  private static $map;

  /**
   * Initializes the application loader by mapping the application and loading event listeners.
   *
   * This method should be called at the beginning of the application's lifecycle to ensure that
   * the application is properly set up before any other operations are performed.
   */
  public static function init(): void
  {

    self::mapApplication();


    self::loadAppEventListeners();
  }

  /**
   * Returns the application's map data.
   *
   * @return object The application's map data.
   */
  public static function getMap(): object
  {
    return self::$map;
  }

  /**
   * Loads a service from the application's services directory.
   *
   * @param string $path The path to the service file (without the .php extension).
   * @return object|null The loaded service object or null if the service file does not exist.
   */
  public static function loadService(string $path): ?object
  {

    $mapdata = self::$map;

    // From app's map, try to find its service
    $servicePath = "{$mapdata->mainapp_path}/{$mapdata->services_basepath}/{$path}.php";
    if (file_exists($servicePath))
      $obj = ObjLoader::load($servicePath);


    return $obj ?? null;
  }

  /**
   * Loads a template from the application's templates directory.
   *
   * @param string $path The path to the template file (without the .php extension).
   * @param array $varlist An optional associative array of variables to be extracted and made available in the template.
   * @return string|null The rendered template content or null if the template file does not exist.
   */
  public static function loadTemplate(string $path, array $varlist = []): ?string
  {

    if (!empty($varlist)) extract(Utils::escapeHTML($varlist));

    $mapdata = self::$map;

    $tplPath = "{$mapdata->mainapp_path}/{$mapdata->templates_basepath}/{$path}.php";
    if (!file_exists($tplPath)) return null;

    ob_start();
    include $tplPath;

    return ob_get_clean();
  }

  /**
   * Loads an SQL file from the application's SQL directory.
   *
   * @param string $sql The name of the SQL file (without the .php extension).
   * @return string The content of the SQL file or the original SQL string if the file does not exist.
   */
  public static function loadSQL(string $sql): string
  {

    $mapdata = self::$map;

    $sqlPath = "{$mapdata->mainapp_path}/{$mapdata->sql_basepath}/{$sql}.php";
    if (!file_exists($sqlPath)) return $sql;


    return file_get_contents($sqlPath);
  }

  /**
   * Lists all event files in the application's events directory.
   *
   * @return array An array of event file paths.
   */
  public static function listEventFiles(): array
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

  /**
   * Finds a CLI command based on the provided command elements.
   *
   * @param array $cmdElements The command elements to search for.
   * @return object|null The found CLI command object or null if not found.
   */
  public static function findCli(array $cmdElements): ?object
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

    return null;
  }

  /**
   * Finds a web service based on the provided URL elements.
   *
   * @param array $urlElements The URL elements to search for.
   * @return object|null The found web service object or null if not found.
   */
  public static function findWebService(array $urlElements): ?object
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
          'route' => "/" . implode('/', array_slice($urlElements, $i + 1))
        ];
      } else {
        return null;
      }
    }

    return null;
  }

  /**
   * Lists all migration files in the application's database migrations directory.
   *
   * @return array An array of migration file paths.
   */
  public static function listMigrations(): array
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

  /**
   * Lists all seed files in the application's database seeds directory.
   *
   * @return array An array of seed file paths.
   */
  public static function listSeeds(): array
  {
    $mapdata = self::$map;
    $basepath = "{$mapdata->mainapp_path}/{$mapdata->dbseeds_basepath}";

    $paths = [];

    if (is_dir($basepath)) {
      $dirHandle = opendir($basepath);
      while (($f = readdir($dirHandle)) !== false) {
        if (!Utils::regexTest('/^\d{10}_/', $f)) continue;

        $filepath = "{$basepath}/{$f}";

        // Combine $dirPath and $file to retrieve fully qualified class path:
        if ($f != '.' && $f != '..' && is_file($filepath)) {
          // Find the seed name from the file path:
          $sepIdx = strpos(basename($filepath), '_');
          $sName = substr(basename($filepath), $sepIdx + 1, strrpos(basename($filepath), '.') - $sepIdx - 1);
          $sName = str_replace('-', ' ', $sName);
          $sName = ucwords($sName);

          $paths[] = (object) [
            'module' => null,
            'filepath' => $filepath,
            'skey' => hash('sha256', file_get_contents($filepath)),
            'filename' => $f,
            'name' => $sName
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

  /**
   * Maps the application directories and files.
   *
   * This method scans the application's directory structure and creates a mapping
   * of all relevant directories and files for later use.
   */
  private static function mapApplication(): void
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
      'dbseeds_basepath' => @$mapdata['DBSEEDS_BASEPATH'] ?: 'dbseeds',
    ];
  }

  /**
   * Loads the application's event listeners.
   *
   * This method scans the application's event listeners directory and loads all
   * event listener classes.
   */
  private static function loadAppEventListeners(): void
  {
    $mapdata = self::$map;

    $lstPath = "{$mapdata->mainapp_path}/{$mapdata->eventlisteners_basepath}";

    if (is_file("{$lstPath}.php")) {
      ObjLoader::load("{$lstPath}.php");
      return;
    }

    if (!file_exists($lstPath)) return;

    foreach (new \DirectoryIterator($lstPath) as $lst) {
      // skip "." and ".." and anything that is a directory
      if ($lst->isDot() || $lst->isDir()) continue;

      $path = $lst->getPathname();
      ObjLoader::load($path);
    }
  }
}
