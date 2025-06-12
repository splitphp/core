<?php

namespace SplitPHP\DbMigrations;

use Exception;

abstract class Migration
{
  private $operations;

  abstract public function apply();

  public final function __construct()
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/class.blueprint.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/database/class.vocab.php';

    $this->operations = [];
  }

  public final function getOperations()
  {
    return $this->operations;
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
