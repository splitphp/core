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
use ReflectionClass;

/**
 * Class ObjLoader
 * 
 * This class is responsible loading the classes's objects, respecting the singleton OOP concept.
 *
 * @package SplitPHP
 */
class ObjLoader
{

  /**
   * @var array $collection
   * Stores a collection of already loaded objects.
   */
  private static $collection = [];

  /** 
   * Returns the instance of a class registered on the collection. If the class instance isn't registered yet, 
   * create a new instance of that class, register it on the collection, then returns it.
   * 
   * @param string $path
   * @param string $classname
   * @param array $args = []
   * @return mixed 
   */
  public static final function load(string $filepath, array $args = [])
  {
    if (!file_exists($filepath))
      throw new Exception("The requested file path could not be found.");

    $classNames = self::getFullyQualifiedClassNames($filepath);
    if (empty($classNames))
      throw new Exception("The file at the requested path does not contain any instantiable class.");

    $result = [];
    foreach ($classNames as $clName) {
      if (!isset(self::$collection[$clName])) {
        try {
          include_once $filepath;

          $r = new ReflectionClass($clName);
          self::$collection[$clName] = $r->newInstanceArgs($args);
        } catch (Exception $ex) {
          throw $ex;
        }
      }

      $result[] = self::$collection[$clName];
    }

    if (!count($result) < 2) return $result[0];
    return $result;
  }

  private static function getFullyQualifiedClassNames(string $file): array
  {
    if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
      return [];
    }

    $source    = file_get_contents($file);
    $tokens    = token_get_all($source);
    $namespace = '';
    $classes   = [];

    // Which token-IDs count as “namespace name”
    $nsTokens = [T_STRING, T_NS_SEPARATOR];
    if (defined('T_NAME_QUALIFIED')) {
      $nsTokens[] = T_NAME_QUALIFIED;
    }
    if (defined('T_NAME_FULLY_QUALIFIED')) {
      $nsTokens[] = T_NAME_FULLY_QUALIFIED;
    }

    for ($i = 0, $len = count($tokens); $i < $len; $i++) {
      // 1) detect namespace
      if (is_array($tokens[$i]) && $tokens[$i][0] === T_NAMESPACE) {
        $namespace = '';
        for ($j = $i + 1; $j < $len; $j++) {
          $t = $tokens[$j];
          // is it one of our “namespace part” tokens?
          if (is_array($t) && in_array($t[0], $nsTokens, true)) {
            $namespace .= $t[1];
          }
          // stop at ";" or "{"
          elseif ($t === ';' || $t === '{') {
            break;
          }
        }
      }

      // 2) detect class (skip anonymous)
      if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
        $prev = $tokens[$i - 1] ?? null;
        // new class? skip
        if (is_array($prev) && $prev[0] === T_NEW) {
          continue;
        }
        // find the class name token
        for ($j = $i + 1; $j < $len; $j++) {
          if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $className = $tokens[$j][1];
            $fqcn      = $namespace !== '' ? $namespace . '\\' . $className : $className;
            $classes[] = $fqcn;
            break;
          }
          // skip any whitespace
          if ($tokens[$j] === '{' || $tokens[$j] === ';') {
            break;
          }
        }
      }
    }

    return array_unique($classes);
  }
}
