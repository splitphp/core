<?php

namespace SplitPHP\DbMigrations;

use Exception;

abstract class Migration
{
  private $operations;

  abstract public function apply();

  public final function __construct()
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/dbmigrations/class.vocab.php';

    $this->operations = [];
  }

  public final function info()
  {
    return (object) get_object_vars($this);
  }

  protected final function Table($name)
  {
    if (array_key_exists($name, $this->operations))
      throw new Exception("There already are operations defined for table '{$name}' in this migration.");

    $tbBlueprint = new TableBlueprint(name: $name);
    $this->operations[$name] = (object) [
      'table' => $tbBlueprint,
      'up' => null,
      'down' => null
    ];

    return $tbBlueprint;
  }
}
