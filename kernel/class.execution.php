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

/**
 * Class Execution
 * 
 * This class is for capturing the incoming cli commands and managing its informations.
 *
 * @package SplitPHP
 */
class Execution
{
  /**
   * @var string $cmd
   * Stores the current accessed command.
   */
  private $cmd;

  private Cli $cli;

  /**
   * @var string $cliPath
   * Stores the defined Cli class path.
   */
  private $cliPath;

  /**
   * @var string $cliName
   * Stores the defined Cli class name.
   */
  private $cliName;

  /**
   * @var array $args
   * Stores the parameters and data passed along the command.
   */
  private $args;

  /** 
   * Parse the incoming $argv, separating, Cli's path and arguments. Returns an instance of the Action class (constructor).
   * 
   * @param array $args
   * @return Action 
   */
  public final function __construct(array $args)
  {
    Utils::printLn();
    Utils::printLn("
:'######::'########::'##:::::::'####:'########:::::'########::'##::::'##:'########::
'##... ##: ##.... ##: ##:::::::. ##::... ##..:::::: ##.... ##: ##:::: ##: ##.... ##:
 ##:::..:: ##:::: ##: ##:::::::: ##::::: ##:::::::: ##:::: ##: ##:::: ##: ##:::: ##:
. ######:: ########:: ##:::::::: ##::::: ##:::::::: ########:: #########: ########::
:..... ##: ##.....::: ##:::::::: ##::::: ##:::::::: ##.....::: ##.... ##: ##.....:::
'##::: ##: ##:::::::: ##:::::::: ##::::: ##:::::::: ##:::::::: ##:::: ##: ##::::::::
. ######:: ##:::::::: ########:'####:::: ##:::::::: ##:::::::: ##:::: ##: ##::::::::
:......:::..:::::::::........::....:::::..:::::::::..:::::::::..:::::..::..::::v2.2.5");
    Utils::printLn("
                ____ ____ ____ _  _ ____ _ _ _ ____ ____ _  _ 
                |___ |__/ |__| |\/| |___ | | | |  | |__/ |_/  
                |    |  \ |  | |  | |___ |_|_| |__| |  \ | \_ CONSOLE");
    Utils::printLn("\nWELCOME!!\n");

    $this->cmd = $args[1];
    array_shift($args);
    array_shift($args);
    $cmdElements = explode(":", $this->cmd);
    $this->args = $this->prepareArgs($args);

    if (
      is_null($metadata = $this->findBuiltInCli($cmdElements)) &&
      is_null($metadata = AppLoader::findCli($cmdElements)) &&
      is_null($metadata = ModLoader::findCli($cmdElements))
    ) {
      throw new Exception("The requested CLI could not be found.");
    }

    $this->cliPath = $metadata->cliPath;
    $this->cliName = $metadata->cliName;
    $this->cmd = $metadata->cmd;
    $this->cli = ObjLoader::load($metadata->cliPath);
    if (is_array($this->cli)) throw new Exception("CLI files cannot contain more than 1 class or namespace.");
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public final function __toString(): string
  {
    return "class:" . __CLASS__ . "(CLI:{$this->cliName}, Path:{$this->cliPath}, Command:{$this->cmd})";
  }

  /** 
   * Returns the stored command.
   * 
   * @return string 
   */
  public function getCmd(): string
  {
    return $this->cmd;
  }

  /**
   * Returns the path of the Cli class.
   * 
   * @return string 
   */
  public function getCliPath(): string
  {
    return $this->cliPath;
  }

  /**
   * Returns the name of the Cli class.
   * 
   * @return string 
   */
  public function getCliName(): string
  {
    return $this->cliName;
  }

  /** 
   * Returns the instance of the Cli class.
   * 
   * @return Cli 
   */
  public function getCli(): Cli
  {
    return $this->cli;
  }

  /** 
   * Returns the parameters and data passed along the command.
   * 
   * @return array 
   */
  public function getArgs(): array
  {
    return $this->args;
  }

  /** 
   * Using $path as a base, loops through the $cmdElements searching for a valid Cli filepath. Once it is found, define the 
   * Cli's path and name, and the rest of the remaining elements up to that point are defined as the command itself.
   * 
   * @param string $path
   * @param array $cmdElements
   * @return object|null 
   */
  private function findBuiltInCli(array $cmdElements): ?object
  {
    $basePath = ROOT_PATH . "/core/commands/";

    foreach ($cmdElements as $i => $cmdPart) {
      if (is_dir($basePath . $cmdPart))
        $basePath .= $cmdPart . '/';
      elseif (is_file("{$basePath}{$cmdPart}.php")) {
        Utils::printLn("[SPLITPHP CONSOLE] Running a built-in command.");
        Utils::printLn("                   User-defined commands with the same name will be ignored.");

        return (object) [
          'cliPath' => "{$basePath}{$cmdPart}.php",
          'cliName' => $cmdPart,
          'cmd' => ":" . implode(':', array_slice($cmdElements, $i + 1))
        ];
      } else {
        return null;
      }
    }

    return null;
  }

  /** 
   * Normalizes args from CLI input, setting the final array in the pattern "key=value".
   * 
   * @param array $args
   * @return array
   */
  private function prepareArgs(array $args): array
  {
    $result = [];
    foreach ($args as $arg) {
      if (is_string($arg) && strpos($arg, '=') !== false) {
        $argData = explode('=', $arg);
        $result[$argData[0]] = $argData[1];
      } else $result[] = $arg;
    }

    return $result;
  }
}
