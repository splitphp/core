<?php

namespace SplitPHP\DbMigrations;

use Exception;
use SplitPHP\ObjLoader;

abstract class Migration
{
  private $operations;
  private $preSQL;
  private $postSQL;

  /**
   * Apply the migration.
   *
   * This method should be implemented by subclasses to define the operations
   * that will be executed when the migration is applied.
   */
  /**
   * @return void
   * @throws Exception If there is an error during the migration.
   */
  abstract public function apply();

  public final function __construct()
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/class.blueprint.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.procedure.php';
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
      'blueprint' => $tbBlueprint,
      'type' => 'table',
      'up' => null,
      'down' => null,
      'presql' => $this->preSQL ?? null,
      'postsql' => $this->postSQL ?? null,
    ];

    $this->preSQL = null;
    $this->postSQL = null;

    return $tbBlueprint;
  }

  protected final function Procedure($name)
  {
    if (array_key_exists($name, $this->operations))
      throw new Exception("There already are operations defined for procedure '{$name}' in this migration.");

    $procBlueprint = new ProcedureBlueprint(name: $name);
    $this->operations[$name] = (object) [
      'blueprint' => $procBlueprint,
      'type' => 'procedure',
      'up' => null,
      'down' => null,
      'presql' => $this->preSQL ?? null,
      'postsql' => $this->postSQL ?? null,
    ];

    $this->preSQL = null;
    $this->postSQL = null;

    return $procBlueprint;
  }

  protected final function onDatabase($dbName)
  {
    $sqlBuilder = ObjLoader::load(CORE_PATH . '/database/' . DBTYPE . '/class.sql.php');
    $this->preSQL = $sqlBuilder
      ->createDatabase($dbName)
      ->useDatabase($dbName)
      ->output(true);

    $this->postSQL = $sqlBuilder
      ->useDatabase(DBNAME)
      ->output(true);

    return $this;
  }
}
