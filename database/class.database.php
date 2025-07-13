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

/**
 * Class Database
 * 
 * This class is responsible for handling database connections and global definitions.
 *
 * @package SplitPHP
 */
class Database
{
  /**
   * @var array $connections
   * Holds the database connections indexed by their names.
   */
  private static $connections = [];

  /**
   * @var string|null $dbname
   * Holds the name of the database to be used globally.
   * If not set, it will default to the database name defined in the configuration.
   */
  private static ?string $dbname = null;

  /**
   * @var string|null $rdbms
   * Holds the name of the RDBMS (Relational Database Management System) to be used globally.
   * If not set, it will default to the RDBMS name defined in the configuration.
   */
  private static ?string $rdbms = null;

  /**
   * Gets a database connection instance. If it doesn't exist, a new one will be created, thus requiring the credentials.
   *
   * @param string $cnnName
   * @param DbCredentials|null $credentials
   * @return Dbcnn
   * @throws Exception
   */
  public static function getCnn(string $cnnName, ?DbCredentials $credentials = null)
  {
    if (!isset(self::$connections[$cnnName])) {
      if (empty($credentials))
        throw new Exception("You need to provide credentials to establish a new database connection.");

      $dbType = self::getRdbmsName();

      require_once ROOT_PATH . "/core/database/{$dbType}/class.dbcnn.php";

      self::$connections[$cnnName] = new Dbcnn($credentials);
    }

    return self::$connections[$cnnName];
  }

  /**
   * Removes a database connection instance and close the actual connection if it exists.
   *
   * @param string $cnnName
   * @return bool
   */
  public static function removeCnn(string $cnnName)
  {
    if (isset(self::$connections[$cnnName])) {
      $cnn = self::getCnn($cnnName);
      $cnn->disconnect();
      unset(self::$connections[$cnnName]);

      return true;
    }
    return false;
  }

  /**
   * Changes the credentials of an existing database connection.
   *
   * @param string $cnnName
   * @param DbCredentials|null $credentials
   * @return Dbcnn
   */
  public static function changeCnn(string $cnnName, ?DbCredentials $credentials = null)
  {
    self::removeCnn($cnnName);

    return self::getCnn($cnnName, $credentials);
  }

  /**
   * Checks if a database connection instance exists.
   *
   * @param string $cnnName
   * @return bool
   */
  public static function checkCnn(string $cnnName)
  {
    return isset(self::$connections[$cnnName]);
  }

  /**
   * Sets the name of the database to be used by default.
   *
   * @param string $name
   */
  public static function setName(string $name): void
  {
    echo "Setting database name to: $name\n";
    self::$dbname = $name;
  }

  /**
   * Sets the name of the RDBMS to be used by default.
   *
   * @param string $name
   */
  public static function setRdbmsName(string $name): void
  {
    self::$rdbms = $name;
  }

  /**
   * Gets the name of the database to be used globally.
   * If not set, it returns the default database name defined in the configuration.
   *
   * @return string
   */
  public static function getName(): string
  {
    return self::$dbname ?? DBNAME;
  }

  /**
   * Gets the name of the RDBMS to be used globally.
   * If not set, it returns the default RDBMS name defined in the configuration.
   *
   * @return string
   */
  public static function getRdbmsName(): string
  {
    return self::$rdbms ?? RDBMS;
  }
}
