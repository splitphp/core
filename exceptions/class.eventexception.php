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

namespace SplitPHP\Exceptions;

use Throwable;
use Exception;
use SplitPHP\Event;

/**
 * Class EventException
 *
 * This class represents an extension of exceptions that occur on triggered Event handlers and is able to store the the event object, so the developer can
 * make a more detailed analysis of the problem.
 *
 * @package SplitPHP/Exceptions
 */
class EventException extends Exception
{
  /**
   * @var ?Event $event
   * Stores the event object.
   */
  private ?Event $event;

  /**
   * @var Throwable $originalException
   * Stores the original exception that was thrown.
   */
  private Throwable $originalException;

  /** 
   * Runs Exception class constructor, sets common Exception properties with the data retrieved from the Exception object passed on $exc, 
   * set sqlstate property with the data passed on $sqlstate, set property sqlcommand with the value passed on $sqlcmd, then returns an 
   * instance of this class (constructor).
   * 
   * @param Exception $exc
   * @param ?Event $event
   * @return EventException 
   */
  public final function __construct(Throwable $exc, ?Event $event = null)
  {
    parent::__construct($exc->getMessage(), $exc->getCode(), $exc->getPrevious());

    $this->message = $exc->getMessage();
    $this->code = $exc->getCode();
    $this->file = $exc->getFile();
    $this->line = $exc->getLine();
    $this->originalException = $exc;

    $this->event = $event;
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public final function __toString()
  {
    return "class:" . __CLASS__ . "(Code:{$this->code}, Message:{$this->message}, File:{$this->file}, Line:{$this->line}, Event:{$this->event})";
  }

  /** 
   * Returns the event object associated with this exception.
   * 
   * @return Event|null
   */
  public function getEvent(): ?Event
  {
    return $this->event;
  }

  /** 
   * Returns the original exception that was thrown.
   * 
   * @return Throwable
   */
  public function getOriginalException(): Throwable
  {
    return $this->originalException;
  }
}
