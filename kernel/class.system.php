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

use Exception;

/**
 * Class System
 * 
 * This is the main class, the entry point of the application.
 *
 * @package SplitPHP
 */
class System
{
  /**
   * @var string $webservicePath
   * Stores the name of the WebService which is being executed in the current execution.
   */
  public static $request = "";

  /**
   * @var string $cliPath
   * Stores the name of the CLI which is being executed in the current execution.
   */
  public static $action = "";

  /** 
   * This is the constructor of System class. It initiate the $globals property, create configuration constants, load and runs 
   * extensions, load custom exception classes, include the main classes, then executes the request.
   * 
   * @return System 
   */
  public final function __construct($cliArgs = [])
  {
    // Define root path constant:
    define('ROOT_PATH', dirname(__DIR__));

    // Set error handling:
    $this->setErrorHandling();

    // Setting up general configs:
    $this->loadEnv(ROOT_PATH . '/.env');
    $this->setConfigsFromEnv();

    // Set CORS
    if (ALLOW_CORS == "on")
      $this->setCORS();

    // Set system's default timezone: 
    if (!empty(DEFAULT_TIMEZONE))
      date_default_timezone_set(DEFAULT_TIMEZONE);

    // Load extensions:
    $this->loadExtensions();
    $this->loadExceptions();

    // Including main classes:
    require_once __DIR__ . "/class.objloader.php";
    require_once __DIR__ . "/class.dbconnections.php";
    require_once __DIR__ . "/class.service.php";
    require_once __DIR__ . "/class.eventlistener.php";
    require_once __DIR__ . "/class.modloader.php";
    require_once __DIR__ . "/class.apploader.php";
    require_once __DIR__ . "/interface.event.php";
    require_once __DIR__ . "/class.utils.php";

    AppLoader::init();
    ModLoader::init();

    // Init basic database connections:
    if (DB_CONNECT == 'on') {
      $this->startDatabase();
    }

    $this->serverLogCleanUp();

    if (empty($cliArgs)) {
      define('HTTP_PROTOCOL', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://"));
      define('URL_APPLICATION', HTTP_PROTOCOL . $_SERVER['HTTP_HOST']);

      require_once __DIR__ . "/class.request.php";
      require_once __DIR__ . "/class.webservice.php";
      $this->executeRequest(new Request($_SERVER["REQUEST_URI"]));
    } else {
      require_once __DIR__ . "/class.action.php";
      require_once __DIR__ . "/class.cli.php";
      $this->executeCommand(new Action($cliArgs));
    }

    if (DB_CONNECT == "on")
      DbConnections::remove('main');
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString()
  {
    $request = self::$request;
    $action = self::$action;

    return "class:" . __CLASS__ . "(Action:{$action}, Request:{$request})";
  }

  /** 
   * Setup CORS policy and responds pre-flight requests:
   * 
   * @return void 
   */
  private function setCORS()
  {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day

    // Respond pre-flight requests:
    if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
      header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

      die;
    }
  }

  /** 
   * Setup MAINAPP_PATH/log directory and pre-create server.log file
   * 
   * @return void 
   */
  private function setErrorHandling()
  {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    $path = ROOT_PATH . "/log";
    if (!file_exists($path)) {
      mkdir($path);
      chmod($path, 0755);
    }

    $path .= "/server.log";
    if (!file_exists($path)) {
      touch($path);
      chmod($path, 0644);
    }

    ini_set('error_log', $path);
  }

  private function startDatabase()
  {
    // For Main user:
    DbConnections::retrieve('main', [
      DBHOST,
      DBPORT,
      DBNAME,
      DBUSER_MAIN,
      DBPASS_MAIN
    ]);

    // For Readonly user:
    DbConnections::retrieve('readonly', [
      DBHOST,
      DBPORT,
      DBNAME,
      DBUSER_READONLY,
      DBPASS_READONLY
    ]);
  }

  /** 
   * Using the information stored in the received Request object, set and run a specific WebService, passing along the route 
   * and data specified in that Request object.
   * 
   * @param Request $request
   * @return void
   */
  private function executeRequest(Request $request)
  {
    EventListener::triggerEvent('onRequest', [$request]);

    self::$request = $request;

    $webServiceObj = ObjLoader::load($request->getWebService()->path);
    if(is_array($webServiceObj)) throw new Exception("WebService files cannot contain more than 1 class or namespace.");
    $res = call_user_func_array(array($webServiceObj, 'execute'), $request->getArgs());
    EventListener::triggerEvent('afterResponded', [$res]);
  }

  /** 
   * Using the information stored in the received Action object, set and run a specific Cli, passing along the command 
   * and arguments specified in that Action object.
   * 
   * @param Action $action
   * @return void
   */
  private function executeCommand(Action $action)
  {
    EventListener::triggerEvent('beforeRunCommand', [$action]);
    self::$action = $action;

    $CliObj = ObjLoader::load($action->getCli()->path);
    if(is_array($CliObj)) throw new Exception("CLI files cannot contain more than 1 class or namespace.");
    call_user_func_array(array($CliObj, 'execute'), $action->getArgs());
  }

  /** 
   * Loads and runs all scripts located at /engine/extensions. It is used to add extra functionalities to PHP's interface, like $_PUT 
   * superglobal, for instance.
   * 
   * @return void 
   */
  private function loadExtensions()
  {
    if ($dir = opendir(__DIR__ . '/extensions/')) {
      while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') include_once __DIR__ . '/extensions/' . $file;
      }
    }
  }

  /** 
   * Includes all custom exception classes located at /engine/exceptions.
   * 
   * @return void 
   */
  private function loadExceptions()
  {
    if ($dir = opendir(__DIR__ . '/exceptions/')) {
      while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') include_once __DIR__ . '/exceptions/' . $file;
      }
    }
  }

  /**
   * Load a .env file into environment variables.
   *
   * @param string $path Path to the .env file
   * @return void
   */
  private function loadEnv(string $path): void
  {
    if (! file_exists($path) || ! is_readable($path)) {
      return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $raw = [];

    // === PASS 1: parse raw values ===
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
      }

      list($name, $value) = explode('=', $line, 2);
      $name  = trim($name);
      $value = trim($value);

      // strip only one layer of surrounding quotes
      $value = trim($value, "\"'");

      // un-escape literal \n
      $value = str_replace('\n', "\n", $value);

      $raw[$name] = $value;
    }

    // === PASS 2: resolve ${VAR} placeholders ===
    $env = [];
    foreach ($raw as $name => $value) {
      $env[$name] = preg_replace_callback(
        '/\$\{([A-Za-z0-9_]+)\}/',
        function ($m) use (&$raw) {
          $key = $m[1];
          if (array_key_exists($key, $raw)) {
            return $raw[$key];
          }
          // fallback to already-set env or empty string
          return getenv($key) ?: '';
        },
        $value
      );
    }

    // === PASS 3: write into PHP environment ===
    foreach ($env as $name => $value) {
      putenv("{$name}={$value}");
      $_ENV[$name]    = $value;
      $_SERVER[$name] = $value;
    }
  }

  /** 
   * Sets global constants from specific environment variables:
   * 
   * @return void 
   */
  private function setConfigsFromEnv()
  {
    // Define Database configuration constants:
    define('DB_CONNECT', getenv('DB_CONNECT'));
    define('DBNAME', getenv('DBNAME'));
    define('DBHOST', getenv('DBHOST'));
    define('DBPORT', getenv('DBPORT'));
    define('DBUSER_MAIN', getenv('DBUSER_MAIN'));
    define('DBPASS_MAIN', getenv('DBPASS_MAIN'));
    define('DBUSER_READONLY', getenv('DBUSER_READONLY'));
    define('DBPASS_READONLY', getenv('DBPASS_READONLY'));
    define('DBTYPE', getenv('DBTYPE'));
    define('DB_TRANSACTIONAL', getenv('DB_TRANSACTIONAL'));
    define('DB_WORK_AROUND_FACTOR', getenv('DB_WORK_AROUND_FACTOR'));
    define('CACHE_DB_METADATA', getenv('CACHE_DB_METADATA'));
    define('DB_CHARSET', getenv('DB_CHARSET') ?? "utf8");

    // Define System configuration constants:
    define('APPLICATION_NAME', getenv('APPLICATION_NAME'));
    define('DEFAULT_ROUTE', getenv('DEFAULT_ROUTE'));
    define('DEFAULT_TIMEZONE', getenv('DEFAULT_TIMEZONE'));
    define('HANDLE_ERROR_TYPES', getenv('HANDLE_ERROR_TYPES'));
    define('APPLICATION_LOG', getenv('APPLICATION_LOG'));
    define('PRIVATE_KEY', getenv('PRIVATE_KEY'));
    define('PUBLIC_KEY', getenv('PUBLIC_KEY'));
    define('ALLOW_CORS', getenv('ALLOW_CORS'));
    define('MAINAPP_PATH', getenv('MAINAPP_PATH') ?? '/application');
    define('MODULES_PATH', getenv('MODULES_PATH')) ?? '/modules';
    define('MAX_LOG_ENTRIES', getenv('MAX_LOG_ENTRIES') ?? 5);
    ini_set('memory_limit', '1024M');
  }

  /** 
   * Remove entries from server's log until it reach the MAX_LOG_ENTRIES limit. The cleaning-up remove the oldest entries and leave the newer:
   * 
   * @return void 
   */
  private function serverLogCleanUp()
  {
    $path = ROOT_PATH . '/log/server.log';

    if (file_exists($path)) {
      $pattern = '/^\[\d{2}\-[a-zA-Z]{3}\-\d{4}\s\d{2}\:\d{2}\:\d{2}\s[a-zA-Z]*\/[a-zA-Z_]*\]\s/m';
      $rawString = file_get_contents($path);

      preg_match_all($pattern, $rawString, $dates);
      $dates = $dates[0];

      $rawData = preg_split($pattern, $rawString, -1, PREG_SPLIT_NO_EMPTY);
      foreach ($rawData as $i => &$entry) {
        $entry = $dates[$i] . $entry;
      }

      if (count($rawData) > MAX_LOG_ENTRIES) {
        $rawData = array_slice($rawData, ((MAX_LOG_ENTRIES - 1) * -1));
        file_put_contents($path, implode("", $rawData));
      }
    }
  }
}
