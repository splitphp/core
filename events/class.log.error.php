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

namespace SplitPHP\Events;

use SplitPHP\Event;
use Throwable;

class LogError implements Event
{
  public const EVENT_NAME = 'log.error';

  private string $datetime;
  private string $logname;
  private $logmsg;
  private Throwable $exception;
  private array $info;

  public function __construct(string $datetime, string $logname, $logmsg, Throwable $exception, array $info = [])
  {
    $this->datetime = $datetime;
    $this->logname = $logname;
    $this->logmsg = $logmsg;
    $this->exception = $exception;
    $this->info = $info;
  }

  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', Log Name: ' . $this->logname . ', Exception: ' . $this->exception->getMessage() . ')';
  }

  public function getName(): string
  {
    return self::EVENT_NAME;
  }

  public function getDatetime()
  {
    return $this->datetime;
  }

  public function getLogName()
  {
    return $this->logname;
  }

  public function getLogMsg()
  {
    return $this->logmsg;
  }

  public function getException()
  {
    return $this->exception;
  }

  public function getLogFilePath()
  {
    return MAINAPP_PATH . '/log/' . $this->logname . '.log';
  }

  public function getLogFileName()
  {
    return $this->logname . '.log';
  }

  public function getLogFileFullPath()
  {
    return $this->getLogFilePath() . '/' . $this->getLogFileName();
  }

  public function info()
  {
    return [
      'datetime' => $this->getDatetime(),
      'logname' => $this->getLogName(),
      'logmsg' => $this->getLogMsg(),
      'logfile' => $this->getLogFileFullPath(),
      'exception' => $this->getException(),
      'info' => $this->info
    ];
  }
}
