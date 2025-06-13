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

namespace SplitPHP\Commands\Generate;

use Exception;
use SplitPHP\Cli;
use SplitPHP\ModLoader;
use SplitPHP\AppLoader;
use SplitPHP\Utils;

class Migration extends Cli
{
  public function init()
  {

    $this->addCommand('', function () {
      Utils::printLn("Usage:");
      Utils::printLn("  generate:[option] [...parameters]");
      Utils::printLn();

      Utils::printLn("AVAILABLE OPTIONS:");
      Utils::printLn("  service      [--parameter=<value>]      Generate a new service class.");
      Utils::printLn("  webservice   [--parameter=<value>]      Generate a new web service class.");
      Utils::printLn("  cli          [--parameter=<value>]      Generate a new CLI command class.");
      Utils::printLn("  migration    [--parameter=<value>]      Generate a new migration class.");
      Utils::printLn("  help                                    Show this help message.");
      Utils::printLn();

      Utils::printLn("PARAMETERS:");
      Utils::printLn("  --name=<name>         The name of the item to be generated.");
      Utils::printLn("  --module=<name>       The target module for the generated item.");
      Utils::printLn("  --help                Show detailed help for the specified option.");
      Utils::printLn();

      Utils::printLn("NOTES:");
      Utils::printLn("  This command is built-in and takes precedence over user-defined CLIs");
      Utils::printLn("  with the same name.");
    });
  }
}
