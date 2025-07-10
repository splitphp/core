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
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use SplitPHP\Database\Database;

/**
 * Class System
 * 
 * This is the main class, the entry point of the application.
 *
 * @package SplitPHP
 */
final class System
{
  /**
   * @var string $webservicePath
   * Stores the name of the WebService which is being executed in the current execution.
   */
  public static $request = null;

  /**
   * @var string $cliPath
   * Stores the name of the CLI which is being executed in the current execution.
   */
  public static $execution = null;

  /** 
   * This is the constructor of System class. It initiate the $globals property, create configuration constants, load and runs 
   * extensions, load custom exception classes, include the main classes, then executes the request.
   * 
   * @return System 
   */
  public final function __construct($cliArgs = [])
  {
    // Define root path constant:
    define('ROOT_PATH', dirname(__DIR__, 2));
    define('CORE_PATH', dirname(__DIR__));

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

    // Include kernel:
    require_once __DIR__ . "/class.objloader.php";
    require_once __DIR__ . "/class.modloader.php";
    require_once __DIR__ . "/class.apploader.php";
    require_once __DIR__ . "/class.service.php";
    require_once __DIR__ . "/class.eventlistener.php";
    require_once __DIR__ . "/class.eventdispatcher.php";
    require_once __DIR__ . "/class.event.php";
    require_once __DIR__ . "/class.utils.php";
    require_once __DIR__ . "/class.helpers.php";
    require_once __DIR__ . "/class.exceptionhandler.php";
    require_once CORE_PATH . "/database/class.database.php";

    // Init basic database connections:
    if (DB_CONNECT == 'on') {
      $this->startDatabase();
    }

    AppLoader::init();
    ModLoader::init();

    $this->serverLogCleanUp();

    if (empty($cliArgs)) $this->executeRequest();
    else {
      require_once __DIR__ . "/class.execution.php";
      self::executeCommand(new Execution($cliArgs));
    }

    if (DB_CONNECT == "on")
      Database::removeCnn('main');
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString(): string
  {
    $request = self::$request;
    $execution = self::$execution;

    return "class:" . __CLASS__ . "(Action:{$execution}, Request:{$request})";
  }

  /** 
   * Using the information stored in the received Execution object, set and run a specific Cli, passing along the command 
   * and arguments specified in that Execution object.
   * 
   * @param Execution $execution
   * @return void
   */
  public static function executeCommand(Execution $execution): void
  {
    require_once __DIR__ . "/class.cli.php";

    self::$execution = $execution;

    $CliObj = ObjLoader::load($execution->getCli()->path);
    if (is_array($CliObj)) throw new Exception("CLI files cannot contain more than 1 class or namespace.");

    EventDispatcher::dispatch(fn() => call_user_func_array(array($CliObj, 'execute'), $execution->getArgs()), 'command.before', [$execution]);
  }

  /** 
   * Requires all files located at the specified directory.
   * 
   * @param string $dir
   * @throws Exception
   */
  public static function requireDir(string $dir, $recursive = false): void
  {
    if (!is_dir($dir))
      throw new Exception("Directory not found: $dir");

    if ($handle = opendir($dir)) {
      while (($file = readdir($handle)) !== false) {
        if ($file != '.' && $file != '..' && $file != '.gitkeep') {
          if (is_dir($dir . $file) && $recursive) {
            self::requireDir($dir . $file . '/', true);
          } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
            require_once $dir . $file;
          }
        }
      }
      closedir($handle);
    } else {
      throw new Exception("Cannot open directory: $dir");
    }
  }

  /**
   * Inspect any callable (Closure, function, static or instance method)
   * and return a list of its parameters with only name + type.
   *
   * @param callable $callable
   * @return array<int, array{name: string, type: string|null}>
   */
  public static function getCallableParams(callable $callable): array
  {
    // pick the right Reflection…
    if (is_array($callable)) {
      $ref = new ReflectionMethod($callable[0], $callable[1]);
    } elseif (is_string($callable) && strpos($callable, '::') !== false) {
      list($class, $method) = explode('::', $callable, 2);
      $ref = new ReflectionMethod($class, $method);
    } else {
      $ref = new ReflectionFunction($callable);
    }

    $output = [];
    foreach ($ref->getParameters() as $param) {
      $type = $param->getType();
      $output[] = [
        'name' => $param->getName(),
        'type' => ($type instanceof ReflectionNamedType)
          ? $type->getName()
          : null,
      ];
    }

    return $output;
  }

  /** 
   * Setup CORS policy and responds pre-flight requests:
   * 
   * @return void 
   */
  private function setCORS(): void
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
  private function setErrorHandling(): void
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

  /**
   * Starts the database connections.
   * 
   * @return void
   */
  private function startDatabase(): void
  {
    // For Main user:
    Database::getCnn('main', [
      'host' => DBHOST,
      'port' => DBPORT,
      'name' => DBNAME,
      'user' => DBUSER_MAIN,
      'pass' => DBPASS_MAIN
    ]);

    // For Readonly user:
    Database::getCnn('readonly', [
      'host' => DBHOST,
      'port' => DBPORT,
      'name' => DBNAME,
      'user' => DBUSER_READONLY,
      'pass' => DBPASS_READONLY
    ]);
  }

  /** 
   * Using the information stored in the received Request object, set and run a specific WebService, passing along the route 
   * and data specified in that Request object.
   * 
   * @return void
   */
  private function executeRequest(): void
  {
    define('HTTP_PROTOCOL', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://"));
    define('URL_APPLICATION', HTTP_PROTOCOL . $_SERVER['HTTP_HOST']);

    require_once __DIR__ . "/class.request.php";
    require_once __DIR__ . "/class.webservice.php";

    $req = new Request($_SERVER["REQUEST_URI"]);
    self::$request = $req;

    EventDispatcher::dispatch(function () use (&$req) {
      call_user_func_array([$req->getWebService(), 'execute'], [$req]);
    }, 'request.before', [$req]);
  }

  /** 
   * Loads and runs all scripts located at /core/extensions. It is used to add extra functionalities to PHP's interface, like $_PUT 
   * superglobal, for instance.
   * 
   * @return void 
   */
  private function loadExtensions(): void
  {
    self::requireDir(ROOT_PATH . '/core/extensions/');
  }

  /** 
   * Includes all custom exception classes located at /core/exceptions.
   * 
   * @return void 
   */
  private function loadExceptions(): void
  {
    self::requireDir(CORE_PATH . '/exceptions/', true);
  }

  /**
   * Load a .env file into environment variables.
   *
   * @param string $path Path to the .env file
   * @return void
   */
  private function loadEnv(string $path): void
  {
    if (! is_readable($path)) {
      return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $raw   = [];

    // === PASS 1: parse raw values (keys and values, strip comments) ===
    // Regex matches:
    //  1) key
    //  2) single-quoted inner text
    //  3) double-quoted inner text (allowing \" and \\ escapes)
    //  4) unquoted text (up to first # or ;)
    // then strips any trailing comment.
    $re = '/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*' .
      "(?:'([^']*)'|\"((?:\\\\.|[^\"])*)\"|([^#;]*?))" .
      '(?:\s*[#;].*)?$/';

    foreach ($lines as $line) {
      if (! preg_match($re, $line, $m)) {
        continue;
      }

      $name = $m[1];

      // pick the correct capture group
      if ($m[2] !== '') {
        // single-quoted: inner content
        $value = $m[2];
      } elseif ($m[3] !== '') {
        // double-quoted: unescape \" and \\  
        $value = str_replace(['\"', '\\\\'], ['"', '\\'], $m[3]);
      } else {
        // unquoted: trim whitespace
        @$value = trim($m[4]);
      }

      // strip one matching pair of quotes around the entire value
      if (strlen($value) > 1) {
        $first = $value[0];
        $last  = substr($value, -1);
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
          $value = substr($value, 1, -1);
        }
      }

      // un-escape literal "\n"
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
          // fallback to existing env or empty string
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
  private function setConfigsFromEnv(): void
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
    define('DB_WORK_AROUND_FACTOR', getenv('DB_WORK_AROUND_FACTOR') ?? 5);
    define('CACHE_DB_METADATA', getenv('CACHE_DB_METADATA'));
    define('DB_CHARSET', getenv('DB_CHARSET') ?: "utf8");

    // Define System configuration constants:
    define('APPLICATION_NAME', getenv('APPLICATION_NAME'));
    define('DEFAULT_ROUTE', getenv('DEFAULT_ROUTE'));
    define('DEFAULT_TIMEZONE', getenv('DEFAULT_TIMEZONE'));
    define('HANDLE_ERROR_TYPES', getenv('HANDLE_ERROR_TYPES'));
    define('APPLICATION_LOG', getenv('APPLICATION_LOG'));
    define('PRIVATE_KEY', getenv('PRIVATE_KEY'));
    define('PUBLIC_KEY', getenv('PUBLIC_KEY'));
    define('ALLOW_CORS', getenv('ALLOW_CORS'));
    define('MAINAPP_PATH', '/' . trim(getenv('MAINAPP_PATH') ?: 'application', '/'));
    define('MODULES_PATH', '/' . trim(getenv('MODULES_PATH') ?: 'modules', '/'));
    define('MAX_LOG_ENTRIES', getenv('MAX_LOG_ENTRIES') ?: 5);
    define('APP_ENV', getenv('SPLITPHP_ENV') ?: getenv('APP_ENV') ?: 'production');
    ini_set('memory_limit', '1024M');
  }

  /** 
   * Remove entries from server's log until it reach the MAX_LOG_ENTRIES limit. The cleaning-up remove the oldest entries and leave the newer:
   * 
   * @return void 
   */
  private function serverLogCleanUp(): void
  {
    $path = ROOT_PATH . '/log/server.log';

    if (file_exists($path)) {
      $pattern = '/^\[\d{1,2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} UTC\].*?(?=^\[\d{1,2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} UTC\]|\z)/ms';

      $rawString = file_get_contents($path);

      preg_match_all($pattern, $rawString, $matches);

      if (count($matches[0]) > MAX_LOG_ENTRIES) {
        $rawData = array_slice($matches[0], ((MAX_LOG_ENTRIES - 1) * -1));
        file_put_contents($path, implode("", $rawData));
      }
    }
  }
}
