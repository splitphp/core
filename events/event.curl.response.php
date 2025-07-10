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
 * Class CurlResponse
 *
 * This class represents a response event for cURL requests.
 * It extends the base Event class and provides additional functionality for handling cURL responses.
 *
 * @package SplitPHP\Events
 */
class CurlResponse extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'curl.response';

  /**
   * @var string $datetime
   * The datetime when the cURL response event occurred.
   */
  private string $datetime;
  
  /**
   * @var string $url
   * The URL that was requested in the cURL operation.
   */
  private string $url;

  /**
   * @var string $httpVerb
   * The HTTP verb used in the cURL request (e.g., GET, POST, PUT, DELETE).
   */
  private string $httpVerb;

  /**
   * @var array $headers
   * The headers sent in the cURL request.
   */
  private array $headers = [];

  /**
   * @var mixed $rawData
   * The raw data sent in the cURL request.
   */
  private $rawData;

  /**
   * @var mixed $payload
   * The payload data sent in the cURL request, typically used for POST or PUT requests.
   */
  private $payload;

  /**
   * @var object|null $output
   * The output of the cURL request, which can be an object containing response data.
   */
  private ?object $output;


  /**
   * CurlResponse constructor.
   * Initializes the event with the specified datetime, URL, HTTP verb, headers, raw data, payload, and output.
   *
   * @param string $datetime The datetime when the cURL response event occurred.
   * @param string $url The URL that was requested in the cURL operation.
   * @param string $httpVerb The HTTP verb used in the cURL request (e.g., GET, POST, PUT, DELETE).
   * @param array $headers The headers sent in the cURL request.
   * @param mixed $rawData The raw data sent in the cURL request.
   * @param mixed $payload The payload data sent in the cURL request.
   * @param object|null $output The output of the cURL request.
   */
  public function __construct(string $datetime, string $url, string $httpVerb, array $headers = [], $rawData = null, $payload = null, ?object $output = null)
  {
    $this->datetime = $datetime;
    $this->url = $url;
    $this->httpVerb = $httpVerb;
    $this->headers = $headers;
    $this->rawData = $rawData;
    $this->payload = $payload;
    $this->output = $output;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string The string representation of the event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', URL: ' . $this->url . ', HTTP Verb: ' . $this->httpVerb . ')';
  }

  /**
   * Returns the datetime when the cURL response event occurred.
   *
   * @return string The datetime of the cURL response event.
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
   * @return string The HTTP verb of the cURL request (e.g., GET, POST, PUT, DELETE).
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
   * Returns the raw data sent in the cURL request.
   *
   * @return mixed The raw data of the cURL request.
   */
  public function getRawData()
  {
    return $this->rawData;
  }

  /**
   * Returns the payload data sent in the cURL request.
   *
   * @return mixed The payload of the cURL request, typically used for POST or PUT requests.
   */
  public function getPayload()
  {
    return $this->payload;
  }

  /**
   * Returns the output of the cURL request.
   *
   * @return object|null The output of the cURL request, which can be an object containing response data.
   */
  public function getOutput(): ?object
  {
    return $this->output;
  }

  /**
   * Returns an array containing the information about the cURL response event.
   *
   * @return array An associative array with keys 'datetime', 'url', 'httpVerb', 'headers', 'rawData', 'payload', and 'output'.
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
      'output' => $this->output
    ];
  }
}
