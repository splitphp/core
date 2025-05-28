<?php

namespace engine;

use \DirectoryIterator;

class ModLoader
{
  private static $maps = [];

  public static function init()
  {
    self::mapModules();
    self::loadModEventListeners();
  }

  public static function getMaps()
  {
    return self::$maps;
  }

  public static function loadService(string $path)
  {
    $metadata = self::findModuleByPath($path);
    if (empty($metadata)) return null;

    // From its map, try to find its service
    $servicePath = "{$metadata->modulepath}/{$metadata->services_basepath}/{$metadata->itemPath}.php";
    if (file_exists($servicePath))
      return ObjLoader::load($servicePath, $metadata->itemName);

    return null;
  }

  public static function loadTemplate(string $path)
  {
    $metadata = self::findModuleByPath($path);
    if (empty($metadata)) return null;

    $tplPath = "{$metadata->modulepath}/{$metadata->templates_basepath}/{$metadata->itemPath}.php";
    if (!file_exists($tplPath)) return null;

    ob_start();
    include $tplPath;
    return ob_get_clean();
  }

  public static function loadSQL(string $sql)
  {
    $metadata = self::findModuleByPath($sql);
    if (empty($metadata)) return $sql;

    $sqlPath = "{$metadata->modulepath}/{$metadata->sql_basepath}/{$metadata->itemPath}.php";
    if (!file_exists($sqlPath)) return $sql;

    return file_get_contents($sqlPath);
  }

  private static function findModuleByPath(string $path)
  {
    // Check for invalid module path:
    if (strpos($path, '/') === false) return null;

    // Break module path into pieces:
    $pathData = explode('/', $path);
    if ($pathData[0] == '') unset($pathData[0]);

    // Find the module name
    $modName = array_splice($pathData, 0, 1);
    if (!array_key_exists($modName, self::$maps))
      return null;

    return (object)[
      ...self::$maps[$modName],
      'itemPath' => implode('/', $pathData),
      'itemName' => @$className = end($pathData)
    ];
  }

  private static function mapModules()
  {
    foreach (new DirectoryIterator(MODULES_PATH) as $mod) {
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
        'modulepath' => $dirPath,
        'routes_basepath' => $moddata['ROUTES_BASEPATH'] ?? 'routes',
        'services_basepath' => $moddata['SERVICES_BASEPATH'] ?? 'services',
        'templates_basepath' => $moddata['TEMPLATES_BASEPATH'] ?? 'templates',
        'commands_basepath' => $moddata['COMMANDS_BASEPATH'] ?? 'commands',
        'eventlisteners_basepath' => $moddata['EVENTLISTENERS_BASEPATH'] ?? 'eventlisteners',
        'events_basepath' => $moddata['EVENTS_BASEPATH'] ?? 'events',
        'sql_basepath' => $moddata['SQL_BASEPATH'] ?? 'sql',
        'dbmigrations_basepath' => $moddata['DBMIGRATION_BASEPATH'] ?? 'dbmigrations',
      ];
    }
  }

  private static function loadModEventListeners(){
    foreach(self::$maps as $mod){
      $lstPath = "{$mod->modulepath}/{$mod->eventlisteners_basepath}";
  
      if (is_dir($lstPath) && $dir = opendir($lstPath)) {
        while (($file = readdir($dir)) !== false) {
          if ($file == '.' || $file == '..') continue;
  
          $path = "{$lstPath}/{$file}";
  
          $content = file_get_contents($path);
  
          // Use regex to extract the class name
          if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
            $className = $matches[1];
          }
  
          if (!empty($className))
            ObjLoader::load($path, $className);
        }
      }
    }
  }
}
