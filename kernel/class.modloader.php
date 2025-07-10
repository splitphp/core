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

/**
 * Class ModLoader
 * 
 * This class is responsible for loading modules stuff, their services, templates, SQL files, and event listeners.
 * It also provides methods to find CLI commands and web services defined in the modules.
 *
 * @package SplitPHP
 */
class ModLoader
{
  /**
   * @var string The full path to the modules directory.
   */
  private const MOD_FULLPATH = ROOT_PATH . MODULES_PATH;

  /**
   * @var array An array to hold module maps.
   */
  private static $maps = [];

  /**
   * Initializes the ModLoader by mapping modules. 
   * It also loads event listeners for each module.
   * This method should be called once at the start of the application to ensure all modules are loaded properly.
   */
  public static function init(): void
  {

    self::mapModules();


    self::loadModEventListeners();
  }

  /**
   * Returns the module maps.
   * If a module name is provided, it returns the map for that specific module.
   * Otherwise, it returns all module maps.
   *
   * @param string|null $modName The name of the module to get the map for, or null to get all maps.
   * @return array The module maps.
   */
  public static function getMaps(?string $modName = null): object|array
  {
    if (!empty($modName)) return self::$maps[$modName];
    else return self::$maps;
  }

  /**
   * Loads a service from the specified path.
   * The path should be in the format "module/service/item".
   * If the service file exists, it is loaded and returned.
   * If not, null is returned.
   *
   * @param string $path The path to the service.
   * @return object|null The loaded service object or null if not found.
   */
  public static function loadService(string $path): ?object
  {

    $mapdata = self::findModuleByPath($path);
    if (empty($mapdata)) return null;

    // From its map, try to find its service
    $servicePath = "{$mapdata->modulepath}/{$mapdata->services_basepath}/{$mapdata->itemPath}.php";
    if (file_exists($servicePath))
      $obj = ObjLoader::load($servicePath);


    return $obj ?? null;
  }

  /**
   * Loads a template from the specified path.
   * The path should be in the format "module/template/item".
   * If the template file exists, it is loaded and returned.
   * If not, null is returned.
   *
   * @param string $path The path to the template.
   * @param array $varlist An optional list of variables to extract into the template.
   * @return string|null The rendered template content or null if not found.
   */
  public static function loadTemplate(string $path, array $varlist = []): ?string
  {

    if (!empty($varlist)) extract(Utils::escapeHTML($varlist));

    $metadata = self::findModuleByPath($path);
    if (empty($metadata)) return null;

    $tplPath = "{$metadata->modulepath}/{$metadata->templates_basepath}/{$metadata->itemPath}.php";
    if (!file_exists($tplPath)) return null;

    ob_start();
    include $tplPath;

    return ob_get_clean();
  }

  /**
   * Loads an SQL file from the specified path.
   * The path should be in the format "module/sql/item".
   * If the SQL file exists, it is loaded and returned.
   * If not, null is returned.
   *
   * @param string|null $sql The path to the SQL file.
   * @return string|null The SQL content or null if not found.
   */
  public static function loadSQL(?string $sql = null): ?string
  {

    if (empty($sql)) return null;
    $metadata = self::findModuleByPath($sql);
    if (empty($metadata)) {

      return $sql;
    }

    $sqlPath = "{$metadata->modulepath}/{$metadata->sql_basepath}/{$metadata->itemPath}.sql";
    if (!file_exists($sqlPath)) return $sql;


    return file_get_contents($sqlPath);
  }

  /**
   * Lists all event files for the loaded modules.
   *
   * @return array An array of event file paths.
   */
  public static function listEventFiles(): array
  {

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


    return $paths;
  }

  /**
   * Finds a CLI command based on the provided command elements.
   * The first element should be the module name, followed by the command path.
   * If the command file exists, it returns an object with the CLI path, name, and command.
   * If not found, it returns null.
   *
   * @param array $cmdElements The command elements to search for.
   * @return object|null An object with CLI details or null if not found.
   */
  public static function findCli(array $cmdElements): ?object
  {

    $mapdata = self::$maps[$cmdElements[0]] ?? null;
    if (empty($mapdata)) return null;

    $basePath = "{$mapdata->modulepath}/{$mapdata->commands_basepath}";

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
      }
    }


    return null;
  }

  /**
   * Finds a web service based on the provided URL elements.
   * The first element should be the module name, followed by the service path.
   * If the service file exists, it returns an object with the web service path, name, and route.
   * If not found, it returns null.
   *
   * @param array $urlElements The URL elements to search for.
   * @return object|null An object with web service details or null if not found.
   */
  public static function findWebService(array $urlElements): ?object
  {

    if (array_key_exists($urlElements[0], self::$maps) == false) return null;

    $mapdata = self::$maps[$urlElements[0]];

    $basePath = "{$mapdata->modulepath}/{$mapdata->routes_basepath}";

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
      }
    }


    return null;
  }

  /**
   * Lists all migration files for the loaded modules.
   *
   * @return array An array of migration file paths.
   */
  public static function listMigrations(?string $filterModule = null): array
  {

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


    return $paths;
  }

  /**
   * Lists all seed files for the loaded modules.
   *
   * @return array An array of seed file paths.
   */
  public static function listSeeds(?string $filterModule = null): array
  {
    $paths = [];

    foreach (self::$maps as $modName => $mapdata) {
      if (!empty($filterModule) && $modName != $filterModule) continue;

      $basepath = "{$mapdata->modulepath}/{$mapdata->dbseeds_basepath}";

      $paths[$modName] = [];

      if (is_dir($basepath)) {
        $dirHandle = opendir($basepath);
        while (($f = readdir($dirHandle)) !== false) {
          if (!Utils::regexTest('/^\d{10}_/', $f)) continue;

          $filepath = "{$basepath}/{$f}";

          // Combine $dirPath and $file to retrieve fully qualified class path:
          if ($f != '.' && $f != '..' && is_file($filepath)) {
            // Find the seed name from the file path:
            $sepIdx = strpos(basename($filepath), '_');
            $mName = substr(basename($filepath), $sepIdx + 1, strrpos(basename($filepath), '.') - $sepIdx - 1);
            $mName = str_replace('-', ' ', $mName);
            $mName = ucwords($mName);

            $paths[$modName][] = (object) [
              'module' => $modName,
              'filepath' => $filepath,
              'skey' => hash('sha256', file_get_contents($filepath)),
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

    return $paths;
  }

  /**
   * Finds a module by its path.
   * The path should be in the format "module/itemPath/itemName".
   * If the module exists, it returns an object with module details and item path/name.
   * If not found, it returns null.
   *
   * @param string $path The path to the module.
   * @return object|null An object with module details or null if not found.
   */
  private static function findModuleByPath(string $path): ?object
  {

    // Check for invalid module path:
    if (strpos($path, '/') === false) {

      return null;
    }
    // Break module path into pieces:
    $pathData = explode('/', $path);
    if ($pathData[0] == '') unset($pathData[0]);

    // Find the module name
    $modName = array_splice($pathData, 0, 1);
    $modName = $modName[0];

    if (empty($modName) || !array_key_exists($modName, self::$maps))
      return null;

    return (object)[
      ...(array) self::$maps[$modName],
      'itemPath' => implode('/', $pathData),
      'itemName' => end($pathData)
    ];
  }

  /**
   * Maps all the modules inside the modules directory.
   *
   * This method scans the module's directory structure and creates a mapping
   * of all relevant directories and files for later use.
   */
  private static function mapModules(): void
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
        'dbseeds_basepath' => @$moddata['DBSEEDS_BASEPATH'] ?: 'dbseeds',
      ];

      unset($moddata);
    }
  }

  /**
   * Loads all event listeners for the loaded modules.
   */
  private static function loadModEventListeners(): void
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
