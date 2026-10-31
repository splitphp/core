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

namespace SplitPHP\Helpers;

/**
 * Class BackgroundProcess
 *
 * Launches a CLI command as an independent OS-level background process.
 * Unlike Spawn (which relies on pcntl_fork and only works under the CLI
 * SAPI), this helper uses `exec()` to start a completely new `php console`
 * process — making it safe to call from PHP-FPM or any other SAPI.
 *
 * The background process inherits no file descriptors, database connections,
 * or framework state from the caller.  It bootstraps a full SplitPHP CLI
 * environment from scratch, exactly as if the command were typed by hand.
 *
 * Usage:
 *
 *   // Fire-and-forget — caller returns immediately:
 *   Helpers::BackgroundProcess()->run('servings:generate-alias --type=serving --id=42');
 *
 * @package SplitPHP\Helpers
 */
class BackgroundProcess
{
  /**
   * Launches a CLI command in the background and returns immediately.
   *
   * The command is executed via `php <ROOT_PATH>/console <command>` with
   * stdout and stderr redirected to /dev/null so the parent process is
   * never blocked.
   *
   * @param string $command  The console command and its arguments
   *                         (e.g. 'my-cmd:action --flag=value').
   * @return void
   */
  public function run(string $command): void
  {
    $consolePath = ROOT_PATH . '/console';
    $fullCommand = sprintf(
      'php %s %s > /dev/null 2>&1 &',
      escapeshellarg($consolePath),
      $command
    );

    exec($fullCommand);
  }
}
