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

use stdClass;
use Exception;

/**
 * Class Request
 * 
 * This class if for capturing the incoming requests and managing its informations.
 *
 * @package SplitPHP
 */
class Request
{
  /**
   * @var string $httpVerb
   * Stores the HTTP verb on which the request was made.
   */
  private string $httpVerb;

  /**
   * @var string $route
   * Stores the current accessed route URL.
   */
  private string $url;

  /**
   * @var array $routeparams
   * Stores the route parameters, if any.
   */
  private array $routeparams;

  /**
   * @var string $webServiceName
   * Stores the name of the Web Service being accessed.
   */
  private string $webServiceName;

  /**
   * @var string $webServicePath
   * Stores the path to the Web Service being accessed.
   */
  private string $webServicePath;

  /**
   * @var WebService $webService
   * Stores the Web Service class instance.
   */
  private WebService $webService;

  /**
   * @var array $body
   * Stores the body of the request, if any.
   */
  private array $body;

  /** 
   * Parse the incoming URI, separating DNS, Web Service's path and name, route and arguments. Returns an instance of the Request class (constructor).
   * 
   * @param string $uri
   * @return Request 
   */
  public final function __construct(string $uri)
  {
    $urlElements = array_values(array_filter(
      explode("/", str_replace(strrchr(urldecode($uri), "?"), "", urldecode($uri))),
      'strlen'
    ));

    // If no route is found under URL, set it as default route:
    if (empty($urlElements[0])) {
      $urlElements = array_filter(
        explode('/', str_replace(strrchr(urldecode(DEFAULT_ROUTE), "?"), "", urldecode(DEFAULT_ROUTE))),
        'strlen'
      );
    }

    if (
      is_null($metadata = AppLoader::findWebService($urlElements)) &&
      is_null($metadata = ModLoader::findWebService($urlElements))
    ) {
      http_response_code(404);
      die;
    }

    $this->httpVerb = $_SERVER['REQUEST_METHOD'];
    $this->url = $metadata->route;
    $this->body = [];
    $this->routeparams = [];
    $this->webServiceName = $metadata->webServiceName;
    $this->webServicePath = $metadata->webServicePath;
    $this->webService = ObjLoader::load($this->webServicePath, [$this->url, $this->httpVerb]);
    if (is_array($this->webService)) throw new Exception("WebService files cannot contain more than 1 class or namespace.");
  }

  /**
   * Unloads the Web Service class instance when the Request object is destroyed.
   * 
   * This method is called automatically when the Request object is no longer needed, ensuring that resources are freed.
   * 
   * @return void
   */
  public function __destruct()
  {
    ObjLoader::unload($this->webServicePath);
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString(): string
  {
    return "class:" . __CLASS__ . "(WebService:{$this->webServiceName}, Path:{$this->webServicePath}, URL:{$this->url}, HttpVerb:{$this->httpVerb})";
  }

  /** 
   * Returns the HTTP verb on which the request was made.
   * 
   * @return string 
   */
  public function getVerb(): string
  {
    return $this->httpVerb;
  }

  /** 
   * Returns the stored route.
   * 
   * @return object 
   */
  public function getRoute(): object
  {
    return (object) [
      'url' => $this->url,
      'params' => $this->routeparams,
    ];
  }

  /** 
   * Returns the request's body, if any.
   * 
   * @return array
   */
  public function getBody(?string $key = null): array|string
  {
    if ($key !== null)
      return $this->body[$key];

    return $this->body;
  }

  /**
   * Sets a specific key in the request's body.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function setBody(string $key, mixed $value): void
  {
    $this->body[$key] = $value;
  }

  /**
   * Unsets a specific key from the request's body.
   *
   * @param string $key
   * @return void
   */
  public function unsetBody(string $key): void
  {
    if (array_key_exists($key, $this->body))
      unset($this->body[$key]);
  }

  /**
   * Returns the request's headers.
   * @param string|null $headerName
   * If provided, returns the value of the specified header. If not provided, returns all headers.
   * 
   * @return array|string|null
   */
  public function getHeader(?string $headerName = null): array|string|null
  {
    $headers = getallheaders();

    if ($headerName !== null)
      return $headers[$headerName];

    return $headers;
  }

  /**
   * Returns stored the Web Service class instance.
   * 
   * @return WebService
   */
  public function getWebService(): WebService
  {
    return $this->webService;
  }

  /**
   * Extracts data from the request and populates the class properties.
   * 
   * @param bool $antiXSS
   * @return void
   */
  public function parseData(bool $antiXSS = true): void
  {
    $routeEntry = $this->webService->findRoute($this->url, $this->httpVerb);
    $routeInput = array_filter(explode('/', $this->url));

    if ($routeEntry !== false)
      foreach ($routeEntry->params as $param) {
        $this->routeparams[$param->paramKey] = $routeInput[$param->index];
      }

    switch ($this->httpVerb) {
      case 'GET':
        $this->body = $_GET;
        $this->actualizeEmptyValues($this->body);
        break;
      case 'POST':
        $this->body = array_merge($_POST, $_GET);
        $this->actualizeEmptyValues($this->body);
        break;
      case 'PUT':
        global $_PUT;
        $this->body = array_merge($_PUT, $_GET);
        $this->actualizeEmptyValues($this->body);
        break;
      case 'DELETE':
        $this->body = $_GET;
        $this->actualizeEmptyValues($this->body);
        break;
    }

    if ($routeEntry && $routeEntry->antiXSS && $antiXSS) $this->antiXSS($this->body);
  }

  /** 
   * Returns the client's IP.
   * 
   * @return string 
   */
  public static function getUserIP(): string
  {
    //whether ip is from the share internet  
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    //whether ip is from the proxy  
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    //whether ip is from the remote address  
    else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }

  /** 
   * Nullify string representations of empty values, like 'null' or 'undefined', then returns the modified dataset.
   * 
   * @param mixed $data
   * @return mixed
   */
  private function actualizeEmptyValues(&$data): void
  {
    if (gettype($data) == 'array' || (gettype($data) == 'object' && $data instanceof StdClass)) {
      foreach ($data as &$value) {
        $this->actualizeEmptyValues($value);
      }
    }

    if ($data === 'null' || $data === 'undefined') $data = null;
  }

  /** 
   * Performs a check for potentially harmful data within $input. If found, log information about it and throws exception.
   * 
   * @param mixed $input
   * @return void
   */
  private function antiXSS($input): void
  {
    $inputRestriction = [
      '/<[^>]*script/mi',
      '/<[^>]*iframe/mi',
      '/<.*[^>]on[^>,\s]*=/mi',
      '/{{.*}}/mi',
      '/<[^>]*(ng-.|data-ng.)/mi'
    ];

    foreach ($input as $content) {
      if (gettype($content) == 'array' || (gettype($content) == 'object' && $content instanceof StdClass)) {
        $this->antiXSS($content);
        continue;
      }

      foreach ($inputRestriction as $pattern) {
        if (gettype($content) == 'string' && preg_match($pattern, $content, $matches)) {
          global $_PUT;
          $info = (object) [
            "info" => (object) [
              "log_time" => date('d/m/Y H:i:s'),
              "message" => "Someone has attempted to submit possible malware whithin a request payload.",
              "suspicious_content" => $matches,
              "client" => (object) [
                "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                "ip" => $_SERVER['REMOTE_ADDR'],
                "port" => $_SERVER['REMOTE_PORT'],
              ],
              "server" => (object) [
                "ip" => $_SERVER['SERVER_ADDR'],
                "port" => $_SERVER['SERVER_PORT'],
              ],
              "request" => (object) [
                "time" => date('d/m/Y H:i:s', strtotime($_SERVER['REQUEST_TIME'])),
                "server_name" => $_SERVER['SERVER_NAME'],
                "uri" => $_SERVER['REQUEST_URI'],
                "query_string" => $_SERVER['QUERY_STRING'],
                "method" => $_SERVER['REQUEST_METHOD'],
                "params" => (object) [
                  "GET" => $_GET,
                  "POST" => $_POST,
                  "PUT" => $_PUT,
                  "REQUEST" => $_REQUEST,
                ],
                "upload" => $_FILES,
                "cookie" => $_COOKIE
              ]
            ]
          ];

          Helpers::Log()->common('security', json_encode($info));

          throw new Exception("Invalid input.", 400);
        }
      }
    }
  }
}
