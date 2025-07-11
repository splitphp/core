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

/**
 * Class DbCredentials
 * 
 * This class is responsible for storing and managing database connection credentials.
 *
 * @package SplitPHP\Database
 */
class DbCredentials
{
  /**
   * @var string The hostname of the database server.
   */
  private $host;

  /**
   * @var string The username for the database connection.
   */
  private $user;

  /**
   * @var string The password for the database connection.
   */
  private $pass;

  /**
   * @var int|null The port number for the database connection, if applicable.
   */
  private $port;

  /**
   * DbCredentials constructor.
   *
   * Initializes the database credentials with the provided parameters.
   *
   * @param string $host The hostname of the database server.
   * @param string $user The username for the database connection.
   * @param string $pass The password for the database connection.
   * @param int|null $port The port number for the database connection, if applicable.
   */
  public function __construct(string $host, string $user, string $pass, ?int $port = null)
  {
    $this->host = $host;
    $this->user = $user;
    $this->pass = $pass;
    $this->port = $port;
  }

  /**
   * @return string The hostname of the database server.
   */
  public function getHost(): string
  {
    return $this->host;
  }

  /**
   * @return string The username for the database connection.
   */
  public function getUser(): string
  {
    return $this->user;
  }

  /**
   * @return string The password for the database connection.
   */
  public function getPass(): string
  {
    return $this->pass;
  }

  /**
   * @return int|null The port number for the database connection, if applicable.
   */
  public function getPort(): ?int
  {
    return $this->port;
  }

  /**
   * Exports the database credentials as an associative array.
   *
   * @return array The exported database credentials.
   */
  public function export()
  {
    return [
      'host' => $this->getHost(),
      'user' => $this->getUser(),
      'pass' => $this->getPass(),
      'port' => $this->getPort()
    ];
  }
}
