<?php

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Utils;
use SplitPHP\Database\DbConnections;

use function PHPSTORM_META\override;

class Migrations extends Cli
{
  private $sqlBuilder;

  public function init()
  {
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "class.sql.php");

    $this->addCommand('apply', function () {
      if (DB_CONNECT != 'on')
        throw new Exception("Database connections are turned off. Turn'em 'on' to perform migrations.");

      $sql = $this->sqlBuilder->write("
        CREATE TABLE IF NOT EXISTS
      ", overwrite: true);

      DbConnections::retrieve('main')->runsql($sql->output(true));
    });
  }
}
