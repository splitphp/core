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
use SplitPHP\Database\Database;
use Throwable;

/**
 * Class Cli
 * 
 * This class aims to provide an interface where the developer creates the user-defined's CLI and defines its commands.
 *
 * @package SplitPHP
 */
abstract class Cli extends Service
{
  /**
   * @var array $commands
   * Stores a list of command strings.
   */
  protected $commands;

  /**
   * @var array $commandIndex
   * This is a summary for the $commands list.
   */
  protected $commandIndex;

  /**
   * @var int $timeStart
   * Stores the timestamp of the start of the command's execution.
   */
  private $timeStart;

  /**
   * @var int $timeEnd
   * Stores the timestamp of the end of the command's execution.
   */
  private $timeEnd;

  /**
   * @var string $cmdString
   * Stores the current execution's command string.
   */
  private $cmdString;

  /** 
   * Defines constants for user errors, set properties with their initial values, instantiate other classes, then returns an
   * instance of the CLI(constructor).
   * 
   * @return Cli 
   */
  public final function __construct()
  {
    ini_set('display_errors', 1);

    $this->commands = [];
    $this->cmdString = "";
    $this->timeStart = 0;
    $this->timeEnd = 0;

    // Invoke Cli's init:
    $this->init();
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public final function __toString(): string
  {
    return "class:Cli:" . __CLASS__ . "(started:{$this->timeStart}, Ended:{$this->timeEnd}, Command:{$this->cmdString})";
  }

  /** 
   * Prints a message to the console that indicates the command has started, with a line break at the end.
   * 
   * @return void 
   */
  public final static function cmdStarted(): void
  {
    echo PHP_EOL;
    Utils::printLn("*------*------*------*------*------*------*------*");
    Utils::printLn("[SPLITPHP CONSOLE] Command execution started.");
    Utils::printLn("*------*------*------*------*------*------*------*");
    echo PHP_EOL;
  }

  /**
   * Prints a message to the console that indicates the command has finished, with a line break at the end.
   * 
   * @param int $durationTime
   * @return void
   */
  public final static function cmdFinished(int $durationTime): void
  {
    echo PHP_EOL;
    Utils::printLn("*------*------*------*------*------*------*------*");
    Utils::printLn("[SPLITPHP CONSOLE] Command execution finished. Run time duration: {$durationTime} second(s).");
    Utils::printLn("*------*------*------*------*------*------*------*");
    echo PHP_EOL;
    Utils::printLn("GOOD BYE! :)");
  }

  /** 
   * Searches for the command's string in added commands list then executes the 
   * handler method provided for the command.
   * 
   * @param Execution $execution
   * @param bool $innerExecution = false
   * @return void 
   */
  public final function execute(Execution $execution, $innerExecution = false): void
  {
    $this->cmdString = $execution->getCmd();

    $commandData = $this->findCommand($this->cmdString);
    if (empty($commandData)) {
      throw new Exception("Command \"{$this->cmdString}\" not found");
    }

    try {
      $this->timeStart = time();
      if (!$innerExecution) self::cmdStarted();

      $commandHandler = is_callable($commandData->method) ? $commandData->method : [$this, $commandData->method];

      if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on" && !$innerExecution) {
        Database::getCnn('main')->startTransaction();
        call_user_func_array($commandHandler, [$execution->getArgs()]);
        Database::getCnn('main')->commitTransaction();
      } else {
        call_user_func_array($commandHandler, [$execution->getArgs()]);
      }

      $this->timeEnd = time();
      $durationTime = $this->timeEnd - $this->timeStart;
      if (!$innerExecution) self::cmdFinished($durationTime);
    } catch (Throwable $exc) {
      ExceptionHandler::handle($exc);
    }
  }

  /** 
   * Registers a command on the list $commands, in other words: makes a command available within the CLI, with the 
   * handler method provided.
   * 
   * @param string $cmdString
   * @param mixed $method
   * @return void 
   */
  protected final function addCommand(string $cmdString, $method): void
  {
    $cmdString = substr($cmdString, 0, 1) === ':' ? $cmdString : ":" . $cmdString;

    if (!empty($this->findCommand($cmdString)))
      throw new Exception("Attempt to add duplicate command (same command within a single CLI). (" . self::class . " -> " . $cmdString . ")");

    $this->commands[$cmdString] = (object) [
      "command" => $cmdString,
      "method" => $method
    ];
  }

  /** 
   * Runs another command from within a command, based on the received command string. 
   * Returns the executed command's returned value.
   * 
   * @param string $cmdString
   * @return mixed 
   */
  protected final function run(string $cmdString): mixed
  {
    $execution = new Execution(['console', ...explode(" ", $cmdString)]);
    if ($execution->getCmd() == $this->cmdString) throw new Exception("You cannot run a command from within itself");

    return System::runCommand($execution);
  }

  /** 
   * Using the command's string, searches for a command in the commands list. Returns the command data or null, in case of not founding it.
   * 
   * @param string $cmdString
   * @return object
   */
  private function findCommand(string $cmdString): ?object
  {
    if (array_key_exists($cmdString, $this->commands)) return $this->commands[$cmdString];

    return null;
  }

  /** 
   * This method must be implemented by the child class, where the developer will define the commands of the CLI.
   * It is called automatically when the CLI is instantiated.
   * 
   * @return void 
   */
  public abstract function init(): void;
}
