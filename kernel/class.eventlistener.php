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
 * Class EventListener
 *
 * This class is responsible for managing event listeners.
 *
 * @package SplitPHP
 */
abstract class EventListener extends Service
{
  /**
   * @var array $listeners
   * Stores the registered event listeners.
   * The keys are unique listener IDs and the values are objects containing listener details.
   */
  private static $listeners = [];

  /**
   * EventListener constructor.
   * Initializes the event listener by discovering events.
   */
  public final function __construct()
  {
    // Invoke Listener's init:
    $this->init();
  }

  /**
   * Converts the event listener to a string representation.
   *
   * @return string The string representation of the event listener.
   */
  public final function __toString(): string
  {
    return "EventListener: " . get_class($this);
  }

  /**
   * Adds an event listener for a specific event.
   *
   * @param string $evtName The name of the event.
   * @param callable $callback The callback function to be executed when the event is triggered.
   * @return string The unique ID of the registered event listener.
   */
  protected final function addEventListener(string $evtName, callable $callback): string
  {
    $evtId = "evt-" . uniqid() . "-" . $evtName;
    self::$listeners[$evtId] = (object) [
      'evtName' => $evtName,
      'callback' => $callback
    ];

    return $evtId;
  }

  /**
   * Removes an event listener by its unique ID.
   *
   * @param string $evtId The unique ID of the event listener to remove.
   */
  public static final function removeEventListener($evtId): void
  {
    unset(self::$listeners[$evtId]);
  }

  /**
   * Removes all event listeners for a specific event.
   *
   * @param string $evtName The name of the event.
   */
  public static final function eventRemoveListeners($evtName): void
  {
    foreach (self::$listeners as $key => $listener) {
      if (strpos($key, $evtName) !== false)
        unset(self::$listeners[$key]);
    }
  }

  /**
   * Gets all registered event listeners.
   *
   * @return array An array of registered event listeners.
   */
  public static final function getListeners(): array
  {
    return self::$listeners;
  }

  /**
   * Initializes the event listener.
   * This method should be implemented by subclasses to set up any necessary event listeners.
   */
  protected abstract function init();
}
