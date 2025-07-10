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

/**
 * Class LogAny
 *
 * This class represents a logging event that can be used to log any message with a specific log name and datetime.
 * It extends the base Event class and provides additional functionality for logging events.
 *
 * @package SplitPHP\Events
 */
class LogAny extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'log.any';

  /**
   * @var string $datetime
   * The datetime when the log event occurred.
   */
  private string $datetime;

  /**
   * @var string $logname
   * The name of the log file where the message will be logged.
   */
  private string $logname;

  /**
   * @var mixed $logmsg
   * The message to be logged. It can be any type of data.
   */
  private $logmsg;

  /**
   * LogAny constructor.
   * Initializes the event with the specified datetime, log name, and log message.
   *
   * @param string $datetime The datetime when the log event occurred.
   * @param string $logname The name of the log file where the message will be logged.
   * @param mixed $logmsg The message to be logged. It can be any type of data.
   */
  public function __construct(string $datetime, string $logname, $logmsg)
  {
    $this->datetime = $datetime;
    $this->logname = $logname;
    $this->logmsg = $logmsg;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string The string representation of the event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', Log Name: ' . $this->logname . ')';
  }

  /**
   * Returns the datetime when the log event occurred.
   *
   * @return string The datetime of the log event.
   */
  public function getDatetime(): string
  {
    return $this->datetime;
  }

  /**
   * Returns the name of the log file where the message will be logged.
   *
   * @return string The name of the log file.
   */
  public function getLogName(): string
  {
    return $this->logname;
  }

  /**
   * Returns the message to be logged.
   *
   * @return mixed The message to be logged.
   */
  public function getLogMsg()
  {
    return $this->logmsg;
  }

  /**
   * Returns the full path to the log file.
   *
   * @return string The full path to the log file.
   */
  public function getLogFilePath(): string
  {
    return MAINAPP_PATH . '/log/' . $this->logname . '.log';
  }

  /**
   * Returns the name of the log file including the .log extension.
   *
   * @return string The name of the log file.
   */
  public function getLogFileName(): string
  {
    return $this->logname . '.log';
  }

  /**
   * Returns the full path to the log file including the directory and file name.
   *
   * @return string The full path to the log file.
   */
  public function getLogFileFullPath(): string
  {
    return $this->getLogFilePath() . '/' . $this->getLogFileName();
  }

  /**
   * Returns the log event information as an array.
   *
   * @return array The log event information.
   */
  public function info(): array
  {
    return [
      'datetime' => $this->getDatetime(),
      'logname' => $this->getLogName(),
      'logmsg' => $this->getLogMsg(),
      'logfile' => $this->getLogFileFullPath()
    ];
  }
}
