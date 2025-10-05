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

namespace SplitPHP\Database;

use SplitPHP\DbManager\ForeignKeyBlueprint;
use SplitPHP\DbManager\IndexBlueprint;
use SplitPHP\DbManager\TableBlueprint;
use SplitPHP\DbManager\ColumnBlueprint;
use SplitPHP\DbManager\ProcedureBlueprint;
use Exception;
use stdClass;

require_once CORE_PATH . '/database/class.vocab.php';
/**
 * @SplitPHP\ObjLoader::ignore
 * Class SqlObj
 * 
 * This class is meant to be an input object to perform SQL queries.
 *
 * @package SplitPHP\Database\Mysql
 */
class Sqlobj
{

  /**
   * @var string $sqlstring
   * A string containing the SQL query, itself.
   */
  public $sqlstring;

  /**
   * @var string $table
   * The name of the main table where the query will be executed.
   */
  public $table;

  /** 
   * set the properties sqlstring and table, then returns an object of type Sqlobj(instantiate the class).
   * 
   * @return Sqlobj 
   */
  public final function __construct($str, $table)
  {
    $this->sqlstring = $str;
    $this->table = $table;
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public final function __toString()
  {
    return "class:Sqlobj(SqlString:{$this->sqlstring}, Table:{$this->table})";
  }

  /**
   * Appends another Sqlobj instance to this one appending its SQL string.
   *
   * @param Sqlobj $other
   * @throws Exception
   */
  public final function append(Sqlobj $other)
  {
    if (($this->table !== null && $other->table !== null) && ($this->table !== $other->table)) {
      throw new Exception("Cannot merge SQL objects from different tables.");
    }

    $this->sqlstring .= " " . $other->sqlstring;
  }

  /**
   * Prepends another Sqlobj instance to this one prepending its SQL string.
   *
   * @param Sqlobj $other
   * @throws Exception
   */
  public final function prepend(Sqlobj $other)
  {
    if (($this->table !== null && $other->table !== null) && ($this->table !== $other->table)) {
      throw new Exception("Cannot merge SQL objects from different tables.");
    }

    $this->sqlstring = $other->sqlstring . " " . $this->sqlstring;
  }
}

/**
 * Class Sql
 *
 * This is a SQL builder class, responsible for building and managing the SQL query commands.
 *
 * @package SplitPHP/Database/Mysql
 */
class Sql
{
  /**
   * @var array $DATATYPE_DICT
   * A dictionary containing the SQL data types and their corresponding MySQL data types.
   */
  public const DATATYPE_DICT = [
    DbVocab::DATATYPE_STRING => 'VARCHAR',
    DbVocab::DATATYPE_TEXT => 'TEXT',
    DbVocab::DATATYPE_INT => 'INT',
    DbVocab::DATATYPE_BIGINT => 'BIGINT',
    DbVocab::DATATYPE_DECIMAL => 'DECIMAL',
    DbVocab::DATATYPE_FLOAT => 'FLOAT',
    DbVocab::DATATYPE_DATE => 'DATE',
    DbVocab::DATATYPE_DATETIME => 'DATETIME',
    DbVocab::DATATYPE_TIME => 'TIME',
    DbVocab::DATATYPE_TIMESTAMP => 'TIMESTAMP',
    DbVocab::DATATYPE_BOOL => 'BOOLEAN',
    DbVocab::DATATYPE_BLOB => 'BLOB',
    DbVocab::DATATYPE_JSON => 'JSON',
    DbVocab::DATATYPE_UUID => 'CHAR(36)'
  ];

  /**
   * @var array $INDEX_DICT
   * A dictionary containing the SQL index types and their corresponding MySQL index types.
   */
  public const INDEX_DICT = [
    DbVocab::IDX_PRIMARY => 'PRIMARY',
    DbVocab::IDX_UNIQUE => 'UNIQUE',
    DbVocab::IDX_INDEX => 'INDEX',
    DbVocab::IDX_FULLTEXT => 'FULLTEXT',
    DbVocab::IDX_SPATIAL => 'SPATIAL'
  ];

  /**
   * @var array $FKACTION_DICT
   * A dictionary containing the SQL foreign key actions and their corresponding MySQL foreign key actions.
   */
  public const FKACTION_DICT = [
    DbVocab::FKACTION_CASCADE => 'CASCADE',
    DbVocab::FKACTION_SETNULL => 'SET NULL',
    DbVocab::FKACTION_NOACTION => 'NO ACTION',
    DbVocab::FKACTION_RESTRICT => 'RESTRICT'
  ];

  /**
   * @var string $sqlstring
   * A string containing the SQL query, itself.
   */
  private $sqlstring;

  /**
   * @var string $table
   * The name of the main table where the query will be executed.
   */
  private $table;

  ///////////////
  // CORE METHODS
  ///////////////

  /** 
   * Set Sql::sqlstring property to an empty string, then returns an object
   * of type Sql(instantiate the class).
   * 
   * @return Sql 
   */
  public final function __construct()
  {
    $this->sqlstring = "";
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public final function __toString()
  {
    return "class:SqlBuilder(SqlString:{$this->sqlstring}, Table:{$this->table})";
  }

  ///////////////
  // DATA METHODS
  ///////////////

  /** 
   * Build a insert type query command with the values passed in $dataset, set the working table with the name passed on $table, 
   * then returns the instance of the class.
   * 
   * @param object|array $dataset
   * @param string $table
   * @return Sql 
   */
  public function insert($dataset, string $table)
  {
    $dataset = Database::getCnn('main')->escapevar($dataset);

    $fields = "";
    $values = " VALUES (";

    foreach ($dataset as $key => $val) {
      if (is_array($val) || $val instanceof stdClass) {
        $fields = "";
        foreach ($val as $f => $v) {
          $fields .= $this->escape($f) . ",";
          if (!is_null($v)) $values .= (!is_string($v) ? $v : "'" . $v . "'") . ",";
          else $values .= "NULL,";
        }
        $values = rtrim($values, ",") . "),(";
      } else {
        $fields .= $this->escape($key) . ",";
        if (is_null($val)) $values .= "NULL,";
        else $values .= (!is_string($val) ? $val : "'" . $val . "'") . ",";
      }
    }
    $fields = rtrim($fields, ",") . ")";
    $values = rtrim($values, ",") . ")";
    $values = rtrim($values, "),(") . ")";

    $this->write("INSERT INTO " . $this->escape($table) . " (" . $fields . $values, $table);
    return $this;
  }

  /** 
   * Build a update type query command with the values passed in $dataset, set the working table with the name passed on $table, 
   * then returns the instance of the class.
   * 
   * @param object|array $dataset
   * @param string $table
   * @return Sql 
   */
  public function update($dataset, string $table)
  {
    $dataset = Database::getCnn('main')->escapevar($dataset);

    $sql = "UPDATE " . $this->escape($table) . " SET ";
    foreach ($dataset as $key => $val) {
      if (!is_null($val) && $val !== false && $val !== "") {
        $sql .= $this->escape($key) . "=" . (!is_string($val) ? $val : "'" . $val . "'") . ",";
      } elseif (is_null($val) || $val === "") {
        $sql .= $this->escape($key) . '=NULL,';
      }
    }
    $sql = rtrim($sql, ",");

    $this->write($sql, $table);
    return $this;
  }

  /** 
   * Build a delete type query command, setting the table passed on $table, then returns the instance of the class.
   * 
   * @param string $table
   * @return Sql 
   */
  public function delete(string $table)
  {
    $this->write("DELETE " . $this->escape($table) . " FROM " . $this->escape($table), $table);
    return $this;
  }

  /** 
   * Build a MySQL "WHERE clause" command, add it to the current SQL command, then returns the instance of the class.
   * 
   * @param array $params
   * @return Sql 
   */
  public function where(array $params = [])
  {
    $where = ' WHERE ';
    if (!empty($params)) {
      $firstIteration = true;
      foreach ($params as $cond) {
        $key = $cond->key;
        $val = $cond->value;
        $join = $cond->joint;
        $operator = $cond->operator;

        if (!is_null($join) && !$firstIteration)
          $where .= ' ' . $join . ' ';

        // Full text filtering with "LIKE" operator:
        if (strtoupper($operator) == "LIKE") {
          $where .= $key . ' LIKE "%' . Database::getCnn('main')->escapevar($val) . '%"';
        }
        // Filtering by lists of values with "IN/NOT IN" operators:
        else if (is_array($val)) {
          $val = Database::getCnn('main')->escapevar($val);

          $joinedValues = $this->listOfValues($val, $operator, $key);

          if (!$joinedValues->listIsEmpty)
            $where .= $key . " {$operator} {$joinedValues->sqlstring}";
          else $where .= $joinedValues->sqlstring;
        }
        // Filtering with NULL values:
        elseif (is_null($val)) {
          if ($operator == '<>') $where .= "{$key} IS NOT NULL";
          else $where .= "{$key} IS NULL";
        }
        // General filtering:
        else {
          $where .= $key . ' ' . $operator . ' ' . (!is_string($val) ? $val : "'" . Database::getCnn('main')->escapevar($val) . "'");
        }
        $firstIteration = false;
      }
      $this->write($where, null, false);
    }

    return $this;
  }

  public function listOfValues(array $val, string $operator, string $key): object
  {
    $joined_values = array();
    $hasNullValue = false;
    if (!empty($val)) {
      foreach ($val as $in_val) {
        if (is_null($in_val)) $hasNullValue = true;
        else $joined_values[] = !is_string($in_val) ? $in_val : '"' . $in_val . '"';
      }
    } else $hasNullValue = true;

    $complement = '';
    $complementLogOp = '';
    if ($hasNullValue) {
      if ($operator == 'NOT IN') {
        $complement = " {$key} IS NOT NULL";
        $complementLogOp = ' AND';
      } else {
        $complement = " {$key} IS NULL";
        $complementLogOp = ' OR';
      }
    }

    $result = '';
    if (!empty($joined_values))
      $result .= "(" . implode(',', $joined_values) . ')' . $complementLogOp . $complement;
    else $result .= $complement;

    return (object) [
      'sqlstring' => $result,
      'listIsEmpty' => empty($joined_values)
    ];
  }

  ///////////////////
  //STRUCTURE METHODS
  ///////////////////

  /**
   * Creates a new database with the name passed in $dbName.
   * Returns the instance of the class.
   *
   * @param string $dbName
   * @return Sql
   */
  public function createDatabase(string $dbName)
  {
    if (!is_string($dbName) || is_numeric($dbName))
      throw new Exception("Invalid database name '{$dbName}'. Database names must be non-numeric strings.");

    $this->sqlstring .= "CREATE DATABASE IF NOT EXISTS `{$dbName}`;";
    return $this;
  }

  /**
   * Drops a database with the name passed in $dbName.
   * Returns the instance of the class.
   *
   * @param string $dbName
   * @return Sql
   */
  public function dropDatabase(string $dbName)
  {
    if (!is_string($dbName) || is_numeric($dbName))
      throw new Exception("Invalid database name '{$dbName}'. Database names must be non-numeric strings.");

    $this->sqlstring .= "DROP DATABASE IF EXISTS `{$dbName}`;";
    return $this;
  }

  /** 
   * Create a new table with the name passed in $tbName, with the columns passed in $columns, and returns the instance of the class.
   * 
   * @param string $tbName
   * @param array $columns
   * @param string $charset = 'utf8mb4'
   * @param string $collation = 'utf8mb4_general_ci'
   * @return Sql 
   */
  public function createTable(TableBlueprint $table): self
  {
    $tbName = $table->getName();
    $columns = $table->getColumns();
    $charset = $table->getCharset();
    $collation = $table->getCollation();

    if (empty($columns))
      throw new Exception("Cannot create table '{$tbName}' without columns.");

    $this->table = $tbName;
    $this->statementClosure();

    $this->sqlstring .= "CREATE TABLE IF NOT EXISTS `{$tbName}`(";

    foreach ($columns as $clm) {
      $isInt = ($clm->getType() == DbVocab::DATATYPE_INT ||
        $clm->getType() == DbVocab::DATATYPE_BIGINT);

      $this->sqlstring .= "`{$clm->getName()}`"
        . " " . self::DATATYPE_DICT[$clm->getType()]
        . ($isInt && !empty($clm->isUnsigned()) ? " UNSIGNED" : "")
        . ($clm->getType() == DbVocab::DATATYPE_STRING ? "({$clm->getLength()})" : "")
        . ($clm->isNullable() ? "" : " NOT") . " NULL"
        . ($clm->getDefaultValue() ? " DEFAULT {$clm->getDefaultValue()}" : "")
        . ",";
    }
    $this->sqlstring = rtrim($this->sqlstring, ",");
    $this->sqlstring .= ") ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};";
    return $this;
  }

  /** 
   * Modify a column to be auto-incrementing, setting the column name with $columnName.
   * If $drop is set to true, it will drop the auto-increment property.
   * Returns the instance of the class.
   * 
   * @param string $columnName
   * @param bool $drop = false
   * @return Sql 
   */
  public function columnAutoIncrement(ColumnBlueprint $column, bool $drop = false)
  {
    $columnName = $column->getName();

    if (!is_string($columnName) || is_numeric($columnName))
      throw new Exception("Invalid column name '{$columnName}'. Column names must be non-numeric strings.");

    $this->sqlstring .= "MODIFY COLUMN `{$columnName}` "
      . self::DATATYPE_DICT[DbVocab::DATATYPE_INT]
      . ($drop  ? ";" : " AUTO_INCREMENT;");

    return $this;
  }

  /**
   * Drop a table with the name passed in $tbName.
   * Returns the instance of the class.
   *
   * @param string $tbName
   * @return Sql
   */
  public function dropTable(string $tbName)
  {
    $this->table = $tbName;
    $this->statementClosure();

    $this->sqlstring .= "DROP TABLE IF EXISTS `{$tbName}`;";

    return $this;
  }

  /**
   * Alter an existing table with the name passed in $tbName.
   * Returns the instance of the class.
   *
   * @param string $tbName
   * @return Sql
   */
  public function alterTable(string $tbName)
  {
    $this->table = $tbName;
    $this->statementClosure();

    $this->sqlstring .= "ALTER TABLE `{$tbName}` ";

    return $this;
  }

  /**
   * Adds a new column to the table.
   *
   * @param string $name
   * @param string $type
   * @param int|null $length
   * @param bool $nullable
   * @return Sql
   */
  public function addColumn(ColumnBlueprint $column)
  {
    $name = $column->getName();
    $type = $column->getType();
    $length = $column->getLength();
    $nullable = $column->isNullable();
    $unsigned = $column->isUnsigned();
    $autoIncrement = $column->hasAutoIncrement();
    $defaultValue = $column->getDefaultValue();

    if (!in_array($type, DbVocab::DATATYPES_ALL))
      throw new Exception("Invalid data type '{$type}'");

    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid column name '{$name}'. Column names must be non-numeric strings.");

    $this->sqlstring .= "ADD COLUMN `{$name}` "
      . self::DATATYPE_DICT[$type]
      . ($unsigned && ($type == DbVocab::DATATYPE_INT || $type == DbVocab::DATATYPE_BIGINT) ? " UNSIGNED" : "")
      . ($type == DbVocab::DATATYPE_STRING ? "({$length})" : "")
      . ($nullable ? "" : " NOT") . " NULL"
      . (isset($defaultValue) ? " DEFAULT {$defaultValue}" : "")
      . ($autoIncrement && ($type == DbVocab::DATATYPE_INT || $type == DbVocab::DATATYPE_BIGINT) ? " AUTO_INCREMENT" : "")
      . ",";

    return $this;
  }

  /**
   * Change an existing column in the table.
   *
   * @param string $name
   * @param string $type
   * @param int|null $length
   * @param bool $nullable
   * @return Sql
   */
  public function changeColumn(ColumnBlueprint $column)
  {
    $name = $column->getName();
    $type = $column->getType();
    $length = $column->getLength();
    $nullable = $column->isNullable();
    $unsigned = $column->isUnsigned();
    $autoIncrement = $column->hasAutoIncrement();
    $defaultValue = $column->getDefaultValue();

    if (!in_array($type, DbVocab::DATATYPES_ALL))
      throw new Exception("Invalid data type '{$type}'");

    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid column name '{$name}'. Column names must be non-numeric strings.");

    $this->sqlstring .= "CHANGE COLUMN `{$name}` `{$name}` "
      . self::DATATYPE_DICT[$type]
      . ($unsigned && ($type == DbVocab::DATATYPE_INT || $type == DbVocab::DATATYPE_BIGINT) ? " UNSIGNED" : "")
      . ($type == DbVocab::DATATYPE_STRING ? "({$length})" : "")
      . ($nullable ? "" : " NOT") . " NULL"
      . (isset($defaultValue) ? " DEFAULT {$defaultValue}" : "")
      . ($autoIncrement && ($type == DbVocab::DATATYPE_INT || $type == DbVocab::DATATYPE_BIGINT) ? " AUTO_INCREMENT" : "")
      . ",";

    return $this;
  }

  /**
   * Drop a column from the table.
   *
   * @param string $name
   * @return Sql
   */
  public function dropColumn(string $name)
  {
    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid column name '{$name}'. Column names must be non-numeric strings.");

    $this->sqlstring .= "DROP COLUMN `{$name}`,";

    return $this;
  }

  /**
   * @param array|string $columns
   * @param string       $type  
   * @param string|null  $name  
   * @param string       $separator
   */
  public function addIndex(IndexBlueprint $index)
  {
    $columns = $index->getColumns();
    $type = $index->getType() ?? DbVocab::IDX_INDEX;
    $name = $index->getName();

    if (is_string($columns)) $columns = [$columns];

    if (!in_array($type, DbVocab::INDEX_TYPES))
      throw new Exception("Invalid index type '{$type}'");

    foreach ($columns as &$clm) {
      if (!is_string($clm) || is_numeric($clm))
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");

      $clm = "`{$clm}`";
    }

    if (empty($name)) {
      $name = "idx_" . uniqid() . "_" . implode('_', $columns);
    }

    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid index name '{$name}'. Index names must be non-numeric strings.");


    $this->sqlstring .= " ADD" . ($type == DbVocab::IDX_INDEX ? '' : " " . self::INDEX_DICT[$type]) . " KEY"
      . ($type == DbVocab::IDX_PRIMARY ? '' : "`{$name}`")
      . "(" . implode(',', $columns) . "),";

    return $this;
  }

  /**
   * Drops an index from the table.
   *
   * @param string $name
   * @return Sql
   */
  public function dropIndex(string $name)
  {
    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid index name '{$name}'. Index names must be non-numeric strings.");

    $this->sqlstring .= " DROP INDEX `{$name}`,";

    return $this;
  }

  /**
   * Adds a foreign key constraint to the table.
   *
   * @param string|array $localColumns
   * @param string $refTable
   * @param string|array $refColumns
   * @param ?string $name
   * @return Sql
   */
  public function addConstraint(ForeignKeyBlueprint $fk)
  {
    $localColumns = $fk->getLocalColumns();
    $refTable = $fk->getReferencedTable();
    $refColumns = $fk->getReferencedColumns();
    $name = $fk->getName();
    $onUpdateAction = $fk->getOnUpdateAction() ?? DbVocab::FKACTION_NOACTION;
    $onDeleteAction = $fk->getOnDeleteAction() ?? DbVocab::FKACTION_NOACTION;

    if (is_string($localColumns)) $localColumns = [$localColumns];
    if (is_string($refColumns)) $refColumns = [$refColumns];

    foreach ($localColumns as &$clm) {
      if (!is_string($clm) || is_numeric($clm))
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");

      $clm = "`{$clm}`";
    }
    foreach ($refColumns as &$clm) {
      if (!is_string($clm) || is_numeric($clm))
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");

      $clm = "`{$clm}`";
    }

    if (!in_array($onUpdateAction, DbVocab::FKACTIONS) || !in_array($onDeleteAction, DbVocab::FKACTIONS))
      throw new Exception("Invalid Foreign Key action detected. Available actions: " . implode(', ', DbVocab::FKACTIONS));

    $name = $name ?? "fk_" . uniqid();

    $onDeleteAction = self::FKACTION_DICT[$onDeleteAction];
    $onUpdateAction = self::FKACTION_DICT[$onUpdateAction];

    $this->sqlstring .= " ADD CONSTRAINT `{$name}` FOREIGN KEY ("
      . implode(',', $localColumns) . ") REFERENCES `{$refTable}` ("
      . implode(',', $refColumns) . ") "
      . "ON DELETE {$onDeleteAction} "
      . "ON UPDATE {$onUpdateAction},";

    return $this;
  }

  /**
   * Drops a foreign key constraint from the table.
   *
   * @param string $name
   * @return Sql
   */
  public function dropConstraint(ForeignKeyBlueprint $fk, bool $restartStatement = false)
  {
    $name = $fk->getName();
    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid constraint name '{$name}'. Constraint names must be non-numeric strings.");

    $this->sqlstring .= " DROP FOREIGN KEY `{$name}`,";

    $tb = $fk->getTableRef();
    if (!empty($tb->getIndexes(idxName: $name))) {
      $this->alterTable($tb->getName());
      $this->dropIndex($name);

      if ($restartStatement)
        $this->alterTable($tb->getName());
    }

    return $this;
  }

  ////////////////////
  // PROCEDURE METHODS
  ////////////////////

  /**
   * Creates a new stored procedure.
   *
   * @param string $name
   * @param string $instructions
   * @param array $args
   * @param ?object $output
   * @return Sql
   */
  public function createProcedure(ProcedureBlueprint $procedure)
  {
    $name = $procedure->getName();
    $instructions = $procedure->getInstructions();
    $args = $procedure->getArgs();
    $output = $procedure->getOutput();

    $this->statementClosure();

    $this->sqlstring .= "-- PROCEDURE: `{$name}`\n
    CREATE PROCEDURE `{$name}` (";

    foreach ($args as $arg) {
      if (!is_string($arg->name) || is_numeric($arg->name))
        throw new Exception("Invalid argument name '{$arg->name}'. Argument names must be non-numeric strings.");

      if (!in_array($arg->type, DbVocab::DATATYPES_ALL))
        throw new Exception("Invalid data type '{$arg->type}' for argument '{$arg->name}'");

      $arg->type = self::DATATYPE_DICT[$arg->type];
      $this->sqlstring .= "IN {$arg->name} {$arg->type}, ";
    }

    if ($output) {
      if (!is_string($output->name) || is_numeric($output->name))
        throw new Exception("Invalid output name '{$output->name}'. Output names must be non-numeric strings.");

      $this->sqlstring .= " OUT {$output->name} `{$output->type}`";
    }

    $this->sqlstring = rtrim($this->sqlstring, ", ") . ")";

    $this->sqlstring .= "\nBEGIN\n {$instructions} \nEND;";

    return $this;
  }

  /**
   * Drops a stored procedure.
   *
   * @param string $name
   * @return Sql
   */
  public function dropProcedure(string $name)
  {
    $this->statementClosure();

    if (!is_string($name) || is_numeric($name))
      throw new Exception("Invalid procedure name '{$name}'. Procedure names must be non-numeric strings.");

    $this->sqlstring .= "DROP PROCEDURE IF EXISTS `{$name}`;";

    return $this;
  }

  /**
   * Invokes a stored procedure.
   *
   * @param string $name
   * @param array $arguments = []
   * @return Sql
   */
  public function invokeProcedure(
    string $name,
    array $arguments = []
  ): Sqlobj {
    foreach ($arguments as &$arg) {
      if (is_array($arg) || $arg instanceof stdClass) {
        $arg = json_encode($arg);
      } elseif (is_string($arg)) {
        $arg = "'{$arg}'";
      }
    }
    $paramList = implode(',', $arguments);

    return $this->write("CALL $name($paramList)", null, true)->output(true);
  }

  ////////////////
  // USAGE METHODS
  ////////////////

  /** 
   * Registers or updates the SQL command in the instance of the class and returns the instance of the class.
   * 
   * @param string $sqlstr
   * @param string $table = null
   * @param boolean $overwrite = true
   * @return Sql 
   */
  public function write($sqlstr, $table = null, $overwrite = true)
  {
    if ($overwrite) {
      $this->sqlstring = $sqlstr;
      $this->table = $table;
    } else {
      $this->sqlstring .= $sqlstr;
    }

    return $this;
  }

  /** 
   * Create an instance of the Sqlobj input class, which reflects the state of the instance of this class and returns it.
   * If $clear tag is set to true, reset the state of this class.
   * 
   * @param boolean $clear = false
   * @return Sqlobj 
   */
  public function output($clear = false): Sqlobj
  {
    $this->sqlstring = rtrim($this->sqlstring, ",");
    if (!empty($this->sqlstring) && substr($this->sqlstring, -1) != ';') {
      $this->sqlstring .= ';';
    }
    $obj = new Sqlobj($this->sqlstring, $this->table);

    if ($clear)
      $this->reset();

    return $obj;
  }

  /** 
   * Reset the state of the instance of this class, setting Sql::sqlstring and Sql::table to their initial values.
   * Returns the instance of the class.
   * 
   * @return Sql 
   */
  public function reset()
  {
    $this->sqlstring = "";
    $this->table = null;
    return $this;
  }

  /**
   * Selects a database to use.
   *
   * @param string $dbName
   * @return Sql
   */
  public function useDatabase(string $dbName)
  {
    if (!is_string($dbName) || is_numeric($dbName))
      throw new Exception("Invalid database name '{$dbName}'. Database names must be non-numeric strings.");

    $this->sqlstring .= "USE `{$dbName}`;";
    return $this;
  }

  //////////////////
  // PRIVATE METHODS
  //////////////////

  /** 
   * Escapes a value, surrounding it between two grave accents (`), then returns this modified value.
   * 
   * @param mixed $val
   * @return string 
   */
  private function escape($val)
  {
    return $val == "*" ? $val : "`" . $val . "`";
  }

  /**
   * Closes the current SQL statement, appending a semicolon if necessary.
   *
   * @return void
   */
  private function statementClosure()
  {
    if (!empty($this->sqlstring) && substr($this->sqlstring, -1) != ';') {
      $this->sqlstring = rtrim($this->sqlstring, ",");
      $this->sqlstring .=  ';';
    }
  }
}
