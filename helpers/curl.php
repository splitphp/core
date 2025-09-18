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
use SplitPHP\EventDispatcher;
use Exception;

/**
 * Class Curl
 *
 * This helper class provides a simple interface for making HTTP requests using cURL.
 * It supports various HTTP methods (GET, POST, PUT, PATCH, DELETE) and allows setting headers and data.
 *
 * @package SplitPHP\Helpers
 */
class Curl
{
  /**
   * @var array $headers
   * An array to store headers that will be sent with the request.
   */
  private $headers = [];

  /**
   * @var mixed $rawData
   * The raw data that will be sent in the request body.
   */
  private $rawData;

  /**
   * @var string|null $payload
   * The payload that will be sent in the request body, formatted as a query string or JSON.
   */
  private $payload;

  /**
   * @var string|null $httpVerb
   * The HTTP verb (method) that will be used for the request (e.g., GET, POST, PUT, PATCH, DELETE).
   */
  private $httpVerb;

  /**
   * @var string|null $url
   * The URL to which the request will be sent.
   */
  private $url;

  /**
   * @var string $userAgent
   * The User-Agent string to be sent with the request.
   */
  private $userAgent = 'User-Agent: curl/8.10.1';

  /**
   * Set a header to be sent with the request.
   *
   * @param string $header
   * @return $this
   */
  public function setHeader(string $header)
  {
    if (in_array($header, $this->headers) == false)
      $this->headers[] = $header;

    return $this;
  }

  /**
   * Set the User-Agent header for the request.
   *
   * @param string $userAgent
   * @return $this
   */
  public function setUserAgent(string $userAgent)
  {
    $this->userAgent = 'User-Agent: ' . $userAgent;
    return $this;
  }

  /**
   * Set the data to be sent in the request body.
   *
   * @param mixed $data
   * @return $this
   */
  public function setData($data)
  {
    $this->rawData = $data;
    $this->payload = http_build_query($data);
    return $this;
  }

  /**
   * Set the data to be sent in the request body as JSON.
   *
   * @param mixed $data
   * @return $this
   */
  public function setDataAsJson($data)
  {
    $this->rawData = $data;
    $this->payload = json_encode($data);
    $this->setHeader('Content-Type:application/json');
    $this->setHeader('Accept: application/json');
    return $this;
  }

  /**
   * Send a POST request.
   *
   * @param string $url
   * @return mixed
   */
  public function post($url)
  {
    return $this->request("POST", $url);
  }

  /**
   * Send a PUT request.
   *
   * @param string $url
   * @return mixed
   */
  public function put($url)
  {
    return $this->request("PUT", $url);
  }

  /**
   * Send a PATCH request.
   *
   * @param string $url
   * @return mixed
   */
  public function patch($url)
  {
    return $this->request("PATCH", $url);
  }

  /**
   * Send a DELETE request.
   *
   * @param string $url
   * @return mixed
   */
  public function del($url)
  {
    return $this->request("DELETE", $url);
  }

  /**
   * Send a GET request.
   *
   * @param string $url
   * @return mixed
   */
  public function get($url)
  {
    return $this->request("GET", $url);
  }

  /**
   * Send an HTTP request with the specified method and URL.
   *
   * @param string $httpVerb The HTTP method to use (e.g., GET, POST, PUT, PATCH, DELETE).
   * @param string $url The URL to which the request will be sent.
   * @return mixed The response from the server.
   * @throws Exception If the HTTP verb is invalid or if there is an error in the cURL request.
   */
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
    $this->setHeader($this->userAgent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

    // Execute and return:
    curl_setopt($ch, CURLOPT_URL, $url);

    $output = null;
    EventDispatcher::dispatch(function () use (&$output, $ch, $url, $httpVerb) {
      $output = (object)[
        'data' => json_decode(curl_exec($ch), true),
        'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE)
      ];

      EventDispatcher::dispatch(function () use ($output, $url, $httpVerb) {
        if ($output->status >= 400) {
          EventDispatcher::dispatch(fn() => true, 'curl.error', [
            'datetime' => date('Y-m-d H:i:s'),
            'url' => $url,
            'httpVerb' => $httpVerb,
            'headers' => $this->headers,
            'rawData' => $this->rawData,
            'payload' => $this->payload,
            'errorMsg' => "Error in cURL request: HTTP {$output->status} - " . json_encode($output->data)
          ]);
        }
      }, 'curl.response', [
        'datetime' => date('Y-m-d H:i:s'),
        'url' => $url,
        'httpVerb' => $httpVerb,
        'headers' => $this->headers,
        'rawData' => $this->rawData,
        'payload' => $this->payload,
        'output' => $output
      ]);

      $this->log($output);
    }, 'curl.before', [
      'datetime' => date('Y-m-d H:i:s'),
      'url' => $url,
      'httpVerb' => $httpVerb,
      'headers' => $this->headers,
      'rawData' => $this->rawData,
      'payload' => $this->payload
    ]);

    curl_close($ch);

    $this->headers = [];
    $this->rawData = null;
    $this->payload = null;
    $this->httpVerb = null;
    $this->url = null;

    return $output;
  }

  /**
   * Log the cURL request and response details.
   *
   * @param mixed $output The output of the cURL request, typically an object containing response data.
   */
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

    Helpers::Log()->common('curl', $logObj);
  }
}
