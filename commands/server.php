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
use SplitPHP\Helpers;

class Server extends Cli
{
  public function init(): void
  {
    $this->addCommand('start', function ($input) {

      $port = empty($input['--port']) ? '8000' : $input['--port'];

      Utils::printLn("⏳ \33[93mStarting server...");
      Utils::printLn();
      
      if (file_exists(ROOT_PATH . '/cache/server.pid')) {
        Utils::printLn("❌ \33[91mA server is already running.\33[0m");
        return;
      }
      
      
      if (!is_dir(ROOT_PATH . '/cache'))
      mkdir(ROOT_PATH . '/cache', 0755, true);
    
    $command = PHP_OS_FAMILY === 'Windows'
    ? "start /B php -S 0.0.0.0:{$port} -t " . ROOT_PATH . "\\public"
    : "php -S 0.0.0.0:{$port} -t " . ROOT_PATH . "/public > /dev/null 2>&1 & echo $! > " . ROOT_PATH . "/cache/server.pid";
    
    exec($command);
    Helpers::Stash()->set('server_port', $port);
    
    Utils::printLn("✅ \33[92mSuccessfully started server at \33[94mlocalhost:{$port}\33[92m.\33[0m");
    Utils::printLn();
    Utils::printLn("⚠️ \33[33m IMPORTANT: This server is intended for DEVELOPMENT PURPOSE ONLY. For production, ");
    Utils::printLn("              we encourage you to use some solution like NGINX or APACHE web server.\33[0m");
    });

    $this->addCommand('stop', function () {
      Utils::printLn("⏳ \33[93mStopping server...");
      Utils::printLn();

      if (PHP_OS_FAMILY === 'Windows') {
        $this->stopOnWindows();
      } else {
        $this->stopOnUnix();
      }
    });

    $this->addCommand('status', function () {
      Utils::printLn("⏳ \33[93m Checking server status...");
      Utils::printLn();

      $port = Helpers::Stash()->get('server_port', '8000');

      if (PHP_OS_FAMILY === 'Windows') {
        exec("netstat -ano | findstr :{$port}", $output);
      } else {
        exec("lsof -i :{$port}", $output);
      }

      if (empty($output)) {
        Utils::printLn("❌ \33[91m Server is not running.\33[0m");
        Utils::printLn();
      } else {
        Utils::printLn("✅ \33[92m Server is running.\33[0m");
        Utils::printLn();
        Utils::printLn("➤ Listening on port \33[94m{$port}.\33[0m");
        Utils::printLn();
        Utils::printLn("➤ Process details:");
        $cols = array_values(array_filter(explode(' ', $output[0])));
        $rows = array_values(array_filter(explode(' ', $output[1])));
        $rows[8] = "{$rows[8]}$rows[9]";
        $rows = array_slice($rows, 0, 9);
        Utils::cliTable([$rows], $cols);
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
    $port = Helpers::Stash()->get('server_port', '8000');

    // Find the PID of the process using the port
    exec("netstat -ano | findstr :$port", $output);

    if (empty($output)) {
      Utils::printLn("❌ \33[91mNo server process could be found. Is the server running?\33[0m");
      return;
    }

    // Extract the PID from the output
    foreach ($output as $line) {
      if (preg_match('/\s+(\d+)$/', $line, $matches)) {
        $pid = $matches[1];
        // Kill the process
        exec("taskkill /F /PID $pid", $killOutput, $status);
        if ($status === 0) {
          Utils::printLn("✅ \33[92mServer stopped successfully.\33[0m");
        } else {
          Utils::printLn("❌ \33[91mFailed to stop server. Process PID: {$pid}.\33[0m");
        }
        return;
      }
    }

    Utils::printLn("❌ \33[91mCould not parse PID from netstat output.\33[0m");
  }

  private function stopOnUnix()
  {
    $pidFile = ROOT_PATH . '/cache/server.pid';

    if (!file_exists($pidFile)) {
      Utils::printLn("❌ \33[91mNo server process could be found. Is the server running?\33[0m");
      return;
    }

    $pid = trim(file_get_contents($pidFile));
    if (!$pid || !is_numeric($pid)) {
      Utils::printLn("❌ \33[91mInvalid PID in file.\33[0m");
      return;
    }

    exec("kill -9 {$pid}", $output, $status);

    if ($status === 0) {
      unlink($pidFile);
      Utils::printLn("✅ \33[92mServer stopped successfully.\33[0m");
    } else {
      Utils::printLn("❌ \33[91mFailed to stop server. Process PID: {$pid}.\33[0m");
    }
  }
}
