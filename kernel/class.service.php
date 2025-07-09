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

namespace SplitPHP;

use Exception;
use SplitPHP\Database\Dao;

/**
 * Class Service
 * mixed
 * This class aims to provide an interface where the developer creates the application's Service layer, applying all the business rules, logic and database 
 * operations of the application.
 *
 * @package SplitPHP
 */
class Service
{
  /**
   * @var string $templateRoot
   * Stores the root path of the templates.
   */
  protected string $templateRoot;

  /** 
   * Runs the parent's constructor, initiate the properties, calls init() method then returns an instance of the class (constructor).
   * 
   * @return Service 
   */
  public function __construct()
  {
    $this->templateRoot = "";

    if (!defined('VALIDATION_FAILED')) define('VALIDATION_FAILED', 1);
    if (!defined('BAD_REQUEST')) define('BAD_REQUEST', 2);
    if (!defined('NOT_AUTHORIZED')) define('NOT_AUTHORIZED', 3);
    if (!defined('NOT_FOUND')) define('NOT_FOUND', 4);
    if (!defined('PERMISSION_DENIED')) define('PERMISSION_DENIED', 5);
    if (!defined('CONFLICT')) define('CONFLICT', 6);

    $this->init();
  }

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString(): string
  {
    return "class:Service:" . __CLASS__ . "()";
  }

  /** 
   * It's an empty abstract method, used to replace __construct(), in case the dev wants to initiate his Service with some initial execution, he 
   * can extend this method and perform whatever he wants on the initiation of the Service.
   * 
   * @return void 
   */
  public function init() {}

  /** 
   * This returns an instance of a service specified in $path.
   * 
   * @param string $path
   * @return mixed 
   */
  protected final function getService(string $path): mixed
  {
    if (empty($service = AppLoader::loadService($path)))
      $service = ModLoader::loadService($path);

    if (empty($service))
      throw new Exception("The requested service path could not be found.");

    return $service;
  }

  /** 
   * This loads and returns the DAO, starting an operation with the specified working table.
   * 
   * @param string $path
   * @return Dao 
   */
  protected final function getDao(?string $workingTableName = null): Dao
  {
    $dao = ObjLoader::load(CORE_PATH . "/database/class.dao.php");

    if (is_null($workingTableName)) return $dao;

    return $dao->startOperation($workingTableName);
  }

  /** 
   * Renders a template, at a location specified in $path, starting from Service::templateRoot, then returns the rendered result in a string.
   * 
   * @param string $path
   * @param array $varlist = []
   * @return string 
   */
  protected final function renderTemplate(string $path, array $varlist = []): string
  {
    $path = ltrim($path, '/');

    if (empty($content = AppLoader::loadTemplate($path, $varlist)))
      $content = ModLoader::loadTemplate($path, $varlist);

    if (empty($content))
      throw new Exception("The requested template path could not be found.");

    return $content;
  }

  /** 
   * By default, the root path of the templates is at MAINAPP_PATH/templates. With this method, you can add more directories under that.
   * 
   * @param string $path
   * @return void 
   */
  protected final function setTemplateRoot(string $path): void
  {
    if (!empty($path) && substr($path, -1) != "/") $path .= "/";
    $path = ltrim($path, '/');
    $this->templateRoot = $path;
  }
}
