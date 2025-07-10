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

class Server extends Cli
{
  public function init(): void
  {
    $this->addCommand('start', function ($input) {

      $port = empty($input['port']) ? '8000' : $input['port'];

      Utils::printLn("Starting server at localhost in port {$port}.");
      Utils::printLn();
      Utils::printLn("IMPORTANT: This server is intended for DEVELOPMENT PURPOSE ONLY. For production, we encourage you to use some solution like NGINX or APACHE web server.");
      Utils::printLn();

      if (!is_dir(ROOT_PATH . '/cache'))
        mkdir(ROOT_PATH . '/cache', 0755, true);

      $command = PHP_OS_FAMILY === 'Windows'
        ? "start /B php -S 0.0.0.0:{$port} -t " . ROOT_PATH . "\\public"
        : "php -S 0.0.0.0:{$port} -t " . ROOT_PATH . "/public > /dev/null 2>&1 & echo $! > " . ROOT_PATH . "/cache/server.pid";

      exec($command);
    });

    $this->addCommand('stop', function () {
      Utils::printLn("Stopping server...");

      if (PHP_OS_FAMILY === 'Windows') {
        $this->stopOnWindows();
      } else {
        $this->stopOnUnix();
      }
    });

    $this->addCommand('status', function () {
      Utils::printLn("Checking server status...");

      if (PHP_OS_FAMILY === 'Windows') {
        exec("netstat -ano | findstr :8000", $output);
      } else {
        exec("lsof -i :8000", $output);
      }

      if (empty($output)) {
        Utils::printLn("Server is not running.");
      } else {
        Utils::printLn("Server is running.");
      }
    });

    $this->addCommand('help', function () {
      Utils::printLn("Usage:");
      Utils::printLn("  server:[option]");
      Utils::printLn();

      Utils::printLn("AVAILABLE OPTIONS:");
      Utils::printLn("  start                           Apply pending migrations to the database.");
      Utils::printLn();
      Utils::printLn("  stop                            Roll back previously applied migrations.");
      Utils::printLn();
      Utils::printLn("  status                          Check the current status of the server.");
      Utils::printLn();
      Utils::printLn("  help                            Show this help message.");
      Utils::printLn();
    });
  }

  private function stopOnWindows()
  {
    $port = 8000; // default port or make this dynamic if needed

    // Find the PID of the process using the port
    exec("netstat -ano | findstr :$port", $output);

    if (empty($output)) {
      Utils::printLn("No server process could be found. Is the server running?");
      return;
    }

    // Extract the PID from the output
    foreach ($output as $line) {
      if (preg_match('/\s+(\d+)$/', $line, $matches)) {
        $pid = $matches[1];
        // Kill the process
        exec("taskkill /F /PID $pid", $killOutput, $status);
        if ($status === 0) {
          Utils::printLn("Server stopped successfully.");
        } else {
          Utils::printLn("Failed to stop server. Process PID: {$pid}.");
        }
        return;
      }
    }

    Utils::printLn("Could not parse PID from netstat output.");
  }

  private function stopOnUnix()
  {
    $pidFile = ROOT_PATH . '/cache/server.pid';

    if (!file_exists($pidFile)) {
      Utils::printLn("No server process could be found. Is the server running?");
      return;
    }

    $pid = trim(file_get_contents($pidFile));
    if (!$pid || !is_numeric($pid)) {
      Utils::printLn("Invalid PID in file.");
      return;
    }

    exec("kill -9 {$pid}", $output, $status);

    if ($status === 0) {
      unlink($pidFile);
      Utils::printLn("Server stopped successfully.");
    } else {
      Utils::printLn("Failed to stop server. Process PID: {$pid}.");
    }
  }
}
