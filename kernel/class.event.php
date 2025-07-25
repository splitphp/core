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

/**
 * Interface Event
 *
 * This interface defines the structure for event classes in the SplitPHP framework.
 * It requires methods to get the event name, convert the event to a string, and provide additional information about the event.
 *
 * @package SplitPHP
 */
abstract class Event
{
  /**
   * The name of the event.
   * This constant should be overridden in subclasses to provide a specific event name.
   */
  private const EVENT_NAME = "Event";

  /**
   * Indicates whether the event should propagate to other listeners.
   * This property can be overridden in subclasses to control propagation behavior.
   */
  protected $propagate = true;

  /**
   * Returns the name of the event.
   *
   * @return string The name of the event.
   */
  public final function getName(): string
  {
    return static::EVENT_NAME;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string The string representation of the event.
   */
  public function __toString(): string
  {
    return 'Event: ' . $this->getName();
  }

  /**
   * Stops the propagation of the event to other listeners.
   * This method can be called to prevent further processing of the event by other listeners.
   */
  public final function stopPropagation(): void
  {
    $this->propagate = false;
  }

  /**
   * Allows the event to continue propagating to other listeners.
   * This method can be called to ensure that the event is processed by subsequent listeners.
   */
  public final function continuePropagation(): void
  {
    $this->propagate = true;
  }

  /**
   * Checks if the event should continue propagating to other listeners.
   *
   * @return bool True if the event should continue propagating, false otherwise.
   */
  public final function shouldPropagate(): bool
  {
    return $this->propagate;
  }

  /**
   * Returns additional information about the event.
   */
  public abstract function info();
}
