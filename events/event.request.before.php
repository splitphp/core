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
use SplitPHP\Request;

/**
 * Class BeforeRequest
 *
 * This event is triggered before a request is processed.
 * It allows modification of the request before it is handled by the application.
 *
 * @package SplitPHP\Events
 */
class BeforeRequest extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'request.before';

  /**
   * @var Request $request
   * The request object that is being processed.
   */
  private $request;

  /**
   * BeforeRequest constructor.
   * Initializes the event with the request object.
   *
   * @param Request $req The request object that is being processed.
   */
  public function __construct(Request &$req)
  {
    $this->request = $req;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string The string representation of the event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Request: ' . $this->request . ')';
  }

  /**
   * Returns the request object that is being processed.
   *
   * @return Request The request object.
   */
  public function info(): Request
  {
    return $this->request;
  }
}
