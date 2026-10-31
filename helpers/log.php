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

namespace SplitPHP\Helpers;

use Exception;
use \stdClass;
use SplitPHP\System;
use SplitPHP\EventDispatcher;
use Throwable;

class Log
{
  /** 
   * Creates a log file under ROOT_PATH/log with the specified $logname, writing down $logmsg with the current datetime 
   * 
   * @param string $logname
   * @param mixed $logmsg
   * @param boolean $limit
   * @return void 
   */
  public function common(string $logname, $logmsg, $limit = true)
  {
    EventDispatcher::dispatch(function () use ($logname, $logmsg, $limit) {
      $this->add($logname, $logmsg, $limit);
    }, 'log.common', [
      'datetime' => date('Y-m-d H:i:s'),
      'logname' => $logname,
      'logmsg' => $logmsg,
    ]);
  }

  /** 
   * Creates a log file under ROOT_PATH/log with the specified $logname, with specific information about the exception received in $exc. 
   * Use $info to add extra information on the log.
   * 
   * @param string $logname
   * @param Throwable $exc
   * @param array $info = []
   * @return void 
   */
  public function error(string $logname, Throwable $exc, array $info = [])
  {
    $logmsg = $this->exceptionBuildLog($exc, $info);
    EventDispatcher::dispatch(function () use ($logname, $logmsg) {
      $this->add($logname, $logmsg);
    }, 'log.error', [
      'datetime' => date('Y-m-d H:i:s'),
      'logname' => $logname,
      'logmsg' => $logmsg,
      'exception' => $exc,
      'info' => $info,
    ]);
  }

  /** 
   * Creates a log file under ROOT_PATH/log with the specified $logname, writing down $logmsg with the current datetime 
   * 
   * @param string $logname
   * @param mixed $logmsg
   * @return void 
   */
  public function add(string $logname, $logmsg, $limit = true)
  {
    if ($logname == 'server') throw new Exception("You cannot manually write data in server's log.");

    $path = ROOT_PATH . "/log/";

    if (!file_exists($path))
      mkdir($path, 0755, true);
    touch($path);
    chmod($path, 0755);

    if (is_array($logmsg) || (gettype($logmsg) == 'object' && $logmsg instanceof stdClass)) {
      $logmsg = json_encode($logmsg);
    }

    if (file_exists($path . $logname . '.log')) {
      $rawLogData = file_get_contents($path . $logname . '.log');
      // file_get_contents() returns false when the file exists but is not readable (e.g. owned by
      // root while php-fpm runs as www-data). Treat it as an empty log instead of exploding a bool.
      $currentLogData = $rawLogData === false ? [] : array_filter(explode(str_repeat(PHP_EOL, 2), $rawLogData));
    } else $currentLogData = [];

    // Logging is a side-channel: it must NEVER take down the request/command that
    // triggered it. If the log file cannot be opened for writing (e.g. it was
    // created by another OS user — a console command running as root leaves
    // curl.log owned by root, and php-fpm's www-data can no longer append to it),
    // fopen()/file_put_contents() return false. Passing that false into fwrite()
    // raises a TypeError in PHP 8, which previously bubbled up and turned a working
    // request into a 502. Fail the write silently (diagnosed via error_log) instead.
    $logFile = $path . $logname . '.log';

    if (count($currentLogData) >= MAX_LOG_ENTRIES && $limit) {
      $currentLogData = array_slice($currentLogData, ((MAX_LOG_ENTRIES - 1) * -1));
      $currentLogData[] = "[" . date('Y-m-d H:i:s') . "] - " . $logmsg;
      if (@file_put_contents($logFile, implode(str_repeat(PHP_EOL, 2), $currentLogData) . str_repeat(PHP_EOL, 2)) === false) {
        error_log("[Log::add] Could not write to log file '{$logFile}'.");
      }
    } else {
      // Logging is best-effort: a failure to open the log file (e.g. a root-owned file that
      // php-fpm cannot append to as www-data) must NEVER surface as a fatal TypeError in fwrite()
      // and abort the user's request. Fall back to error_log() so the entry is not lost.
      $log = @fopen($logFile, 'a');
      if ($log === false) {
        error_log("[Log::add] Could not open log file '{$logFile}' for writing. Original message: " . $logmsg);
        return;
      }
      // Widen perms so a log file created by one OS user (e.g. a root console
      // command) stays appendable by php-fpm's www-data on the next request.
      @chmod($logFile, 0666);
      fwrite($log, "[" . date('Y-m-d H:i:s') . "] - " . $logmsg . str_repeat(PHP_EOL, 2));
      fclose($log);
    }
  }

  /** 
   * Using the information of the exception received in $exc, and the extra $info, builds a fittable 
   * error log object to be used as $logmsg.  
   * 
   * @param Throwable $exc
   * @param array $info
   * @return void 
   */
  public function exceptionBuildLog(Throwable $exc, array $info)
  {
    return (object) [
      "datetime" => date('Y-m-d H:i:s'),
      "message" => $exc->getMessage(),
      "file" => $exc->getFile(),
      "line" => $exc->getLine(),
      "request" => System::$currentRequest,
      "execution" => System::$currentExecution,
      "payload" => $_REQUEST ?? null,
      "cookie" => $_COOKIE ?? null,
      "session" => $_SESSION ?? null,
      "server" => $_SERVER ?? null,
      "info" => $info,
      "stack_trace" => $exc->getTrace(),
      "previous_exception" => ($exc->getPrevious() != null ? $this->exceptionBuildLog($exc->getPrevious(), []) : null),
    ];
  }
}
