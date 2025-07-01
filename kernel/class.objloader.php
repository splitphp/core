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
use Throwable;

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
      throw new Exception("The requested file path \"{$filepath}\" could not be found.");

    $classNames = self::getClassesInFile($filepath);
    if (empty($classNames))
      throw new Exception("The file at the requested path does not contain any instantiable class.");

    $result = [];
    foreach ($classNames as $clName) {
      if (!isset(self::$collection[$clName])) {
        try {
          include_once $filepath;
          if (class_exists($clName) === false) {
            throw new Exception("The class \"{$clName}\" could not be found in the file at path \"{$filepath}\".");
          }

          $r = new ReflectionClass($clName);
          self::$collection[$clName] = $r->newInstanceArgs($args);
        } catch (Throwable $err) {
          throw new Exception(
            "While instantiating \"{$clName}\": " . $err->getMessage(),
            $err->getCode(),
            $err
          );
        }
      }

      $result[] = self::$collection[$clName];
    }

    if (!count($result) < 2) return $result[0];
    return $result;
  }

  public static function getClassesInFile(string $file): array
  {
    if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
      return [];
    }
    
    $source = file_get_contents($file);
    $tokens = token_get_all($source);
    $namespace = '';
    $classes   = [];

    // annotation-driven ignores
    $ignoreNamespaces = [];
    $ignoreClasses    = [];
    $pendingIgnore    = false;

    // Which token-IDs count as “namespace name”
    $nsTokens = [T_STRING, T_NS_SEPARATOR];
    if (defined('T_NAME_QUALIFIED')) {
      $nsTokens[] = T_NAME_QUALIFIED;
    }
    if (defined('T_NAME_FULLY_QUALIFIED')) {
      $nsTokens[] = T_NAME_FULLY_QUALIFIED;
    }

    for ($i = 0, $len = count($tokens); $i < $len; $i++) {
      $t = $tokens[$i];

      //
      // 0) detect “ignore” annotation in any comment or attribute
      //
      if (is_array($t) && ($t[0] === T_DOC_COMMENT || $t[0] === T_COMMENT)) {
        // you could change the marker to whatever you like
        if (preg_match('/@SplitPHP\\\\ObjLoader::ignore\b/', $t[1])) {
          $pendingIgnore = true;
        }
      }
      // (PHP 8 attributes are a bit more involved—if you want to support them,
      //   look for T_ATTRIBUTE and then parse the name tokens in a similar way.)

      //
      // 1) detect namespace
      //
      if (is_array($t) && $t[0] === T_NAMESPACE) {
        // collect the namespace
        $ns = '';
        for ($j = $i + 1; $j < $len; $j++) {
          $u = $tokens[$j];
          if (is_array($u) && in_array($u[0], $nsTokens, true)) {
            $ns .= $u[1];
          } elseif ($u === ';' || $u === '{') {
            break;
          }
        }
        // if we had an “ignore” right before this, remember it
        if ($pendingIgnore) {
          $ignoreNamespaces[] = $ns;
          $pendingIgnore     = false;
        }

        $namespace = $ns;
        continue;
      }

      //
      // 2) detect class (skip anonymous)
      //
      if (is_array($t) && $t[0] === T_CLASS) {
        // skip “new class” (anonymous)
        $prev = $tokens[$i - 1] ?? null;
        if (is_array($prev) && $prev[0] === T_NEW) {
          continue;
        }

        // find the class name
        for ($j = $i + 1; $j < $len; $j++) {
          $u = $tokens[$j];
          if (is_array($u) && $u[0] === T_STRING) {
            $className = $u[1];
            $fqcn      = $namespace !== '' ? $namespace . '\\' . $className : $className;

            // if we had an “ignore” right before this, remember it
            if ($pendingIgnore) {
              $ignoreClasses[] = $fqcn;
              $pendingIgnore   = false;
              break; // don’t add it to $classes
            }

            // otherwise, only add it if it’s not in an ignored namespace
            $inIgnoredNs = false;
            foreach ($ignoreNamespaces as $ignNs) {
              if ($namespace === $ignNs) {
                $inIgnoredNs = true;
                break;
              }
            }
            if (! $inIgnoredNs) {
              $classes[] = $fqcn;
            }
            break;
          }
          // if we hit “{” or “;” before a name, abandon
          if ($u === '{' || $u === ';') {
            break;
          }
        }
      }
    }

    return array_unique($classes);
  }
}
