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
  private bool $isError = false;
  /** 
   * Creates a log file under MAINAPP_PATH/log with the specified $logname, writing down $logmsg with the current datetime 
   * 
   * @param string $logname
   * @param mixed $logmsg
   * @param boolean $limit
   * @return void 
   */
  public function add(string $logname, $logmsg, $limit = true)
  {
    if (!$this->isError)
      EventDispatcher::dispatch(function () use ($logname, $logmsg, $limit) {
        if ($logname == 'server') throw new Exception("You cannot manually write data in server's log.");

        $path = ROOT_PATH . "/log/";

        if (!file_exists($path))
          mkdir($path, 0755, true);
        touch($path);
        chmod($path, 0755);

        if (is_array($logmsg) || (gettype($logmsg) == 'object' && $logmsg instanceof stdClass)) {
          $logmsg = json_encode($logmsg);
        }

        if (file_exists($path . $logname . '.log'))
          $currentLogData = array_filter(explode(str_repeat(PHP_EOL, 2), file_get_contents($path . $logname . '.log')));
        else $currentLogData = [];

        if (count($currentLogData) >= MAX_LOG_ENTRIES && $limit) {
          $currentLogData = array_slice($currentLogData, ((MAX_LOG_ENTRIES - 1) * -1));
          $currentLogData[] = "[" . date('Y-m-d H:i:s') . "] - " . $logmsg;
          file_put_contents($path . $logname . '.log', implode(str_repeat(PHP_EOL, 2), $currentLogData) . str_repeat(PHP_EOL, 2));
        } else {
          $log = fopen($path . $logname . '.log', 'a');
          fwrite($log, "[" . date('Y-m-d H:i:s') . "] - " . $logmsg . str_repeat(PHP_EOL, 2));
          fclose($log);
        }
      }, 'log.any', [
        'datetime' => date('Y-m-d H:i:s'),
        'logname' => $logname,
        'logmsg' => $logmsg,
      ]);
  }

  /** 
   * Creates a log file under MAINAPP_PATH/log with the specified $logname, with specific information about the exception received in $exc. 
   * Use $info to add extra information on the log.
   * 
   * @param string $logname
   * @param Throwable $exc
   * @param array $info = []
   * @return void 
   */
  public function error(string $logname, Throwable $exc, array $info = [])
  {
    $this->isError = true;
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
   * Using the information of the exception received in $exc, and the extra $info, builds a fittable 
   * error log object to be used as $logmsg.  
   * 
   * @param Throwable $exc
   * @param array $info
   * @return void 
   */
  private function exceptionBuildLog(Throwable $exc, array $info)
  {
    return (object) [
      "datetime" => date('Y-m-d H:i:s'),
      "message" => $exc->getMessage(),
      "file" => $exc->getFile(),
      "line" => $exc->getLine(),
      "request" => System::$request,
      "execution" => System::$execution,
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
