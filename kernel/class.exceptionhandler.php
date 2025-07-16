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

use Throwable;
use SplitPHP\Exceptions\DatabaseException;
use SplitPHP\Exceptions\EventException;
use SplitPHP\Exceptions\UserException;
use SplitPHP\Database\Database;

/**
 * Class ExceptionHandler
 *
 * This class is responsible for handling exceptions in the SplitPHP framework.
 * It provides methods to handle exceptions and return appropriate responses.
 *
 * @package SplitPHP
 */
final class ExceptionHandler
{
  /**
   * Handles the given exception and returns a Throwable object.
   *
   * This method checks if a database connection is available and if transactions are enabled.
   * If so, it rolls back the transaction. It then prints the exception to the console and logs it
   * if logging is enabled. Finally, it returns the original exception or the exception itself.
   *
   * @param Throwable $exception The exception to handle.
   * @return Throwable The handled exception.
   */
  public static function handle(Throwable $exception, ?Request $request = null, ?Execution $execution = null): Throwable
  {
    if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on" && Database::checkCnn('main')) {
      Database::getCnn('main')->rollbackTransaction();
    }

    // Outputs the exception to the user
    switch (System::$bootType) {
      case 'cli':
        self::printException($exception, $execution);
        break;
      case 'web':
        self::respondException($exception, $request);
        break;
      default:
        // Handle other boot types if necessary
    }

    if (APPLICATION_LOG == "on") self::logException($exception);

    return match (true) {
      $exception instanceof EventException => $exception->getOriginalException(),
      default => $exception
    };
  }

  /**
   * Prints the exception message to the console.
   *
   * This method formats the exception message and prints it in red color to indicate an error.
   *
   * @param Throwable|EventException $exception The exception to print.
   */
  private static function printException(Throwable|EventException $exception, ?Execution $execution = null): void
  {
    $excType = explode('\\', get_class($exception));
    $printType = end($excType);
    if ($printType == 'EventException')
      $printType = 'Event:' . $exception->getEvent()->getName() . '->' . get_class($exception->getOriginalException());

    echo Utils::lineBreak();
    echo "\033[31mERROR[{$printType}]: " . $exception->getMessage() . ". In file '" . $exception->getFile() . "', line " . $exception->getLine() . ".\033[0m";
    echo Utils::lineBreak();
  }

  private static function respondException(Throwable|EventException $exception, ?Request $request = null): void
  {
    $status = 500;
    $responseData = [
      "error" => true,  
      "accessible" => false,
      "message" => $exception->getMessage(),
      "request" => $request->__toString(),
      "method" => $request->getVerb(),
      "url" => $request->getRoute()->url,
      "params" => $request->getRoute()->params,
      "body" => $request->getBody()
    ];

    if ($exception instanceof UserException) {
      $status = $exception->getStatusCode() ?: 500;
      $responseData['accessible'] = $exception->isUserReadable();
    }

    http_response_code($status);

    if (!empty($responseData)) {
      header('Content-Type: application/json');
      echo json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
  }

  /**
   * Logs the exception based on its type.
   *
   * This method checks the type of the exception and logs it accordingly.
   * It handles DatabaseException, EventException, UserException, and general exceptions.
   *
   * @param Throwable $exception The exception to log.
   */
  private static function logException(Throwable $exception): void
  {
    switch (true) {
      case $exception instanceof DatabaseException:
        Helpers::Log()->error('database_error', $exception, [
          'sqlState' => $exception->getSqlState(),
          'sqlCommand' => $exception->getSqlCmd()
        ]);
        break;
      case $exception instanceof EventException:
        $extra = [
          'eventName' => $exception->getEvent()->getName(),
          'eventInfo' => $exception->getEvent()->info()
        ];

        $originalExc = $exception->getOriginalException();
        switch (true) {
          case $originalExc instanceof DatabaseException:
            $extra = [
              'sqlState' => $originalExc->getSqlState(),
              'sqlCommand' => $originalExc->getSqlCmd()
            ];
            $logname = 'database_error';
            break;
          case $originalExc instanceof UserException:
            $logname = 'user_error';
            $extra = [
              'status' => $originalExc->getStatusMessage(),
              'code' => $originalExc->getStatusCode()
            ];
            break;
          default:
            $logname = 'general_error';
        }
        Helpers::Log()->error($logname, $exception, $extra);
        break;
      case $exception instanceof UserException:
        Helpers::Log()->error('user_error', $exception, [
          'status' => $exception->getStatusMessage(),
          'code' => $exception->getStatusCode()
        ]);
        break;
      default:
        Helpers::Log()->error('general_error', $exception);
    }
  }
}
