<?php

namespace __NAMESPACE__;

use SplitPHP\Cli;
use SplitPHP\Utils;

class __CLASSNAME__ extends Cli
{
  public function init(): void
  {
    /*
     * Here you can define the command string taht the user must type to execute the associated
     * handler function. The $args parameter is an associative or numeric array containing
     * any argument passed in the command line.
     * For more info, refer to SPLIT PHP docs at www.splitphp.org/docs. 
     */
    $this->addCommand('hello', function ($args) {
      Utils::printLn("Hello from CLI");
    });
  }
}
