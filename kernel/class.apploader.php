<?php

namespace engine;

use \DirectoryIterator;

class AppLoader
{
  private const APP_FULLPATH = ROOT_PATH . '/' . MAINAPP_PATH;
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

  public static function loadTemplate(string $path)
  {
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
      while (($f = readdir($dirHandle)) !== false)
        // Combine $dirPath and $file to retrieve fully qualified class path:
        if ($dirPath . $f != '.' && $dirPath . $f != '..' && is_file($dirPath . $f))
          $paths[] = $dirPath . $f;

      closedir($dirHandle);
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
      'routes_basepath' => $mapdata['ROUTES_BASEPATH'] ?? 'routes',
      'services_basepath' => $mapdata['SERVICES_BASEPATH'] ?? 'services',
      'templates_basepath' => $mapdata['TEMPLATES_BASEPATH'] ?? 'templates',
      'commands_basepath' => $mapdata['COMMANDS_BASEPATH'] ?? 'commands',
      'eventlisteners_basepath' => $mapdata['EVENTLISTENERS_BASEPATH'] ?? 'eventlisteners',
      'events_basepath' => $mapdata['EVENTS_BASEPATH'] ?? 'events',
      'sql_basepath' => $mapdata['SQL_BASEPATH'] ?? 'sql',
      'dbmigrations_basepath' => $mapdata['DBMIGRATION_BASEPATH'] ?? 'dbmigrations',
    ];
  }

  private static function loadAppEventListeners()
  {
    $mapdata = self::$map;

    $lstPath = "{$mapdata->mainapp_path}/{$mapdata->eventlisteners_basepath}";

    foreach (new DirectoryIterator($lstPath) as $lst) {
      // skip "." and ".." and anything that is a directory
      if ($lst->isDot() || $lst->isDir()) continue;

      $path = $lst->getPathname();
      ObjLoader::load($path);
    }
  }
}
