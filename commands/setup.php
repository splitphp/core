<?php

namespace engine\commands;

use \engine\Cli;
use \engine\Utils;

class Setup extends Cli
{
  public function init()
  {
    $this->addCommand('', function () {

      Utils::printLn("Welcome to SPLIT PHP Framework Setup.");
      Utils::printLn("This Setup will generate a file named 'config.ini' with your settings in the root directory.");

      $configContent = file_get_contents(ROOT_PATH . '/.env.sample');

      $configContent = preg_replace('/PRIVATE_KEY\s?=\s?"generate_a_hash_to_insert_here"/', 'PRIVATE_KEY="' . hash('sha256', Utils::dataEncrypt(uniqid(), 'SETUPPRIVKEY')) . '"', $configContent);
      $configContent = preg_replace('/PUBLIC_KEY\s?=\s?"generate_a_hash_to_insert_here"/', 'PUBLIC_KEY="' . hash('sha256', Utils::dataEncrypt(uniqid(), 'SETUPPUBKEY')) . '"', $configContent);

      if(file_put_contents(ROOT_PATH . '/.env', $configContent)){
        Utils::printLn("Setup has finished successfully.");
      } else{
        Utils::printLn("There was a problem to complete the Setup.");
      }
    });
  }
}
