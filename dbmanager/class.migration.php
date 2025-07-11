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

namespace SplitPHP\DbManager;

use Exception;

abstract class Migration
{
  private $operations;
  private $preSQL;
  private $postSQL;
  private $selectedDatabase;

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
   * Migration constructor.
   * This constructor initializes the migration by loading the necessary
   * classes for blueprints and database operations.
   * It also initializes the operations array to hold the migration operations.
   * @throws Exception If there is an error loading the required classes.
   */
  public final function __construct()
  {
    require_once CORE_PATH . '/dbmanager/blueprints/class.blueprint.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.procedure.php';
    require_once CORE_PATH . '/database/class.vocab.php';

    $this->operations = [];
    $this->preSQL = null;
    $this->postSQL = null;
    $this->selectedDatabase = null;
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
    $this->selectedDatabase = $dbName;

    return $this;
  }

  /**
   * Get the name of the database selected for this migration.
   *
   * This method returns the name of the database that was set using the
   * onDatabase method. If no database was selected, it returns null.
   *
   * @return string|null The name of the selected database or null if none was selected.
   */
  public final function getSelectedDatabase(): ?string
  {
    return $this->selectedDatabase;
  }
}
