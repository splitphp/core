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
   *
   *
   * @return void
   * @throws Exception If there is an error during the migration.
   */
  abstract public function apply();

  /**
   * Revert the migration.
   *
   * This method should be implemented by subclasses to define the operations
   * that will be executed when the migration is reverted.
   *
   * @return void
   * @throws Exception If there is an error during the migration revert.
   */
  public final function __construct()
  {
    require_once CORE_PATH . '/dbmigrations/blueprints/class.blueprint.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/dbmigrations/blueprints/blueprint.procedure.php';
    require_once CORE_PATH . '/database/class.vocab.php';

    $this->operations = [];
  }

  /**
   * Get the operations defined in this migration.
   *
   * This method returns an associative array where the keys are the names of
   * the operations (tables or procedures) and the values are objects containing
   * the blueprint, type, up, down, presql, and postsql information for each operation.
   *
   * @return array The operations defined in this migration.
   */
  public final function getOperations()
  {
    return $this->operations;
  }

  /**
   * Define a new table operation for this migration.
   *
   * @param string $name The name of the table.
   * @param string|null $label The label of the table (optional).
   * @return TableBlueprint The blueprint for the new table.
   * @throws Exception If a table with the same name already exists.
   */
  protected final function Table(string $name, ?string $label = null)
  {
    if (array_key_exists($name, $this->operations))
      throw new Exception("There already are operations defined for table '{$name}' in this migration.");

    $tbBlueprint = new TableBlueprint(name: $name, label: $label);
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

  /**
   * Define a new procedure operation for this migration.
   *
   * @param string $name The name of the procedure.
   * @return ProcedureBlueprint The blueprint for the new procedure.
   * @throws Exception If a procedure with the same name already exists.
   */
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

  /**
   * Specify the database to use for this migration. If the database does not exist,
   * it will be created.
   *
   * @param string $dbName The name of the database.
   * @return self
   */
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
