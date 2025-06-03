<?php
namespace SplitPHP\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Migrations extends Cli{
  public function init(){
    $this->addCommand('apply', function(){

    });
  }
}