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
  private $httpVerb;

  /**
   * @var string $route
   * Stores the current accessed route.
   */
  private $route;

  /**
   * @var string $webServicePath
   * Stores the defined WebService class path.
   */
  private $webServicePath;

  /**
   * @var string $webServiceName
   * Stores the defined WebService class name.
   */
  private $webServiceName;

  /**
   * @var array $args
   * Stores the parameters and data passed along the request.
   */
  private $args;

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
    $this->route = $metadata->route;
    $this->webServicePath = $metadata->webServicePath;
    $this->webServiceName = $metadata->webServiceName;

    $this->args = [
      $this->route,
      $this->httpVerb
    ];
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString()
  {
    return "class:" . __CLASS__ . "(WebService:{$this->webServiceName}, Path:{$this->webServicePath}, Route:{$this->route}, HttpVerb:{$this->httpVerb})";
  }

  /** 
   * Returns the stored route.
   * 
   * @return string 
   */
  public function getRoute()
  {
    return $this->route;
  }

  /** 
   * Returns an object containing the name and the path of the Web Service class.
   * 
   * @return object 
   */
  public function getWebService()
  {
    return (object) [
      "name" => $this->webServiceName,
      "path" => $this->webServicePath
    ];
  }

  /** 
   * Returns the parameters and data passed along the request.
   * 
   * @return array 
   */
  public function getArgs()
  {
    return $this->args;
  }

  /** 
   * Returns the client's IP.
   * 
   * @return string 
   */
  public static function getUserIP()
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
}
