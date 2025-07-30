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

use SplitPHP\Database\Database;
use SplitPHP\Exceptions\EventException;
use Exception;
use Throwable;

/**
 * Class EventDispatcher
 *
 * This class is responsible for dispatching events and notifying registered listeners.
 *
 * @package SplitPHP
 */
final class EventDispatcher extends Service
{
  /**
   * @var array|null $events
   * Stores the discovered events in the application.
   * The keys are event names and the values are objects containing event details.
   */
  private static $events = null;

  /**
   * EventDispatcher constructor.
   * Initializes the event dispatcher by discovering events.
   */
  public static final function init()
  {
    // Find all events that exists and load them into self::$events array.
    if (is_null(self::$events))
      self::discoverEvents();
  }

  /**
   * Triggers an event and notifies all registered listeners.
   *
   * @param callable $fn The function that effectively triggers the event.
   * @param string $evtName The name of the event to trigger.
   * @param array $data Optional data to pass to the event listeners.
   */
  public static final function dispatch(callable $dispatcherFn, string $evtName, array $data = []): void
  {
    $listeners = EventListener::getListeners();

    if (empty(self::$events) || empty($listeners)) {
      $dispatcherFn();
      return;
    }

    if (!array_key_exists($evtName, self::$events)) self::discoverEvents();

    if (empty(self::$events[$evtName])) {
      $dispatcherFn();
      return;
    }

    $evt = self::$events[$evtName];
    $evtObj = ObjLoader::load(filepath: $evt->filePath, args: $data);
    if (is_array($evtObj)) throw new Exception("Event files cannot contain more than 1 class or namespace.");

    if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on")
      Database::getCnn('main')->startTransaction();

    try {
      foreach ($listeners as $key => $listener) {
        if (strpos($key, $evtName) !== false) {
          $callback = $listener->callback;
          call_user_func_array($callback, [&$evtObj]);

          if ($evtObj->shouldPropagate() === false) {
            break; // Stop further propagation if the event object indicates to stop
          }
        }
      }
    } catch (Throwable $exc) {
      $newExc = new EventException($exc, $evtObj ?? null);
      ExceptionHandler::handle(
        exception: $newExc,
        request: System::$currentRequest ?? null,
        execution: System::$currentExecution ?? null
      );
    }

    if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on")
      Database::getCnn('main')->commitTransaction();

    if ($evtObj->shouldPropagate()) $dispatcherFn();
  }

  /**
   * Discovers events in the application.
   */
  private static function discoverEvents(): void
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

  /**
   * Lists all core event files in the framework.
   *
   * @return array An array of core event file paths.
   */
  private static function listCoreEventFiles(): array
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
}
