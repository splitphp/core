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
 * Class CurlError
 *
 * This class represents a cURL error event.
 * It extends the base Event class and provides additional functionality for handling cURL errors.
 *
 * @package SplitPHP\Events
 */
class CurlError extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'curl.error';

  /**
   * @var string $datetime
   * The datetime when the cURL error event occurred.
   */
  private string $datetime;

  /**
   * @var string $url
   * The URL that was requested.
   */
  private string $url;

  /**
   * @var string $httpVerb
   * The HTTP verb used for the request (e.g., GET, POST, PUT, PATCH, DELETE).
   */
  private string $httpVerb;

  /**
   * @var array $headers
   * The headers sent with the request.
   */
  private array $headers = [];

  /**
   * @var mixed $rawData
   * The raw data sent with the request, if any.
   */
  private $rawData;

  /**
   * @var mixed $payload
   * The payload sent with the request, if any.
   */
  private $payload;

  /**
   * @var object|null $output
   * The output of the cURL request, typically an object containing response data.
   */
  private ?object $output;

  /**
   * @var string $errorMsg
   * The error message associated with the cURL request, if any.
   */
  private string $errorMsg;

  /**
   * CurlError constructor.
   * Initializes the event with the specified datetime, URL, HTTP verb, headers, raw data, payload, output, and error message.
   *
   * @param string $datetime The datetime when the cURL error event occurred.
   * @param string $url The URL that was requested.
   * @param string $httpVerb The HTTP verb used for the request.
   * @param string $errorMsg The error message associated with the cURL request.
   * @param array $headers The headers sent with the request.
   * @param mixed $rawData The raw data sent with the request, if any.
   * @param mixed $payload The payload sent with the request, if any.
   * @param object|null $output The output of the cURL request, typically an object containing response data.
   */
  public function __construct(string $datetime, string $url, string $httpVerb, string $errorMsg, array $headers = [], $rawData = null, $payload = null, ?object $output = null)
  {
    $this->datetime = $datetime;
    $this->url = $url;
    $this->httpVerb = $httpVerb;
    $this->headers = $headers;
    $this->rawData = $rawData;
    $this->payload = $payload;
    $this->output = $output;
    $this->errorMsg = $errorMsg;
  }

  /**
   * Returns a string representation of the cURL error event.
   *
   * @return string A string representation of the event, including the datetime, URL, HTTP verb, and error message.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', URL: ' . $this->url . ', HTTP Verb: ' . $this->httpVerb . ', Error: ' . $this->errorMsg . ')';
  }

  /**
   * Returns the datetime when the cURL error event occurred.
   *
   * @return string The datetime of the event.
   */
  public function getDatetime(): string
  {
    return $this->datetime;
  }

  /**
   * Returns the URL that was requested in the cURL operation.
   *
   * @return string The URL of the cURL request.
   */
  public function getUrl(): string
  {
    return $this->url;
  }

  /**
   * Returns the HTTP verb used in the cURL request.
   *
   * @return string The HTTP verb (e.g., GET, POST, PUT, PATCH, DELETE).
   */
  public function getHttpVerb(): string
  {
    return $this->httpVerb;
  }

  /**
   * Returns the headers sent in the cURL request.
   *
   * @return array The headers of the cURL request.
   */
  public function getHeaders(): array
  {
    return $this->headers;
  }

  /**
   * Returns the raw data sent in the cURL request, if any.
   *
   * @return mixed The raw data of the cURL request.
   */
  public function getRawData()
  {
    return $this->rawData;
  }

  /**
   * Returns the payload sent in the cURL request, if any.
   *
   * @return mixed The payload of the cURL request.
   */
  public function getPayload()
  {
    return $this->payload;
  }

  /**
   * Returns the output of the cURL request, if any.
   *
   * @return object|null The output of the cURL request.
   */
  public function getOutput(): ?object
  {
    return $this->output;
  }

  /**
   * Returns the error message associated with the cURL request, if any.
   *
   * @return string The error message of the cURL request.
   */
  public function getErrorMsg(): string
  {
    return $this->errorMsg;
  }

  /**
   * Returns an array of information about the cURL error event.
   *
   * @return array An associative array containing details about the event.
   */
  public function info(): array
  {
    return [
      'datetime' => $this->datetime,
      'url' => $this->url,
      'httpVerb' => $this->httpVerb,
      'headers' => $this->headers,
      'rawData' => $this->rawData,
      'payload' => $this->payload,
      'output' => $this->output,
      'errorMsg' => $this->errorMsg
    ];
  }
}
