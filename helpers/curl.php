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

namespace SplitPHP\Helpers;

use SplitPHP\Helpers;
use SplitPHP\EventListener;
use Exception;
use SplitPHP\Event;

class Curl
{
  private $headers = [];
  private $rawData;
  private $payload;
  private $httpVerb;
  private $url;

  public function setHeader(string $header)
  {
    if (in_array($header, $this->headers) == false)
      $this->headers[] = $header;

    return $this;
  }

  public function setData($data)
  {
    $this->rawData = $data;
    $this->payload = http_build_query($data);
    return $this;
  }

  public function setDataAsJson($data)
  {
    $this->rawData = $data;
    $this->payload = json_encode($data);
    $this->setHeader('Content-Type:application/json');
    return $this;
  }

  public function post($url)
  {
    return $this->request("POST", $url);
  }

  public function put($url)
  {
    return $this->request("PUT", $url);
  }

  public function patch($url)
  {
    return $this->request("PATCH", $url);
  }

  public function del($url)
  {
    return $this->request("DELETE", $url);
  }

  public function get($url)
  {
    return $this->request("GET", $url);
  }

  public function request($httpVerb, $url)
  {
    $this->httpVerb = $httpVerb;
    $this->url = $url;

    // Validates $httpVerb:
    if (!in_array($httpVerb, ['POST', 'PUT', 'PATCH', 'DELETE', 'GET']))
      throw new Exception("Invalid HTTP verb.");

    // basic setup:
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // set options by http verb:
    $httpVerb = strtoupper($httpVerb);
    switch ($httpVerb) {
      case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->payload);
        break;
      case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->payload);
        break;
      case 'PATCH':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->payload);
        break;
      case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->payload);
        break;
      case 'GET':
        if (!empty($this->headers) && in_array('Content-Type:application/json', $this->headers))
          throw new Exception("It is not possible to send JSON data through GET requests.");

        $url = strpos($url, '?') !== false ? $url . '&' . $this->payload : $url . '?' . $this->payload;
        break;
    }

    // Set Headers:
    if (!empty($this->headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

    // Execute and return:
    curl_setopt($ch, CURLOPT_URL, $url);

    EventListener::triggerEvent('curl.before', [
      'datetime' => date('Y-m-d H:i:s'),
      'url' => $url,
      'httpVerb' => $httpVerb,
      'headers' => $this->headers,
      'rawData' => $this->rawData,
      'payload' => $this->payload
    ]);

    $output = (object)[
      'data' => json_decode(curl_exec($ch), true),
      'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE)
    ];
    curl_close($ch);

    EventListener::triggerEvent('curl.response', [
      'datetime' => date('Y-m-d H:i:s'),
      'url' => $url,
      'httpVerb' => $httpVerb,
      'headers' => $this->headers,
      'rawData' => $this->rawData,
      'payload' => $this->payload,
      'output' => $output
    ]);

    if ($output->status >= 400) {
      $errorMsg = "Error in cURL request: HTTP {$output->status} - " . json_encode($output->data);
      EventListener::triggerEvent('curl.error', [
        'datetime' => date('Y-m-d H:i:s'),
        'url' => $url,
        'httpVerb' => $httpVerb,
        'headers' => $this->headers,
        'rawData' => $this->rawData,
        'payload' => $this->payload,
        'errorMsg' => $errorMsg
      ]);
    }

    $this->log($output);

    $this->headers = [];
    $this->rawData = null;
    $this->payload = null;
    $this->httpVerb = null;
    $this->url = null;

    return $output;
  }

  private function log($output)
  {
    $logObj = [
      "datetime" => date('Y-m-d H:i:s'),
      "url" => $this->url,
      "httpVerb" => $this->httpVerb,
      "headers" => $this->headers,
      "rawData" => $this->rawData,
      "payload" => $this->payload,
      "output" => $output
    ];

    Helpers::Log()->add('curl', $logObj);
  }
}