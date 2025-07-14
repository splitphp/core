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
use SplitPHP\Database\Sqlobj;
use SplitPHP\Database\Database;

/**
 * Abstract class for database seeds.
 *
 * @package SplitPHP\DbManager
 */
abstract class Seed
{
  /** @var array $operations The operations defined in this seed. */
  private $operations;

  /** @var string|null $preSQL SQL to be executed before the seed operations. */
  private $preSQL;

  /** @var string|null $postSQL SQL to be executed after the seed operations. */
  private $postSQL;

  /** @var string|null $previousDatabase The name of the database selected for this seed. */
  private $previousDatabase;

  /**
   * Apply the seed.
   *
   * This method should be implemented by subclasses to define the operations
   * that will be executed when the seed is applied.
   *
   *
   * @return void
   * @throws Exception If there is an error during the seed.
   */
  abstract public function apply();

  /**
   * Seed constructor.
   * This constructor initializes the seed by loading the necessary
   * classes for blueprints and database operations.
   * It also initializes the operations array to hold the seed operations.
   */
  public final function __construct()
  {
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.seed.php';
    require_once CORE_PATH . '/database/class.vocab.php';

    $this->operations = [];
    $this->preSQL = null;
    $this->postSQL = null;
    $this->previousDatabase = null;
  }

  /**
   * Get the operations defined in this seed.
   *
   * This method returns an associative array where the keys are the names of
   * the operations (tables or procedures) and the values are objects containing
   * the blueprint, type, up, down, presql, and postsql information for each operation.
   *
   * @return array The operations defined in this seed.
   */
  public final function getOperations(): array
  {
    return $this->operations;
  }

  /**
   * Get the pre-seed SQL statements.
   *
   * This method returns the SQL statements that should be executed before
   * applying the seed, such as creating the database or setting up initial
   * conditions.
   *
   * @return Sqlobj|null The pre-seed SQL statements, or null if none are defined.
   */
  public final function getPreSQL(): ?Sqlobj
  {
    return $this->preSQL;
  }

  /**
   * Get the post-seed SQL statements.
   *
   * This method returns the SQL statements that should be executed after
   * applying the seed, such as cleaning up or resetting the database context.
   *
   * @return Sqlobj|null The post-seed SQL statements, or null if none are defined.
   */
  public final function getPostSQL(): ?Sqlobj
  {
    return $this->postSQL;
  }

  /**
   * Define a new table operation for this seed.
   * This method creates a new `SeedBlueprint` instance for the specified table name
   * and adds it to the operations array.
   * 
   * @param string $tbname The name of the table for which seed data is being generated.
   * @return SeedBlueprint The blueprint for the table seed operation.
   */
  protected final function SeedTable(string $tbname, int $batchSize = 1): SeedBlueprint
  {
    $blueprint = new SeedBlueprint(tableName: $tbname, batchSize: $batchSize);
    $this->operations[$blueprint->getName()] = $blueprint;

    return $blueprint;
  }

  /**
   * Specify the database to use for this migration.
   *
   * @param string $dbName The name of the database.
   * @return self
   */
  protected final function onDatabase($dbName): self
  {
    $this->previousDatabase = Database::getName();
    Database::setName($dbName);

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
  public final function getPreviousDatabase(): ?string
  {
    return $this->previousDatabase;
  }
}
