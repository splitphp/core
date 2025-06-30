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

class CurlBefore implements Event
{
  public const EVENT_NAME = 'curl.before';

  private string $datetime;
  private string $url;
  private string $httpVerb;
  private array $headers = [];
  private $rawData;
  private $payload;


  public function __construct(string $datetime, string $url, string $httpVerb, array $headers = [], $rawData = null, $payload = null)
  {
    $this->datetime = $datetime;
    $this->url = $url;
    $this->httpVerb = $httpVerb;
    $this->headers = $headers;
    $this->rawData = $rawData;
    $this->payload = $payload;
  }

  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Datetime: ' . $this->datetime . ', URL: ' . $this->url . ', HTTP Verb: ' . $this->httpVerb . ')';
  }

  public function getName(): string
  {
    return self::EVENT_NAME;
  }

  public function getDatetime()
  {
    return $this->datetime;
  }

  public function getUrl()
  {
    return $this->url;
  }

  public function getHttpVerb()
  {
    return $this->httpVerb;
  }

  public function getHeaders()
  {
    return $this->headers;
  }

  public function getRawData()
  {
    return $this->rawData;
  }

  public function getPayload()
  {
    return $this->payload;
  }

  public function info()
  {
    return [
      'datetime' => $this->datetime,
      'url' => $this->url,
      'httpVerb' => $this->httpVerb,
      'headers' => $this->headers,
      'rawData' => $this->rawData,
      'payload' => $this->payload
    ];
  }
}
