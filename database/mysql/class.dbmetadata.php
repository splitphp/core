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

use Exception;
use SplitPHP\Helpers;
use SplitPHP\ObjLoader;

/**
 * Class Dbmetadata
 * 
 * This class is responsible to retrieve and store database metadata.
 *
 * @package SplitPHP\Database\Mysql
 */
class Dbmetadata
{

  private const CACHE_DIR = ROOT_PATH . '/cache';
  private const CACHE_FILEPATH = self::CACHE_DIR . '/database-metadata.cache';
  /**
   * @var array $collection
   * A complete collection of database metadata, divided by tables.
   */
  private static $collection;

  /**
   * @var array $tableKeys
   * Stores table's primary keys.
   */
  private static $tableKeys;

  /** 
   * Create a new empty cache file, if it doesn't exist.
   * 
   * @return void 
   */
  public static function initCache()
  {
    try {
      if (!file_exists(self::CACHE_DIR)) {
        mkdir(self::CACHE_DIR, 0755, true);
        touch(self::CACHE_DIR);
        chmod(self::CACHE_DIR, 0755);
      }
      if (!file_exists(self::CACHE_FILEPATH)) {
        file_put_contents(self::CACHE_FILEPATH, '');
      }
    } catch (Exception $ex) {
      Helpers::Log()->add('sys_error', $ex->getMessage());
    }
  }

  /** 
   * Reads cache file to the collection. Searches for the specified table's metadata on the collection, if it's not found or $updCache is set to true, 
   * read it from the database, save it in the collection and return it. Updates the cache file with the new Dbmetadata::collections content just before
   * returning.
   * 
   * @param string $tablename
   * @param boolean $forceCacheUpd
   * @return object 
   */
  public static function tbInfo(string $tablename, bool $forceCacheUpd = false)
  {
    if (empty(self::$collection)) {
      self::$collection = self::readCache();
    }

    if (!isset(self::$collection[$tablename]) || $forceCacheUpd) {
      self::$collection[$tablename] = array();
      self::$collection[$tablename]['table'] = $tablename;

      // Get extra configs like engine, charset and collation:
      self::getTbExtraConfigs($tablename);

      // Get table's columns and primary key:
      self::getTbColumnsAndKey($tablename);

      // Get table's indexes:
      self::getTbIndexes($tablename);

      // Get foreign key references to the table:
      self::getTbReferences($tablename);

      // Get table's foreign key references to other tables:
      self::getTbReferencedTo($tablename);

      // Updatethe cache file with the new collection content:
      self::updCache();
    }

    return (object) self::$collection[$tablename];
  }

  /** 
   * Returns the specified table's primary key name from the Dbmetadata::tableKeys collection. If the key is not found in the collection,
   * read it from the database, save it in the collection, then returns it.
   * 
   * @param string $tablename
   * @return string 
   */
  public static function tbPrimaryKey(string $tablename)
  {
    if (!isset(self::$tableKeys[$tablename])) {
      $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
      $res_f = DbConnections::retrieve('main')->runsql($sql->write("SHOW KEYS FROM `" . $tablename . "` WHERE Key_name = 'PRIMARY'", array(), $tablename)->output(true));

      self::$tableKeys[$tablename] = $res_f[0]->Column_name;
    }

    return self::$tableKeys[$tablename];
  }

  /** 
   * Returns a list of all tables in the database.
   * 
   * @return array 
   */
  public static function listTables()
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
    $res = DbConnections::retrieve('main')->runsql($sql->write("SHOW TABLES")->output());

    $ret = array();
    $keyname = "Tables_in_" . DBNAME;
    foreach ($res as $t) {
      $ret[] = $t->$keyname;
    }

    return $ret;
  }

  public static function createMigrationControl()
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
    // Create Migration Table:
    $sqlObj = $sql->write(
      "CREATE TABLE IF NOT EXISTS `SPLITPHP_MIGRATION`(
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `date_exec` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `filepath` VARCHAR(255) NOT NULL,
        `mkey` TEXT NOT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    )->output(true);

    DbConnections::retrieve('main')->runMany($sqlObj);

    $sqlObj = $sql->write(
      "CREATE TABLE IF NOT EXISTS `SPLITPHP_MIGRATION_OPERATION`(
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_migration` INT UNSIGNED NOT NULL,
        `up` TEXT NOT NULL,
        `down` TEXT NOT NULL,
        PRIMARY KEY (`id`),
        KEY `operation_refto_migration` (`id_migration`),
        CONSTRAINT `fk_id_migration_refto_SPLITPHP_MIGRATION`
          FOREIGN KEY (`id_migration`)
          REFERENCES `SPLITPHP_MIGRATION` (`id`)
          ON DELETE CASCADE
          ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    )->output(true);

    DbConnections::retrieve('main')->runMany($sqlObj);
  }

  /** 
   * Deletes dbmetadata cache file, then calls Dbmetadata::initCache() method to create a new empty one.
   * 
   * @return void 
   */
  public static function clearCache()
  {
    try {
      if (file_exists(self::CACHE_FILEPATH))
        unlink(self::CACHE_FILEPATH);
    } catch (Exception $ex) {
      Helpers::Log()->add('sys_error', $ex->getMessage());
    }

    self::initCache();
  }

  public static function checkUserRequiredAccess(?string $operation = null, $throw = false): bool
  {
    try {
      // This only needs to read one row from any INFORMATION_SCHEMA table
      $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
      $sqlobj = $sql->write("SELECT 1 FROM INFORMATION_SCHEMA.TABLES LIMIT 1", [], 'INFORMATION_SCHEMA')->output(true);
      DbConnections::retrieve('main')->runsql($sqlobj);
      // If we get here, the query succeeded → user can read from INFORMATION_SCHEMA
      return true;
    } catch (Exception $ex) {
      // If the exception’s message/code mentions “access denied” (error 1142),
      // we know the user cannot SELECT on INFORMATION_SCHEMA.TABLES

      if ($throw) {
        $msg = "Database main user does not have access/permission to information_schema. "
          . (!is_null($operation) ? "{$operation} could not be performed." : "");
        throw new Exception($msg);
      }

      return false;
    }
  }

  /** 
   * Returns the data contained in the dbmetadata cache file.
   * 
   * @return array 
   */
  private static function readCache()
  {
    try {
      if (file_exists(self::CACHE_FILEPATH) && filesize(self::CACHE_FILEPATH) > 0)
        return (array) unserialize(file_get_contents(self::CACHE_FILEPATH));
      else return [];
    } catch (Exception $ex) {
      Helpers::Log()->add('sys_error', $ex->getMessage());
    }
  }

  /** 
   * Write all data contained in Dbmetadata::collection serialized into the dbmetadata cache file.
   * Returns the number of bytes written this way or false in case of failure.
   * 
   * @return integer|boolean 
   */
  private static function updCache()
  {
    try {
      if (file_exists(self::CACHE_FILEPATH))
        return file_put_contents(self::CACHE_FILEPATH, serialize(array_merge(self::readCache(), self::$collection)));
      else return false;
    } catch (Exception $ex) {
      Helpers::Log()->add('sys_error', $ex->getMessage());
    }
  }

  private static function getTbExtraConfigs($tablename)
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");

    // 1) Fetch engine & table_collation from INFORMATION_SCHEMA.TABLES:
    $res_t = DbConnections::retrieve('main')
      ->runsql(
        $sql->write(
          "SELECT ENGINE, TABLE_COLLATION
               FROM INFORMATION_SCHEMA.TABLES
               WHERE TABLE_SCHEMA = '" . DBNAME . "'
                 AND TABLE_NAME   = '" . $tablename . "'",
          array(),
          $tablename
        )->output()
      );

    // Assuming $res_t returns exactly one row:
    $engine    = $res_t[0]->ENGINE;                // e.g. "InnoDB"
    $collation = $res_t[0]->TABLE_COLLATION;       // e.g. "utf8mb4_unicode_ci"

    // 2) Derive the CHARACTER_SET_NAME by joining to COLLATION_CHARACTER_SET_APPLICABILITY:
    $res_c = DbConnections::retrieve('main')
      ->runsql(
        $sql->write(
          "SELECT CHARACTER_SET_NAME
               FROM INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY
               WHERE COLLATION_NAME = '" . $collation . "'",
          array(),
          $tablename
        )->output()
      );

    // Again, expect exactly one row:
    $charset = $res_c[0]->CHARACTER_SET_NAME;      // e.g. "utf8mb4"

    self::$collection[$tablename]['engine'] = $engine;
    self::$collection[$tablename]['charset'] = $charset;
    self::$collection[$tablename]['collation'] = $collation;
  }

  private static function getTbColumnsAndKey($tablename)
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
    $query = "
        SELECT 
            COLUMN_NAME   AS Field,
            COLUMN_TYPE   AS Type,
            IS_NULLABLE   AS `Null`,
            COLUMN_KEY    AS `Key`,
            COLUMN_DEFAULT AS `Default`,
            EXTRA         AS Extra,
            CHARACTER_SET_NAME AS Charset,
            COLLATION_NAME     AS Collation
        FROM information_schema.COLUMNS
        WHERE 
            TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
        ORDER BY ORDINAL_POSITION
    ";
    $sqlobj = $sql->write($query, array(), $tablename)->output(true);
    $res_f = DbConnections::retrieve('main')->runsql($sqlobj);

    $fields = array();
    $key = false;
    foreach ($res_f as $row) {
      self::setColumnTypeAndLength($row);

      $fields[] = $row;

      if ($row->Key === "PRI") {
        $key = (object) array(
          'keyname' => $row->Field,
          'keyalias' => $tablename . "_" . $row->Field
        );
      }
    }

    self::$collection[$tablename]['columns'] = $fields;
    self::$collection[$tablename]['key'] = $key;
  }

  /**
   * Fetches all indexes (primary + secondary) for $tablename and stores them under
   * self::$collection[$tablename]['indexes'].
   *
   * Uses INFORMATION_SCHEMA.STATISTICS so that we can see:
   *   - INDEX_NAME
   *   - NON_UNIQUE        (0 = unique index, 1 = non‐unique)
   *   - SEQ_IN_INDEX      (position of column within the index)
   *   - COLUMN_NAME
   *   - SUB_PART          (prefix length, if any)
   *   - INDEX_TYPE        (BTREE, HASH, etc.)
   *   - COLLATION         (A = ascending, D = descending, NULL if not sorted)
   *
   * @param string $tablename
   * @return void
   */
  private static function getTbIndexes(string $tablename)
  {
    // 1) Load the SQL helper
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");

    // 2) Build a query against INFORMATION_SCHEMA.STATISTICS for the current DB + table.
    //    We select exactly the columns we need. NOTE: replace DBNAME with your constant.
    $query = "
      SELECT 
          INDEX_NAME        AS IndexName,
          NON_UNIQUE        AS NonUnique,
          SEQ_IN_INDEX      AS SeqInIndex,
          COLUMN_NAME       AS ColumnName,
          SUB_PART          AS SubPart,
          INDEX_TYPE        AS IndexType,
          COLLATION         AS Collation
      FROM INFORMATION_SCHEMA.STATISTICS
      WHERE 
          TABLE_SCHEMA = '" . DBNAME . "'
        AND TABLE_NAME   = '" . $tablename . "'
      ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ";

    // 3) Prepare + execute. (We pass [] for params, since we've already inlined DBNAME & $tablename.)
    $sqlobj = $sql
      ->write($query, array(), $tablename)
      ->output(true);
    $res_i = DbConnections::retrieve('main')->runsql($sqlobj);

    // 4) Group all rows by IndexName:
    $indexes = [];
    foreach ($res_i as $row) {
      // If this is the first time we see this index, create a new object for it:
      if (!isset($indexes[$row->IndexName])) {
        $indexes[$row->IndexName] = (object) [
          'name'       => $row->IndexName,
          'non_unique' => ((int)$row->NonUnique === 1), // true if NON_UNIQUE = 1
          'type'       => $row->IndexType,              // e.g. "BTREE"
          'columns'    => []                             // we’ll fill this next
        ];
      }

      // Append the current column’s info into that index’s column list:
      $indexes[$row->IndexName]->columns[] = (object) [
        'column_name'  => $row->ColumnName,
        'seq_in_index' => (int)$row->SeqInIndex,
        'sub_part'     => $row->SubPart,      // NULL or prefix length (e.g. “10” for VARCHAR(255) INDEX(col(10))
        'collation'    => $row->Collation     // “A” or “D” or NULL
      ];
    }

    // 5) Store under self::$collection[$tablename]['indexes']
    self::$collection[$tablename]['indexes'] = $indexes;
  }

  private static function getTbReferences($tablename)
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");
    $sqlobj = $sql->write(
      "SELECT 
          TABLE_NAME,
          COLUMN_NAME,
          CONSTRAINT_NAME, 
          REFERENCED_TABLE_NAME,
          REFERENCED_COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = '" . DBNAME . "' 
        AND TABLE_NAME = '{$tablename}';",
      [],
      $tablename
    )
      ->output(true);
    $res_r = DbConnections::retrieve('main')->runsql($sqlobj);

    foreach ($res_r as $k => $v) {
      $res_r[$v->TABLE_NAME] = $v;
      unset($res_r[$k]);
    }

    self::$collection[$tablename]['references'] = $res_r;
  }

  private static function getTbReferencedTo(string $tablename)
  {
    $sql = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "/class.sql.php");

    // 1) Build a single query that joins KEY_COLUMN_USAGE ↔ REFERENTIAL_CONSTRAINTS.
    $query = "
      SELECT
          kcu.TABLE_NAME,
          kcu.COLUMN_NAME,
          kcu.CONSTRAINT_NAME,
          kcu.REFERENCED_TABLE_NAME,
          kcu.REFERENCED_COLUMN_NAME,
          rc.UPDATE_RULE,
          rc.DELETE_RULE
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS kcu
      JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS rc
        ON rc.CONSTRAINT_SCHEMA      = kcu.CONSTRAINT_SCHEMA
       AND rc.CONSTRAINT_NAME        = kcu.CONSTRAINT_NAME
       AND rc.UNIQUE_CONSTRAINT_NAME = kcu.REFERENCED_CONSTRAINT_NAME
      WHERE
          kcu.REFERENCED_TABLE_SCHEMA = '" . DBNAME . "'
        AND kcu.TABLE_NAME            = '" . $tablename . "'
      ORDER BY
          kcu.CONSTRAINT_NAME,
          kcu.ORDINAL_POSITION
    ";

    $sqlobj = $sql
      ->write($query, [], $tablename)
      ->output(true);

    $res_r = DbConnections::retrieve('main')->runsql($sqlobj);

    // 2) Group rows by REFERENCED_TABLE_NAME. If a constraint is multi‐column,
    //    we'll build an array of objects under a single key for that referenced table.
    $grouped = [];

    foreach ($res_r as $row) {
      $refTable = $row->REFERENCED_TABLE_NAME;

      // If this is the first time seeing that referenced‐table, create its array
      if (!isset($grouped[$refTable])) {
        $grouped[$refTable] = [];
      }

      // Append the column‐level info plus update/delete rules.
      $grouped[$refTable][] = (object)[
        'TABLE_NAME'             => $row->TABLE_NAME,
        'COLUMN_NAME'            => $row->COLUMN_NAME,
        'CONSTRAINT_NAME'        => $row->CONSTRAINT_NAME,
        'REFERENCED_TABLE_NAME'  => $row->REFERENCED_TABLE_NAME,
        'REFERENCED_COLUMN_NAME' => $row->REFERENCED_COLUMN_NAME,
        'UPDATE_RULE'            => $row->UPDATE_RULE,  // e.g. “CASCADE”
        'DELETE_RULE'            => $row->DELETE_RULE   // e.g. “SET NULL”
      ];
    }

    self::$collection[$tablename]['relatedTo'] = $grouped;
  }

  private static function setColumnTypeAndLength(&$row)
  {
    if (preg_match('/^([a-zA-Z]+)(?:\(([^)]+)\))?$/i', $row->Type, $m)) {
      // $m[1] is the base type (e.g. "int", "varchar", "decimal", "text")
      // $m[2] is whatever was inside the parentheses (e.g. "11", "100", "10,2"), or undefined if no parens
      $row->Datatype = strtolower($m[1]);
      $row->Length   = isset($m[2]) && $m[2] !== ''
        ? $m[2]
        : null;
    } else {
      // Fallback: if somehow it didn’t match, just treat the entire string as the type
      $row->Datatype = strtolower($row->Type);
      $row->Length   = null;
    }
  }
}

Dbmetadata::initCache();
