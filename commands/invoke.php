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

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\Cli;
use SplitPHP\Helpers;
use SplitPHP\Utils;

class Invoke extends Cli
{
  public function init()
  {
    // Service Option:
    $this->addCommand('service', function ($args) {
      if (!isset($args['--uri'])) throw new Exception("You must provide a '--uri' argument to invoke a service.");
      if (!isset($args['--method'])) throw new Exception("You must provide a '--method' argument to invoke a service.");

      $uri = $args['--uri'];
      $method = $args['--method'];
      unset($args['--uri'], $args['--method']);

      $result = $this->getService($uri)->$method(...$args);

      Utils::printLn("Service result: ");
      Utils::printLn($result);
    });

    // Endpoint Option:
    // $this->addCommand('endpoint', function ($args) {
    //   if (!isset($args['--route'])) throw new Exception("You must provide a '--route' argument to invoke an endpoint.");

    //   $url = str_contains($args['--route'], URL_APPLICATION) ? $args['--route'] : URL_APPLICATION . $args['--route'];
    //   unset($args['--route']);

    //   $method = strtoupper($args['--verb'] ?? 'GET');
    //   unset($args['--verb']);

    //   if (isset($args['--headers'])) {
    //     $headers = json_decode($args['--headers']);
    //     if (!is_array($headers))
    //       throw new Exception("Invalid headers format. Use a JSON array of strings.");

    //     $headers = array_filter($headers, function ($value) {
    //       return Utils::regexTest("/^[!#$%&'*+\-.^_`|~0-9A-Za-z]+:[ \t]*[^\r\n]+$/", $value);
    //     });

    //     unset($args['--headers']);
    //   } else {
    //     $headers = [];
    //   }

    //   $curl = Helpers::cURL();
    //   foreach ($headers as $header) {
    //     $curl->setHeader($header);
    //   }

    //   $response = $curl
    //     ->setData($args)
    //     ->$method($url);

    //   Utils::printLn("Endpoint response: ");
    //   Utils::printLn($response);
    // });

    // SQL Option:
    $this->addCommand('sql', function ($args) {
      if (!isset($args['--uri'])) throw new Exception("You must provide a '--uri' argument to invoke a SQL.");

      $uri = $args['--uri'];
      unset($args['--uri']);

      $result = $this->getDao('NONE')
        ->bindParams($args)
        ->find($uri);

      Utils::printLn("SQL result: ");
      Utils::printLn($result);
    });

    // Help Option:
    $this->addCommand('help', function () {
      Utils::printLn("Usage:");
      Utils::printLn("  invoke:[option] [...parameters]");
      Utils::printLn();

      Utils::printLn("AVAILABLE OPTIONS:");
      Utils::printLn("  service     [--uri=<uri>] [--method=<method>] [...parameters]   Invoke a service method with the given parameters.");
      Utils::printLn();
      // Utils::printLn("  endpoint    [--route=<route>] [--verb=<http verb>] [--headers=<headers>]");
      // Utils::printLn("              [...parameters]                                     Invoke an endpoint with the given parameters.");
      Utils::printLn();
      Utils::printLn("  sql         [--uri=<uri>] [...parameters]                       Invoke a SQL query with the given parameters.");
      Utils::printLn();
      Utils::printLn("  help                                                            Show this help message.");
      Utils::printLn();

      Utils::printLn("PARAMETERS:");
      Utils::printLn("  --uri=<uri>           The URI of the resource you want to invoke.");
      Utils::printLn("  --method=<method>     The method of the service you want to invoke.");
      // Utils::printLn("  --route=<route>       The route of the endpoint you want to invoke.");
      // Utils::printLn("  --verb=<http verb>    The HTTP verb to use when invoking the endpoint.");
      // Utils::printLn("                        Defaults to GET if not provided.");
      // Utils::printLn("  --headers=<headers>   A JSON array of headers to include in the request.");
      // Utils::printLn("                        For example: '[\"Content-Type: application/json\"]'.");
      Utils::printLn("  [...parameters]       Additional parameters to pass to the service, endpoint, or SQL query.");
      Utils::printLn();

      Utils::printLn("EXAMPLES:");
      Utils::printLn("  invoke:service --uri=/iam/user --method=getUser id=123");
      Utils::printLn("  invoke:endpoint --route=/api/users --verb=POST name=John age=30");
      Utils::printLn("  invoke:sql --uri=/users/findById id=123");
    });
  }
}
