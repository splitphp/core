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

use SplitPHP\Utils;

class Cli extends \SplitPHP\Cli
{
  public function init(): void
  {

    $this->addCommand('', function ($input) {
      Utils::printLn("Hello and welcome to the SPLIT PHP scafolding tool!");
      Utils::printLn();
      // Opens up Service command help menu:
      if (isset($input[0]) && ($input[0] == '--help' || $input[0] == '-h')) {
        $this->showHelp('cli');
        return;
      }

      if (!$this->askProceed('CLI')) return;
      Utils::printLn();

      $pathName = !empty($input['--name']) ? $input['--name'] : $this->setPathname();
      if (strpos($pathName, '/')) {
        $pathName = explode('/', $pathName);
        $cliName = array_pop($pathName);

        $namespace = trim(MAINAPP_PATH, '/').'commands\\' . implode('\\', $pathName);
        $path = ROOT_PATH . MAINAPP_PATH . '/commands/' . implode('/', $pathName);
        if (!is_dir($path)) mkdir($path, 0775, true);
      } else {
        $cliName = $pathName;
        $namespace = trim(MAINAPP_PATH, '/').'commands';
        $path = ROOT_PATH . MAINAPP_PATH . '/commands';
      }

      $content = file_get_contents(__DIR__ . '/cli.tpl');
      $content = str_replace('__NAMESPACE__', $namespace, $content);
      $content = str_replace('__CLASSNAME__', ucfirst($cliName), $content);
      file_put_contents("{$path}/{$cliName}.php", $content);

      Utils::printLn();
      Utils::printLn("Your new CLI was created at \"{$path}/{$cliName}.php\"");
      Utils::printLn();
    });
  }

  private function showHelp($type)
  {
    $helpItems = [
      'service' => [
        'modes' => [
          '--help' => 'Show this menu.',
          '-h' => 'The shortcut form of "--help".'
        ],
        'arguments' => [
          'name' => 'the pathname of the Service. This must be a valid file/dir URI and you can make use of "/" to put your Service inside a specific directory. If the directory tree does not exist, it will be created automatically.'
        ],
      ],
      'webservice' => [
        'modes' => [
          '--help' => 'Show this menu.',
          '-h' => 'The shortcut form of "--help".'
        ],
        'arguments' => [
          'name' => 'the pathname of the Web Service. This must be a valid file/dir URI and you can make use of "/" to put your Web Service inside a specific directory. If the directory tree does not exist, it will be created automatically.'
        ],
      ],
      'cli' => [
        'modes' => [
          '--help' => 'Show this menu.',
          '-h' => 'The shortcut form of "--help".'
        ],
        'arguments' => [
          'name' => 'the pathname of the CLI. This must be a valid file/dir URI and you can make use of "/" to put your CLI inside a specific directory. If the directory tree does not exist, it will be created automatically.'
        ],
      ],
    ];

    Utils::printLn("//// HELP MENU ////");
    Utils::printLn();
    Utils::printLn("Scafolding command syntax: generate:[option] [?mode] [?argument]");
    Utils::printLn("  -> Available Options:");
    Utils::printLn("    * service: Generates a Service for your application.");
    Utils::printLn("    * webservice: Generates a Web Service for your application.");
    Utils::printLn("    * cli: Generates a CLI for your application.");
    Utils::printLn();
    Utils::printLn("Scafolding Available Modes:");
    foreach ($helpItems[$type]['modes'] as $mode => $msg) {
      Utils::printLn("  * {$mode}: {$msg}");
    }
    Utils::printLn();
    Utils::printLn("Scafolding Available arguments:");
    foreach ($helpItems[$type]['arguments'] as $arg => $msg) {
      Utils::printLn("  * {$arg}: {$msg}");
    }
  }

  private function askProceed($fileType)
  {
    $proceed = strtoupper(readline("- You're about to generate a {$fileType} into your application. Proceed? (\"Y\"=Yes; \"N\"=No;)"));
    if ($proceed == 'Y') return true;
    elseif ($proceed == 'N') {
      Utils::printLn("The operation was cancelled. No file was created.");
      return false;
    } else {
      Utils::printLn("Sorry! I couldn't understand your answer.");
      Utils::printLn('Use "Y" for "yes" and "N" for "no".');
      return $this->askProceed($fileType);
    }
  }

  private function setPathname()
  {
    Utils::printLn("Now you will define the pathname of your CLI.");
    Utils::printLn("(To find more about the pathname of a CLI, type 'generate:cli --help'.)");
    $pathname = readline("What is the full pathname of your CLI?");
    if (empty($pathname)) {
      Utils::printLn("A CLI's pathname cannot be blank or empty.");
      return $this->setPathname();
    }

    return $pathname;
  }
}
