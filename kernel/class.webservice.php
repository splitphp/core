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

use Exception;
use stdClass;
use SplitPHP\Database\DbConnections;

/**
 * Class WebService
 * 
 * This class aims to provide an interface where the developer creates the application's API layer, defines its endpoints, handles the requests and builds
 * standardized responses. Here's where the RESTful magic happens.
 *
 * @package SplitPHP
 */
abstract class WebService extends Service
{
  /**
   * @var array $routes
   * Stores a list of endpoint routes.
   */
  protected $routes;

  /**
   * @var array $routeIndex
   * This is a summary for the $routes list.
   */
  protected $routeIndex;

  /**
   * @var Response $response
   * Stores an instance of the class Response, used to build the standardized responses.
   */
  protected $response;

  /**
   * @var object $template404
   * Stores an object containing information about a pre-defined 404 template.
   */
  private $template404;

  /**
   * @var string $xsrfToken
   * Stores a automatically generated dynamic token, which is used to authenticate requests and ensure that the request
   * is coming from an authorized application.
   */
  private $xsrfToken;

  /**
   * @var boolean $antiXsrfValidation
   * This flag is a control to whether the requests received by the Web Service shall be authenticates by a XSRF token or not. Default = true.
   */
  private $antiXsrfValidation;

  /**
   * @var object|bool $routeEntry
   * This is a cache for the route entry found by the findRoute method, so it doesn't have to search for it again.
   */
  private object|bool $routeEntry;

  /** 
   * Defines constants for user errors, set properties with their initial values, instantiate other classes, then returns an
   * instance of the Web Service(constructor).
   * 
   * @return WebService 
   */
  public final function __construct()
  {
    require_once __DIR__ . '/class.response.php';

    $this->routes = [
      "GET" => [],
      "POST" => [],
      "PUT" => [],
      "DELETE" => []
    ];

    $this->routeIndex = [];
    $this->xsrfToken = Utils::dataEncrypt((string) Request::getUserIP(), PRIVATE_KEY);
    $this->antiXsrfValidation = true;
    $this->response = ObjLoader::load(ROOT_PATH . "/core/kernel/class.response.php");

    parent::__construct();
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString()
  {
    return "class:WebService:" . get_class($this) . "()";
  }

  /** 
   * Checks for allowed HTTP verbs, searches for the request's route in added routes list, generate a new XSRF token, executes the 
   * handler method provided for the endpoint, then respond the request with the response returned from this handler method.
   * 
   * @param string $route
   * @param string $httpVerb
   * @return Response 
   */
  public final function execute(Request $req)
  {
    $httpVerb = $req->getVerb();

    if ($httpVerb != 'GET' && $httpVerb != 'POST' && $httpVerb != 'PUT' && $httpVerb != 'DELETE') {
      http_response_code(405);
      die;
    }


    $routeEntry = $this->findRoute($req->getRoute()->url, $httpVerb);
    if (empty($routeEntry)) {
      if (!empty($this->template404)) $this->render404();

      http_response_code(404);
      die;
    }

    $this->antiXsrfValidation();

    try {
      $endpointHandler = is_callable($routeEntry->method) ? $routeEntry->method : [$this, $routeEntry->method];

      $paramMetadata = Utils::getCallableParams($endpointHandler);
      $mixedArgs = [
        ...$req->getRoute()->params,
        ...$req->getBody()
      ];
      $parameters = [];
      foreach ($paramMetadata as $param) {
        if (str_contains($param['type'], 'Request')) {
          $parameters[$param['name']] = $req;
        } elseif (array_key_exists($param['name'], $mixedArgs)) {
          $parameters[$param['name']] = $mixedArgs[$param['name']];
        }
      }

      // Retro-compatible fallback:
      if (empty($parameters))
        $parameters = [$mixedArgs];

      if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on") {
        DbConnections::retrieve('main')->startTransaction();
        $res = $this->respond(call_user_func_array($endpointHandler, $parameters));
        DbConnections::retrieve('main')->commitTransaction();
        return $res;
      } else {
        return $this->respond(call_user_func_array($endpointHandler, $parameters));
      }
    } catch (Exception $exc) {
      Utils::handleAppException($exc);

      $status = $this->userFriendlyErrorStatus($exc);
      return $this->respond(
        $this->response
          ->withStatus($status)
          ->withData([
            "error" => true,
            "user_friendly" => $status !== 500,
            "message" => $exc->getMessage(),
            "request" => System::$request,
            "route" => $req->getRoute()->url,
            "method" => $httpVerb,
            "payload" => $_REQUEST
          ])
      );
    }
  }

  /** 
   * Using the request's URL, Searches for a route in the routes's summary, where the URL and HTTP verb matches with the pattern and verb 
   * registered on the endpoint. Returns the route data or false, in case of not founding it.
   * 
   * @param string $route
   * @param string $httpVerb
   * @return object|boolean 
   */
  public final function findRoute(string $url, string $httpVerb)
  {
    if (empty($this->routeEntry)) {
      foreach ($this->routeIndex as $summary) {
        if (preg_match('/' . $summary->pattern . '$/', $url) && $httpVerb == $summary->httpVerb) {
          $this->routeEntry = $this->routes[$httpVerb][$summary->id];
          break;
        }
      }

      $this->routeEntry = $this->routeEntry ?? false;
    }

    return $this->routeEntry;
  }

  /** 
   * Registers an endpoint on the list $routes, in other words: makes an endpoint available within the Web Service, with the 
   * HTTP verb, route and handler method provided.
   * 
   * @param string $httpVerb
   * @param string $route
   * @param mixed $method
   * @param boolean $antiXsrf = null
   * @param boolean $validateInput = true
   * @return void 
   */
  protected final function addEndpoint(string $httpVerb, string $route, $method, ?bool $antiXsrf = null, bool $antiXSS = true)
  {
    if (!array_key_exists($httpVerb, $this->routes))
      throw new Exception("Attempt to add endpoint with an unknown http method.");

    if ($this->findRoute($route, $httpVerb) !== false)
      throw new Exception("Attempt to add duplicate endpoint (same route and same http method). (" . $httpVerb . " -> " . $route . ")");

    $endpointId = uniqid();
    $routeConfigs = $this->routeConfig($route);

    $this->routeIndex[] = (object) [
      "id" => $endpointId,
      "pattern" => $routeConfigs->pattern,
      "httpVerb" => $httpVerb
    ];

    $this->routes[$httpVerb][$endpointId] = (object) [
      "route" => $route,
      "verb" => $httpVerb,
      "method" => $method,
      "params" => $routeConfigs->params,
      "antiXSS" => $antiXSS,
      "antiXsrf" => is_null($antiXsrf) ? $this->antiXsrfValidation : $antiXsrf
    ];
  }

  /** 
   * Responds the request setting content type, payload, status code and headers according to the Response object passed as parameter.
   * 
   * @param Response $res
   * @return Response 
   */
  protected final function respond(Response $res)
  {
    EventListener::triggerEvent('beforeRespond', [$res]);

    http_response_code($res->getStatus());

    if (!empty($res->getData())) {
      header('Content-Type: ' . $res->getContentType());
      header('Xsrf-Token: ' . $this->xsrfToken());
      $headerExpose = 'Access-Control-Expose-Headers: ';
      foreach ($res->getHeaders() as $header) {
        $headerExpose .= explode(':', $header)[0] . ',';
        header($header);
      }
      rtrim($headerExpose, ',');
      header($headerExpose);
      echo $res->getData();
    }

    return $res;
  }

  /** 
   * Sets the information of a template that will be rendered in case of a 404 (not found) status.
   * 
   * @param string $path
   * @param array $args = []
   * @return void 
   */
  protected final function set404template(string $path, array $args = [])
  {
    $this->template404 = (object) [
      "path" => $path,
      "args" => $args
    ];
  }

  /** 
   * Returns the auto-generated XSRF token.
   * 
   * @return string 
   */
  protected final function xsrfToken()
  {
    return $this->xsrfToken;
  }

  /** 
   * Turn on/off the Anti XSRF validation.
   * 
   * @param boolean $validate
   * @return void 
   */
  protected final function setAntiXsrfValidation(bool $validate)
  {
    $this->antiXsrfValidation = $validate;
  }

  /** 
   * Configure the settings of the request's route, separating what is route and what is parameter. Returns an object containing 
   * the route and the parameters with their values set accordingly.
   * 
   * @param string $route
   * @return object 
   */
  private function routeConfig(string $route)
  {
    $result = (object) [
      "pattern" => '',
      "params" => []
    ];

    $tmp = explode('/', $route);
    foreach ($tmp as $i => $routePart) {
      if (preg_match('/\?.*\?/', $routePart)) {
        $tmp[$i] = preg_replace('/\?.*\?/', '.*', $routePart);
        $result->params[] = (object) [
          "index" => $i,
          "paramKey" => str_replace('?', '', $routePart)
        ];
      }
    }

    $result->pattern = implode('\/', $tmp);
    return $result;
  }

  /** 
   * Returns an integer representing a specific http status code for predefined types of exceptions. Defaults to 500.
   * 
   * @param Exception $exc
   * @return integer
   */
  private function userFriendlyErrorStatus(Exception $exc)
  {
    switch ($exc->getCode()) {
      case (int) VALIDATION_FAILED:
        return 422;
        break;
      case (int) BAD_REQUEST:
        return 400;
        break;
      case (int) NOT_AUTHORIZED:
        return 401;
        break;
      case (int) NOT_FOUND:
        return 404;
        break;
      case (int) PERMISSION_DENIED:
        return 403;
        break;
      case (int) CONFLICT:
        return 409;
        break;
    }

    return 500;
  }

  /** 
   * Responds the request with the rendered pre defined template for 404 cases.
   * 
   * @return void
   */
  private function render404()
  {
    $response = new Response();

    $this->respond($response->withStatus(404)->withHTML($this->renderTemplate($this->template404->path, $this->template404->args)));
    die;
  }

  /** 
   * Searches within request's data for the XSRF token and returns it. If not found, returns null.
   * 
   * @return string
   */
  private function xsrfTknFromRequest()
  {
    if (!empty($_SERVER['HTTP_XSRF_TOKEN'])) {
      return $_SERVER['HTTP_XSRF_TOKEN'];
    }

    $xsrfToken = !empty($_REQUEST['XSRF_TOKEN']) ? $_REQUEST['XSRF_TOKEN'] : (!empty($_REQUEST['xsrf_token']) ? $_REQUEST['xsrf_token'] : null);
    if (!empty($xsrfToken)) return $xsrfToken;

    return null;
  }

  /** 
   * Authenticates the XSRF token received on the request is valid.
   * 
   * @param object $routeData
   * @return void
   */
  private function antiXsrfValidation()
  {
    // Whether the request must check XSRF token:
    if ($this->routeEntry->antiXsrf) {
      $tkn = $this->xsrfTknFromRequest();

      // Check if there is a token
      if (empty($tkn)) {
        http_response_code(401);
        die;
      }

      // Check the token's authenticity
      if (Utils::dataDecrypt($tkn, PRIVATE_KEY) != Request::getUserIP()) {
        http_response_code(401);
        die;
      }
    }
  }
}
