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

/**
 * Class Helpers
 * Provides a global access point to various helper classes within the SplitPHP framework.
 * @package SplitPHP
 */
class Helpers
{
  /**
   * Returns an instance of the Log helper class.
   * This class is used for logging messages and errors in the application.
   * @return Helpers\Log
   */
  public static function Log(): Helpers\Log
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/log.php");
  }

  /**
   * Returns an instance of the cURL helper class.
   * This class is used for making HTTP requests using cURL.
   * @return Helpers\cURL
   */
  public static function cURL(): Helpers\cURL
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/curl.php");
  }

  /**
   * Returns an instance of the MemUsage helper class.
   * This class is used for tracking memory usage in the application.
   * @return Helpers\MemUsage
   */
  public static function MemUsage(): Helpers\MemUsage
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/memusage.php");
  }

  /**
   * Returns an instance of the DbMapper helper class.
   * This class is used for mapping database structures to blueprint objects.
   * @return Helpers\DbMapper
   */
  public static function DbMapper(): Helpers\DbMapper
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/dbmapper.php");
  }
}
