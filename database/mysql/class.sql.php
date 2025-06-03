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

namespace SplitPHP\Database\Mysql;

use SplitPHP\Database\DbConnections;
use SplitPHP\DbMigrations\MigrationVocab;
use Exception;

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
}

/**
 * 0Class Sql
 * 
 * This is a SQL builder class, responsible for building and managing the SQL query commands. 
 *
 * @package SplitPHP/DbModules/Mysql
 */
class Sql
{

  private const DATATYPE_DICT = [
    MigrationVocab::DATATYPE_STRING => 'VARCHAR',
    MigrationVocab::DATATYPE_TEXT => 'TEXT',
    MigrationVocab::DATATYPE_INT => 'INT',
    MigrationVocab::DATATYPE_BIGINT => 'BIGINT',
    MigrationVocab::DATATYPE_DECIMAL => 'DECIMAL',
    MigrationVocab::DATATYPE_FLOAT => 'FLOAT',
    MigrationVocab::DATATYPE_DATE => 'DATE',
    MigrationVocab::DATATYPE_DATETIME => 'DATETIME',
    MigrationVocab::DATATYPE_TIME => 'TIME',
    MigrationVocab::DATATYPE_TIMESTAMP => 'TIMESTAMP',
    MigrationVocab::DATATYPE_BOOL => 'BOOLEAN',
    MigrationVocab::DATATYPE_BLOB => 'BLOB',
    MigrationVocab::DATATYPE_JSON => 'JSON',
    MigrationVocab::DATATYPE_UUID => 'CHAR(36)'
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
    $dataset = DbConnections::retrieve('main')->escapevar($dataset);

    $fields = "";
    $values = " VALUES (";

    foreach ($dataset as $key => $val) {
      if (is_array($val)) {
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
    $dataset = DbConnections::retrieve('main')->escapevar($dataset);

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
  public function where(array $params)
  {
    $where = ' WHERE ';
    if (!empty($params)) {
      foreach ($params as $cond) {
        $key = $cond->key;
        $val = $cond->value;
        $join = $cond->joint;
        $operator = $cond->operator;

        if (!is_null($join))
          $where .= ' ' . $join . ' ';

        // Full text filtering with "LIKE" operator:
        if (strtoupper($operator) == "LIKE") {
          $where .= $key . ' LIKE "%' . DbConnections::retrieve('main')->escapevar($val) . '%"';
        }
        // Filtering by lists of values with "IN/NOT IN" operators:
        else if (is_array($val)) {
          $val = DbConnections::retrieve('main')->escapevar($val);

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

          if (!empty($joined_values))
            $where .= $key . " {$operator} (" . implode(',', $joined_values) . ')' . $complementLogOp . $complement;
          else $where .= $complement;
        }
        // Filtering with NULL values:
        elseif (is_null($val)) {
          if ($operator == '<>') $where .= "{$key} IS NOT NULL";
          else $where .= "{$key} IS NULL";
        }
        // General filtering:
        else {
          $where .= $key . ' ' . $operator . ' ' . (!is_string($val) ? $val : "'" . DbConnections::retrieve('main')->escapevar($val) . "'");
        }
      }
      $this->write($where, null, false);
    }

    return $this;
  }

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
  public function output($clear = false)
  {
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

  public function create(
    string $tbName,
    array $columns,
    string $charset = 'utf8mb4',
    string $collation = 'utf8mb4_general_ci'
  ) {
    if (!empty($this->sqlstring) && substr($this->sqlstring, -1) != ';')
      $this->sqlstring .=  ';';

    $this->sqlstring .= "CREATE TABLE IF NOT EXISTS `{$tbName}`(";

    foreach ($columns as $clm) {
      $clm = (object) $clm;

      $isInt = ($clm->type == MigrationVocab::DATATYPE_INT ||
        $clm->type == MigrationVocab::DATATYPE_BIGINT);

      $this->sqlstring .= "`{$clm->name}`"
        . " " . self::DATATYPE_DICT[$clm->type]
        . ($isInt && $clm->unsigned ? " UNSIGNED" : "")
        . ($clm->type == MigrationVocab::DATATYPE_STRING ? "({$clm->length})" : "")
        . ($clm->nullable ? "" : " NOT") . " NULL"
        . (isset($clm->defaultValue) ? " DEFAULT {$clm->defaultValue}" : "")
        . ($isInt && $clm->autoIncrement ? " AUTO_INCREMENT" : "")
        . ",";
    }
    $this->sqlstring = rtrim($this->sqlstring, ",");
    $this->sqlstring .= ") ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};";
    return $this;
  }

  public function alter(string $tbName)
  {
    if (!empty($this->sqlstring) && substr($this->sqlstring, -1) != ';')
      $this->sqlstring .=  ';';

    $this->sqlstring .= "ALTER TABLE `{$tbName}` ";
  }

  /**
   * Agora o addKey gera primeiro um check no INFORMATION_SCHEMA e só faz ALTER TABLE
   * se o índice/PK ainda não existir.
   *
   * @param array|string $columns
   * @param string       $type      MigrationVocab::IDX_PRIMARY ou MigrationVocab::IDX_INDEX
   * @param string|null  $name      nome do índice (null só para PK)
   * @param string       $separator  normalmente ";" para terminar cada bloco de SQL
   */
  public function addKey(
    array|string $columns,
    string       $type,
    ?string      $name      = null,
    string       $separator = ','
  ) {
    if (is_string($columns)) {
      $columns = [$columns];
    }
    if (!in_array($type, MigrationVocab::INDEX_TYPES, true)) {
      throw new Exception("Invalid index type '{$type}'");
    }
    // Escapa colunas
    $escapedCols = [];
    foreach ($columns as $clm) {
      if (!is_string($clm) || is_numeric($clm)) {
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");
      }
      $escapedCols[] = "`{$clm}`";
    }
    $colList = implode(',', $escapedCols);

    // Se for PRIMARY KEY
    if ($type === MigrationVocab::IDX_PRIMARY) {
      // 1) Contabiliza se já existe PK na tabela via INFORMATION_SCHEMA.TABLE_CONSTRAINTS
      $tbl = $this->table;
      $this->sqlstring .= "
-- checa se já existe PRIMARY KEY em {$tbl}
SELECT COUNT(*) INTO @cnt_pk
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE()
   AND TABLE_NAME = '{$tbl}'
   AND CONSTRAINT_TYPE = 'PRIMARY KEY';

-- monta a string do ALTER somente se @cnt_pk = 0
SET @ddl_pk = IF(
  @cnt_pk = 0,
  'ALTER TABLE `{$tbl}` ADD PRIMARY KEY({$colList});',
  'SELECT \"PK de {$tbl} já existe, ignorando.\";'
);

PREPARE stmt_pk FROM @ddl_pk;
EXECUTE stmt_pk;
DEALLOCATE PREPARE stmt_pk
{$separator}
";
    }
    // Caso seja um índice “normal” (IDX_INDEX)
    else {
      // 1) Contabiliza se o índice já existe via INFORMATION_SCHEMA.STATISTICS
      $tbl = $this->table;
      $idx = $name;
      $this->sqlstring .= "
-- checa se índice '{$idx}' já existe na tabela '{$tbl}'
SELECT COUNT(*) INTO @cnt_idx
  FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = '{$tbl}'
   AND INDEX_NAME = '{$idx}';

SET @ddl_idx = IF(
  @cnt_idx = 0,
  'ALTER TABLE `{$tbl}` ADD INDEX `{$idx}` ({$colList});',
  'SELECT \"Índice {$idx} em {$tbl} já existe, ignorando.\";'
);

PREPARE stmt_idx FROM @ddl_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx
{$separator}
";
    }

    return $this;
  }

  /**
   * Agora o addConstraint checa em INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   * se a FK já existe antes de executar o ALTER TABLE ADD CONSTRAINT.
   *
   * @param string|array   $localColumns
   * @param string         $refTable
   * @param string|array   $refColumns
   * @param string|null    $name      se null, monta um nome automático
   * @param string|null    $onUpdate  (ex: 'CASCADE', 'RESTRICT' etc)
   * @param string|null    $onDelete  (ex: 'CASCADE', 'RESTRICT' etc)
   * @param string         $separator geralmente ";" para terminar o bloco
   */
  public function addConstraint(
    string|array $localColumns,
    string       $refTable,
    string|array $refColumns,
    ?string      $name      = null,
    ?string      $onUpdate  = null,
    ?string      $onDelete  = null,
    string       $separator = ','
  ) {
    if (is_string($localColumns)) {
      $localColumns = [$localColumns];
    }
    if (is_string($refColumns)) {
      $refColumns = [$refColumns];
    }
    if (is_null($name)) {
      $name = "fk_" . implode('_', $localColumns) . "_refto_{$refTable}";
    }

    // Escapa colunas locais
    $escapedLocal = [];
    foreach ($localColumns as $clm) {
      if (!is_string($clm) || is_numeric($clm)) {
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");
      }
      $escapedLocal[] = "`{$clm}`";
    }
    $locList = implode(',', $escapedLocal);

    // Escapa colunas de referência
    $escapedRef = [];
    foreach ($refColumns as $clm) {
      if (!is_string($clm) || is_numeric($clm)) {
        throw new Exception("Invalid column name '{$clm}'. Column names must be non-numeric strings.");
      }
      $escapedRef[] = "`{$clm}`";
    }
    $refList = implode(',', $escapedRef);

    $tbl = $this->table;
    $fk  = $name;
    $del = $onDelete  ?: 'NO ACTION';
    $upd = $onUpdate  ?: 'NO ACTION';

    $this->sqlstring .= "
-- checa se FK '{$fk}' já existe em '{$tbl}'
SELECT COUNT(*) INTO @cnt_fk
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE()
   AND TABLE_NAME = '{$tbl}'
   AND CONSTRAINT_NAME = '{$fk}'
   AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SET @ddl_fk = IF(
  @cnt_fk = 0,
  CONCAT(
    'ALTER TABLE `{$tbl}`',
    ' ADD CONSTRAINT `{$fk}` FOREIGN KEY ({$locList})',
    ' REFERENCES `{$refTable}` ({$refList})',
    ' ON DELETE {$del} ON UPDATE {$upd};'
  ),
  'SELECT \"FK {$fk} em {$tbl} já existe, ignorando.\";'
);

PREPARE stmt_fk FROM @ddl_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk
{$separator}
";
    return $this;
  }

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
}
