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

use SplitPHP\Cli;
use SplitPHP\Utils;

class Help extends Cli
{
  public function init()
  {
    $this->addCommand('', function () {
      Utils::printLn("Welcome to the SPLIT PHP Framework CLI!");
      Utils::printLn("Here’s a list of available commands and their options:");
      Utils::printLn();

      Utils::printLn("  help                          Show this help message.");
      Utils::printLn();
      Utils::printLn("  setup:[option]                Create a .env file with basic environment variables.");
      Utils::printLn("                                Options: help");
      Utils::printLn();
      Utils::printLn("  server:[option]               Start/Stop the development server.");
      Utils::printLn("                                Options: start, stop, status, help");
      Utils::printLn();
      Utils::printLn("  generate:[option]             Generate code boilerplates for common components.");
      Utils::printLn("                                Options: service, webservice, cli, migration, help");
      Utils::printLn();
      Utils::printLn("  migrations:[option] [...parameters]");
      Utils::printLn("                                Manage database migrations.");
      Utils::printLn("                                Options: apply, rollback, help");
      Utils::printLn();
      Utils::printLn("  invoke:[option] [...parameters]");
      Utils::printLn("                                Invoke a service method or SQL query.");
      Utils::printLn("                                Options: service, sql, help");
      Utils::printLn();
      Utils::printLn("For detailed help on a specific command, use:");
      Utils::printLn("  [command]:help");
      Utils::printLn();
      Utils::printLn("More information:");
      Utils::printLn("  https://www.splitphp.org");
      Utils::printLn("  https://github.com/splitphp/core");
      Utils::printLn();
      Utils::printLn("Thank you for using SPLIT PHP Framework!");
      Utils::printLn("Happy coding!");
    });
  }
}
