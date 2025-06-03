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

use SplitPHP\Database\DbConnections;

use Exception;

class EventListener extends Service
{
  private static $events = null;
  private static $listeners = [];

  public final function __construct()
  {
    // Find all events that exists and load them into self::$events array.
    if (is_null(self::$events))
      self::discoverEvents();

    // Invoke Service's contructor:
    parent::__construct();
  }

  protected final function addEventListener(string $evtName, callable $callback)
  {
    $evtId = "evt-" . uniqid() . "-" . $evtName;
    self::$listeners[$evtId] = (object) [
      'evtName' => $evtName,
      'callback' => $callback
    ];

    return $evtId;
  }

  public static final function removeEventListener($evtId)
  {
    unset(self::$listeners[$evtId]);
  }

  public static final function eventRemoveListeners($evtName)
  {
    foreach (self::$listeners as $key => $listener) {
      if (strpos($key, $evtName) !== false)
        unset(self::$listeners[$key]);
    }
  }

  public static final function triggerEvent(string $evtName, array $data = [])
  {
    if (is_null(self::$events) || empty(self::$listeners)) return;

    try {
      if (!array_key_exists($evtName, self::$events)) self::discoverEvents();

      $evt = self::$events[$evtName];

      $evtObj = ObjLoader::load($evt->filePath, args: $data);
      if (is_array($evtObj)) throw new Exception("Event files cannot contain more than 1 class or namespace.");

      if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on")
        DbConnections::retrieve('main')->startTransaction();

      foreach (self::$listeners as $key => $listener) {
        if (strpos($key, $evtName) !== false) {
          $callback = $listener->callback;
          call_user_func_array($callback, [$evtObj]);
        }
      }

      if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on")
        DbConnections::retrieve('main')->commitTransaction();
    } catch (Exception $exc) {
      if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on" && DbConnections::check('main'))
        DbConnections::retrieve('main')->rollbackTransaction();

      if (APPLICATION_LOG == "on") {
        Helpers::Log()->error('event_error', $exc);
      }

      $status = self::userFriendlyErrorStatus($exc);
      http_response_code($status);
      header('Content-Type: application/json');
      echo json_encode([
        "error" => true,
        "user_friendly" => $status !== 500,
        "message" => $exc->getMessage(),
        "request" => System::$request,
        "payload" => $_REQUEST,
      ]);
      die;
    }
  }

  private static function discoverEvents()
  {
    self::$events = [];

    $eventFiles = [
      // Built-in Events:
      ...self::listCoreEventFiles(),
      // User-defined Events:
      ...ModLoader::listEventFiles(),
      ...AppLoader::listEventFiles()
    ];

    foreach ($eventFiles as $filePath) {
      $content = file_get_contents($filePath);
      if (empty($content)) continue;

      // Use regex to extract the class name
      if (preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)\b/m', $content, $matches)) {
        $className = $matches[1];
      }
      if (empty($className)) continue;

      // Match the EVENT_NAME constant
      $eventName = null;
      if (preg_match('/const\s+EVENT_NAME\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $eventNameMatches)) {
        $eventName = $eventNameMatches[1];
      }

      if (empty($eventName)) {
        throw new Exception("Event class {$className} must implement a public constant 'EVENT_NAME' with a valid name for it");
      }

      self::$events[$eventName] = (object) [
        'evtName' => $eventName,
        'filePath' => $filePath,
        'className' => $className
      ];
    }
  }

  private static function listCoreEventFiles()
  {
    $dirPath = ROOT_PATH . "/core/events/";
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

  /** 
   * Returns an integer representing a specific http status code for predefined types of exceptions. Defaults to 500.
   * 
   * @param Exception $exc
   * @return integer
   */
  private static function userFriendlyErrorStatus(Exception $exc)
  {
    switch ($exc->getCode()) {
      case (int) VALIDATION_FAILED:
        return 422;
        break;
      case (int) BAD_REQUEST:
        return 400;
        break;
      case (int) NOT_AUTHORIZED:
        return 401;
        break;
      case (int) NOT_FOUND:
        return 404;
        break;
      case (int) PERMISSION_DENIED:
        return 403;
        break;
      case (int) CONFLICT:
        return 409;
        break;
    }

    return 500;
  }
}
