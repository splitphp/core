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
use SplitPHP\Execution;

/**
 * Class CommandBefore
 *
 * This class represents an event that occurs before a command is executed.
 * It extends the base Event class and provides additional functionality for handling command events.
 *
 * @package SplitPHP\Events
 */
class CommandBefore extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'command.before';

  /**
   * @var Execution $execution
   * The execution instance that contains information about the command being executed.
   */
  private Execution $execution;

  /**
   * CommandBefore constructor.
   *
   * Initializes the CommandBefore event with the provided Execution instance.
   *
   * @param Execution $execution The execution instance containing command information.
   */
  public function __construct(Execution $execution)
  {
    $this->execution = $execution;
  }

  /**
   * Returns a string representation of the CommandBefore event.
   * This method provides a human-readable description of the event, including the command being executed.
   *
   * @return string A string representation of the CommandBefore event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Action: ' . $this->execution . ')';
  }

  /**
   * Returns the execution instance associated with the CommandBefore event.
   *
   * @return Execution The execution instance containing command information.
   */
  public function info(): mixed
  {
    return $this->execution;
  }
}
