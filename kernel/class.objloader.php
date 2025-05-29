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
// Copyright (c) 2025 Lightertools Open Source Community                                                                                                               //
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

namespace engine;

use Exception;
use ReflectionClass;

/**
 * Class ObjLoader
 * 
 * This class is responsible loading the classes's objects, respecting the singleton OOP concept.
 *
 * @package engine
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
  public static final function load(string $path, ?string $class = null, array $args = [])
  {
    if (!file_exists($path))
      throw new Exception("The requested path could not be found.");

    $classNames = self::getFullyQualifiedClassNames($path);
    if (empty($classNames))
      throw new Exception("The requested path does not contain an instantiable class.");

    foreach ($classNames as $clName) {
      $nameData = explode('\\', $clName);
      if ($class == end($nameData)) $class = $clName;

      if (!isset(self::$collection[$clName])) {
        try {
          include_once $path;

          $r = new ReflectionClass($clName);
          self::$collection[$clName] = $r->newInstanceArgs($args);
        } catch (Exception $ex) {
          throw $ex;
        }
      }
    }

    if (is_null($class))
      $class = $classNames[0];

    if (!isset(self::$collection[$class])) throw new Exception("The requested class {$class} does not exist in the given file path.");
    return self::$collection[$class];
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

    for ($i = 0, $len = count($tokens); $i < $len; $i++) {
      // detect namespace declaration
      if ($tokens[$i][0] === T_NAMESPACE) {
        $namespace = '';
        for ($j = $i + 1; $j < $len; $j++) {
          if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NS_SEPARATOR) {
            $namespace .= $tokens[$j][1];
          } elseif ($tokens[$j] === ';' || $tokens[$j] === '{') {
            break;
          }
        }
      }

      // detect class declaration (skip anonymous classes)
      if ($tokens[$i][0] === T_CLASS) {
        $prev = $tokens[$i - 1] ?? null;
        if (is_array($prev) && $prev[0] === T_NEW) {
          continue;
        }

        // find the class name
        for ($j = $i + 1; $j < $len; $j++) {
          if ($tokens[$j][0] === T_WHITESPACE) {
            continue;
          }
          if ($tokens[$j][0] === T_STRING) {
            $className  = $tokens[$j][1];
            $fqcn       = $namespace !== '' ? $namespace . '\\' . $className : $className;
            $classes[]  = $fqcn;
          }
          break;
        }
      }
    }

    return array_unique($classes);
  }
}
