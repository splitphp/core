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

/**
 * Class LogError
 *
 * This class represents an event that is triggered just before an error is logged by the system.
 * It extends the base Event class and provides additional functionality specific to logging errors.
 *
 * @package SplitPHP\Events
 */
class LogError extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'log.error';

  /**
   * @var string $datetime
   * The date and time when the log occurred.
   */
  private string $datetime;

  /**
   * @var string $logname
   * The name of the log file where the error is being logged.
   */
  private string $logname;

  /**
   * @var mixed $logmsg
   * The message that is being logged.
   * It can be a string or any other type of log message.
   */
  private $logmsg;

  /**
   * @var Throwable $exception
   * The exception that is being logged.
   * This is typically the error or exception that triggered the logging event.
   */
  private Throwable $exception;

  /**
   * @var array $info
   * Additional information related to the log event.
   * This can include any extra data that might be useful for debugging or logging purposes.
   */
  private array $info;

  /**
   * LogError constructor.
   * Initializes the event with the date and time, log name, log message, exception, and additional information.
   *
   * @param string $datetime The date and time when the log occurred.
   * @param string $logname The name of the log file where the error is being logged.
   * @param mixed $logmsg The message that is being logged.
   * @param Throwable $exception The exception that is being logged.
   * @param array $info Additional information related to the log event.
   */
  public function __construct(string $datetime, string $logname, $logmsg, Throwable $exception, array $info = [])
  {
    $this->datetime = $datetime;
    $this->logname = $logname;
    $this->logmsg = $logmsg;
    $this->exception = $exception;
    $this->info = $info;
  }

  /**
   * Returns a string representation of the event.
   * This method provides a human-readable description of the event, including the date and time, log name, and exception message.
   *
   * @return string The string representation of the event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', Log Name: ' . $this->logname . ', Exception: ' . $this->exception->getMessage() . ')';
  }

  /**
   * Returns the date and time when the log occurred.
   *
   * @return string The date and time of the log event.
   */
  public function getDatetime(): string
  {
    return $this->datetime;
  }

  /**
   * Returns the name of the log file where the error is being logged.
   *
   * @return string The name of the log file.
   */
  public function getLogName(): string
  {
    return $this->logname;
  }

  /**
   * Returns the message that is being logged.
   * This can be a string or any other type of log message.
   *
   * @return mixed The log message.
   */
  public function getLogMsg(): mixed
  {
    return $this->logmsg;
  }

  /**
   * Returns the exception that is being logged.
   * This is typically the error or exception that triggered the logging event.
   *
   * @return Throwable The exception being logged.
   */
  public function getException(): Throwable
  {
    return $this->exception;
  }

  /**
   * Returns the path to the log file where the error is being logged.
   * This is typically a directory path where log files are stored.
   *
   * @return string The path to the log file.
   */
  public function getLogFilePath(): string
  {
    return ROOT_PATH . '/log/' . $this->logname . '.log';
  }

  /**
   * Returns the name of the log file where the error is being logged.
   * This is typically the name of the log file without the path.
   *
   * @return string The name of the log file.
   */
  public function getLogFileName(): string
  {
    return $this->logname . '.log';
  }

  /**
   * Returns the full path to the log file where the error is being logged.
   * This combines the log file path and the log file name to provide the complete file path.
   *
   * @return string The full path to the log file.
   */
  public function getLogFileFullPath(): string
  {
    return $this->getLogFilePath() . '/' . $this->getLogFileName();
  }

  /**
   * Returns an array containing information about the log event.
   * This includes the date and time, log name, log message, log file path, exception, and additional info.
   *
   * @return array An associative array with details about the log event.
   */
  public function info(): array
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
