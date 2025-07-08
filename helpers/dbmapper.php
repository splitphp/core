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

namespace SplitPHP\Helpers;

use SplitPHP\Database\Dbmetadata;
use SplitPHP\Database\SqlExpression;
use SplitPHP\Database\Sql;
use SplitPHP\DbManager\TableBlueprint;
use SplitPHP\DbManager\ProcedureBlueprint;
use Exception;

/** 
 * Database Mapper Class
 * This class is responsible for mapping database tables and procedures to their corresponding blueprint objects.
 */
class DbMapper
{
  /**
   * Constructor for the DbMapper class.
   * It initializes the necessary components for database mapping.
   *
   * @throws Exception If the database connection is not enabled.
   */
  public function __construct()
  {
    if (DB_CONNECT != 'on')
      throw new Exception("Database connection is \"off\". Turn it \"on\" to perform table mapping.");

    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.sql.php';
    require_once CORE_PATH . '/database/class.vocab.php';
    require_once CORE_PATH . '/dbmanager/blueprints/class.blueprint.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.table.php';
    require_once CORE_PATH . '/dbmanager/blueprints/blueprint.procedure.php';
  }

  /**
   * Returns the current state of a table as a TableBlueprint object.
   *
   * @param string $tableName The name of the table to retrieve the current state for.
   * @return TableBlueprint A TableBlueprint object representing the current state of the table.
   */
  public function tableBlueprint(string $tableName): TableBlueprint
  {
    return $this->tbCurrentStateBlueprint($tableName);
  }

  /**
   * Returns the current state of a procedure as a ProcedureBlueprint object.
   *
   * @param string $procName The name of the procedure to retrieve the current state for.
   * @return ProcedureBlueprint A ProcedureBlueprint object representing the current state of the procedure.
   */
  public function procedureBlueprint(string $procName): ProcedureBlueprint
  {
    return $this->getProcCurrentStateBlueprint($procName);
  }

  /**
   * Returns the current state of a table as a TableBlueprint object.
   *
   * @param string $tbName The name of the table to retrieve the current state for.
   * @return TableBlueprint A TableBlueprint object representing the current state of the table.
   */
  private function tbCurrentStateBlueprint(string $tbName): TableBlueprint
  {
    /**
     * Returns an object as follows:
     * stdClass(
     *  [table]      => (string)$tablename,
     *  [engine]      => (string)table's enginename (e.g. InnoDB),
     *  [charset]     => (string)table's charset (e.g. utf8mb4),
     *  [collation]   => (string)table's collation (e.g. utf8mb4_general_ci),
     *  [columns]     => [ /* array of stdClass, one per column found  ],
     *  [references] => [ /* assoc array: other_table_name => stdClass(from KEY_COLUMN_USAGE)  ],
     *  [key]        => (object)[ 'keyname'=>PRIMARY_COLUMN, 'keyalias'=>TABLENAME . "_" . PRIMARY_COLUMN ],
     *  [relatedTo]  => [ /* assoc array: referenced_table_name => stdClass(from KEY_COLUMN_USAGE)  ]
     *)
     */
    $tbmetadata = Dbmetadata::tbInfo($tbName, true);

    $blueprint = new TableBlueprint(
      name: $tbmetadata['table'],
      charset: $tbmetadata['charset'],
      collation: $tbmetadata['collation']
    );

    // Set table's columns:
    $this->tbCurrentStateColumns($tbmetadata, $blueprint);

    // Set table's indexes:
    $this->tbCurrentStateIndexes($tbmetadata, $blueprint);

    // Set table's foreign keys:
    $this->tbCurrentStateForeignKeys($tbmetadata, $blueprint);

    return $blueprint;
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's columns, 
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $blueprint The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateColumns($tbmetadata, TableBlueprint &$blueprint)
  {
    foreach ($tbmetadata['columns'] as $col) {
      $colInfo = $blueprint->Column(
        name: $col['Field'],
        type: $col['Datatype'],
        length: $col['Length']
      );

      if (!is_null($col['Charset']))
        $colInfo->setCharset($col['Charset']);

      if (!is_null($col['Collation']))
        $colInfo->setCollation($col['Collation']);

      if ('YES' === $col['Null'])
        $colInfo->nullable();

      if ($col['Extra'] === 'auto_increment')
        $colInfo->autoIncrement();

      if (!empty($col['Default']))
        $colInfo->setDefaultValue($this->prepareDefaultVal($col['Default']));
    }
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's indexes,
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $blueprint The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateIndexes($tbmetadata, TableBlueprint &$blueprint)
  {
    if (empty($tbmetadata['indexes'])) return;

    foreach ($tbmetadata['indexes'] as $idx) {
      $blueprint->Index(
        name: $idx['name'],
        type: $idx['type']
      )
        ->setColumns(array_map(function ($c) {
          return $c['column_name'];
        }, $idx['columns']));
    }
  }

  /**
   * Based on the metadata retrieved from the database, sets the current state of the table's foreign keys,
   * before any modifications are made.
   *
   * @param object $tbmetadata The metadata object containing information about the table.
   * @param TableBlueprint $blueprint The TableBlueprint object to be modified with the current state.
   */
  private function tbCurrentStateForeignKeys($tbmetadata, TableBlueprint &$blueprint)
  {
    if (empty($tbmetadata['relatedTo'])) return;

    $fks = [];
    foreach ($tbmetadata['relatedTo'] as $group) {
      foreach ($group as $fk) {
        if (!array_key_exists($fk['CONSTRAINT_NAME'], $fks)) {
          $fks[$fk['CONSTRAINT_NAME']] = (object)[
            'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
            'on_update_action' => array_flip(Sql::FKACTION_DICT)[$fk['UPDATE_RULE']],
            'on_delete_action' => array_flip(Sql::FKACTION_DICT)[$fk['DELETE_RULE']],
            'columns' => [],
            'referenced_columns' => []
          ];
        }

        $fks[$fk['CONSTRAINT_NAME']]->columns[] = $fk['COLUMN_NAME'];
        $fks[$fk['CONSTRAINT_NAME']]->referenced_columns[] = $fk['REFERENCED_COLUMN_NAME'];
      }
    }

    foreach ($fks as $fk) {
      $fkInfo = $blueprint->Foreign($fk->columns)
        ->references($fk->referenced_columns)
        ->atTable($fk->referenced_table);

      if (!is_null($fk->on_update_action))
        $fkInfo->onUpdate($fk->on_update_action);

      if (!is_null($fk->on_delete_action))
        $fkInfo->onDelete($fk->on_delete_action);
    }
  }

  /**
   * Returns the current state of a procedure (before being modified) as a ProcedureBlueprint object.
   *
   * @param string $procName The name of the procedure to retrieve the current state for.
   * @return ProcedureBlueprint A ProcedureBlueprint object representing the current state of the procedure.
   */
  private function getProcCurrentStateBlueprint(string $procName): ProcedureBlueprint
  {
    /**
     * Returns an object as follows:
     * stdClass(
     *  [name] => (string)$procName,
     *  [args] => [ /* array of stdClass, one per argument found  ],
     *  [output] => (object)[ 'name'=>OUTPUT_NAME, 'type'=>OUTPUT_TYPE ],
     *  [instructions] => (string)SQL_INSTRUCTIONS
     *)
     */
    $procMetadata = Dbmetadata::procInfo($procName);

    $blueprint = new ProcedureBlueprint(name: $procMetadata['name']);

    // Set procedure's arguments:
    foreach ($procMetadata['args'] as $arg) {
      $blueprint->withArg(name: $arg['name'], type: $arg['type']);
    }

    // Set procedure's output:
    if (!empty($procMetadata['output'])) {
      $blueprint->outputs(name: $procMetadata['output']['name'], type: $procMetadata['output']['type']);
    }

    // Set procedure's instructions:
    if (!empty($procMetadata['instructions'])) {
      $blueprint->setInstructions(instructions: $procMetadata['instructions']);
    }

    return $blueprint;
  }

  /**
   * Prepares the default value for a column based on its type. Handles raw sql, NULL, strings, and numeric values.
   *
   * @param mixed $val The default value to be prepared.
   * @return mixed The prepared default value.
   */
  private function prepareDefaultVal($val)
  {
    if ($val === 'CURRENT_TIMESTAMP') return new SqlExpression('CURRENT_TIMESTAMP');

    elseif ($val === 'NULL') return null;

    elseif (is_string($val) && !is_numeric($val))
      return "'{$val}'";

    else return $val;
  }
}
