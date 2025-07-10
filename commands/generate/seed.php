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

class Seed extends Cli
{
  public function init(): void
  {

    $this->addCommand('', function ($input) {
      Utils::printLn("Hello and welcome to the SPLIT PHP scafolding tool!");
      Utils::printLn();
      // Opens up Service command help menu:
      if (isset($input[0]) && ($input[0] == '--help' || $input[0] == '-h')) {
        $this->showHelp('seed');
        return;
      }

      if (!$this->askProceed('Seed')) return;
      Utils::printLn();

      $name = Utils::stringToSlug($input['--name'] ?? $this->setSeedName());
      $namespace = ucfirst($input['--module'] ?? 'Application') . '\Seeds';

      if (empty($input['--module'])) {
        $appMap = AppLoader::getMap();

        $path = "{$appMap->mainapp_path}/{$appMap->dbseeds_basepath}";
      } else {
        $modMap = ModLoader::getMaps($input['--module']);
        if (empty($modMap))
          throw new Exception("There is no module named '{$input['--module']}'");

        $path = "{$modMap->modulepath}/{$modMap->dbseeds_basepath}";
      }

      if (!is_dir($path)) {
        mkdir($path);
        chmod($path, 0755);
      }

      $content = file_get_contents(__DIR__ . '/seed.tpl');
      $content = str_replace('__NAMESPACE__', $namespace, $content);
      $content = str_replace('__CLASSNAME__', Utils::stringToPascalCase($name), $content);
      $tmstamp = time();
      file_put_contents("{$path}/{$tmstamp}_{$name}.php", $content);

      Utils::printLn();
      Utils::printLn("Your new Seed was created at \"{$path}/{$tmstamp}_{$name}.php\"");
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
      'seed' => [
        'modes' => [
          '--help' => 'Show this menu.',
          '-h' => 'The shortcut form of "--help".'
        ],
        'arguments' => [
          'name' => 'the name of the Seed. This must be a descriptive name od the seed. Ex.:("Create Table User").',
          'module' => "if you pretend to create the seed for a module, specify this module's name here."
        ],
      ],
    ];

    Utils::printLn("<<<< HELP MENU >>>>");
    Utils::printLn();
    Utils::printLn("Scaffolding command syntax: generate:[option] [?mode] [?argument]");
    Utils::printLn("  -> Available Options:");
    Utils::printLn("    * service: Generates a Service for your application.");
    Utils::printLn("    * webservice: Generates a Web Service for your application.");
    Utils::printLn("    * cli: Generates a CLI for your application.");
    Utils::printLn("    * seed: Generates a database seed for your application.");
    Utils::printLn();
    Utils::printLn("  -> Available modes:");
    foreach ($helpItems[$type]['modes'] as $mode => $msg) {
      Utils::printLn("    * {$mode}: {$msg}");
    }
    Utils::printLn();
    Utils::printLn("  -> Available arguments:");
    foreach ($helpItems[$type]['arguments'] as $arg => $msg) {
      Utils::printLn("    * {$arg}: {$msg}");
    }
  }

  private function askProceed($fileType)
  {
    $proceed = strtoupper(readline("- You're about to generate a {$fileType} into your application. Proceed? (\"Y\"=Yes; \"N\"=No;)"));
    if ($proceed == 'Y') return true;
    elseif ($proceed == 'N') return false;
    else {
      Utils::printLn("Sorry! I couldn't understand your answer.");
      Utils::printLn('Use "Y" for "yes" and "N" for "no".');
      return $this->askProceed($fileType);
    }
  }

  private function setSeedName()
  {
    $name = readline("Type the name of your Seed. (Ex.: 'Populate Table User'):");
    if (empty($name)) {
      Utils::printLn("Your Seed's name cannot be blank or empty.");
      return $this->setSeedName();
    }

    return $name;
  }
}
