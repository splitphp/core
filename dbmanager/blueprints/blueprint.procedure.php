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
use SplitPHP\Database\SqlExpression;
use SplitPHP\Helpers;
use SplitPHP\Database\Sql;
use SplitPHP\ObjLoader;
use SplitPHP\Database\Dbmetadata;

/** Procedure blueprint class 
 * This class is used to define a procedure blueprint for database migrations.
 * It allows you to specify the procedure name, arguments, output, and instructions.
 * It also provides methods to obtain the SQL statements for creating, altering, or dropping the procedure
 */
final class ProcedureBlueprint extends Blueprint
{
  /**
   * @var array
   * 
   * This is the list of arguments for the procedure, if defined.
   */
  private array $args;

  /**
   * @var object|null
   * 
   * This is the output of the procedure, if defined.
   */
  private ?object $output = null;

  /**
   * @var SqlExpression
   * 
   * This is the SQL instructions for the procedure, if defined.
   */
  private SqlExpression $instructions;

  /**
   * @var Sql
   * 
   * This is the SQL builder instance used to generate SQL statements for the procedure.
   */
  private Sql $sqlBuilder;

  /**
   * ProcedureBlueprint constructor.
   *
   * @param string $name The name of the procedure.
   */
  public final function __construct(string $name)
  {
    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';

    $this->sqlBuilder = ObjLoader::load(CORE_PATH . '/database/' . DBTYPE . '/class.sql.php');


    unset($this->tableRef);
    $this->name = $name;
  }

  /**
   * Adds an argument to the procedure.
   *
   * @param string $name The name of the argument.
   * @param string $type The type of the argument.
   * @return $this
   */
  public final function withArg(string $name, string $type): self
  {
    if (isset($this->args[$name]))
      throw new Exception("Argument '{$name}' already exists in procedure '{$this->name}'.");

    $this->args[] = (object) [
      'name' => $name,
      'type' => $type
    ];

    return $this;
  }

  /**
   * Gets the list of arguments for the procedure.
   *
   * @return array The list of arguments.
   */
  public final function getArgs(): array
  {
    return $this->args ?? [];
  }

  /**
   * Defines the output for the procedure.
   *
   * @param string $name The name of the output.
   * @param string $type The type of the output.
   * @return $this
   */
  public final function outputs(string $name, string $type): self
  {
    if (isset($this->output))
      throw new Exception("Output already defined for procedure '{$this->name}'.");

    $this->output = (object) [
      'name' => $name,
      'type' => $type
    ];

    return $this;
  }

  /**
   * Gets the output of the procedure.
   *
   * @return object|null The output of the procedure, or null if not defined.
   */
  public final function getOutput(): ?object
  {
    return $this->output ?? null;
  }

  /**
   * Sets the instructions for the procedure.
   *
   * @param string $instructions The SQL instructions for the procedure.
   * @return $this
   */
  public final function setInstructions(string $instructions): self
  {
    if (isset($this->instructions))
      throw new Exception("Instructions already defined for procedure '{$this->name}'.");

    $this->instructions = new SqlExpression($instructions);

    return $this;
  }

  /**
   * Gets the SQL instructions for the procedure.
   *
   * @return SqlExpression|null The SQL instructions for the procedure, or null if not defined.
   */
  public final function getInstructions(): ?SqlExpression
  {
    return $this->instructions ?? null;
  }

  /**
   * Obtains the SQL statements for the "up" and "down" operations of a procedure migration.
   *
   * @return object The SQL statements for the procedure migration.
   */
  public function obtainSQL(): object
  {
    $dbMapper = Helpers::DbMapper();
    $result = (object) [
      'up' => null,
      'down' => null
    ];

    // -> Drop Operation:
    if ($this->isToDrop() && in_array($this->getName(), Dbmetadata::listProcedures())) {
      $result->up = $this->sqlBuilder
        ->dropProcedure(name: $this->getName())
        ->output(true);

      $currentState = $dbMapper->procedureBlueprint($this->getName());
      $result->down = $this->sqlBuilder
        ->createProcedure(
          name: $currentState->getName(),
          args: $currentState->getArgs(),
          output: $currentState->getOutput(),
          instructions: $currentState->getInstructions()
        )->output(true);
    }

    // -> Alter Operation:
    elseif (in_array($this->getName(), Dbmetadata::listProcedures())) {
      $currentState = $dbMapper->procedureBlueprint($this->getName());

      $sqlDown = clone $this->sqlBuilder;

      $sqlUp = $this->sqlBuilder
        ->dropProcedure(
          name: $currentState->getName(),
        )
        ->createProcedure(
          name: $this->getName(),
          args: $this->getArgs(),
          output: $this->getOutput(),
          instructions: $this->getInstructions()
        );

      // Set the "down" operation to the current state:
      $sqlDown
        ->dropProcedure(
          name: $this->getName(),
        )
        ->createProcedure(
          name: $currentState->getName(),
          args: $currentState->getArgs(),
          output: $currentState->getOutput(),
          instructions: $currentState->getInstructions()
        );

      $result->up = $sqlUp->output(true);
      $result->down = $sqlDown->output(true);
    }

    // -> Create Operation:
    else {
      $result->up = $this->sqlBuilder
        ->createProcedure(
          name: $this->getName(),
          args: $this->getArgs(),
          output: $this->getOutput(),
          instructions: $this->getInstructions()
        )->output(true);

      $result->down = $this->sqlBuilder
        ->dropProcedure(name: $this->getName())
        ->output(true);
    }

    return $result;
  }
}
