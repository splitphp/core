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

class Setup extends Cli
{
  public function init(): void
  {
    $this->addCommand('', function () {

      Utils::printLn("Welcome to SPLIT PHP Framework Setup.");
      Utils::printLn("This Setup will generate a file named 'config.ini' with your settings in the root directory.");

      $configContent = file_get_contents(ROOT_PATH . '/.env.sample');

      $configContent = preg_replace('/PRIVATE_KEY\s?=\s?"generate_a_hash_to_insert_here"/', 'PRIVATE_KEY="' . hash('sha256', Utils::dataEncrypt(uniqid(), 'SETUPPRIVKEY')) . '"', $configContent);
      $configContent = preg_replace('/PUBLIC_KEY\s?=\s?"generate_a_hash_to_insert_here"/', 'PUBLIC_KEY="' . hash('sha256', Utils::dataEncrypt(uniqid(), 'SETUPPUBKEY')) . '"', $configContent);

      if (file_put_contents(ROOT_PATH . '/.env', $configContent)) {
        Utils::printLn("Setup has finished successfully.");
        unlink(ROOT_PATH . '/.env.sample');
      } else {
        Utils::printLn("There was a problem to complete the Setup.");
      }
    });

    $this->addCommand('help', function () {
      Utils::printLn("Usage: setup");
      Utils::printLn();
      Utils::printLn("This command initializes the framework by creating a .env file with basic environment variables.");
      Utils::printLn("It generates a private and public key for secure operations.");
      Utils::printLn();
      Utils::printLn("For more information, visit:");
      Utils::printLn("  https://www.splitphp.org");
      Utils::printLn("  https://github.com/splitphp/core");
    });
  }
}
