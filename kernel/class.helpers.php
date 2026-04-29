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

  /**
   * Returns an instance of the Stash helper class.
   * This class is used for managing a simple key-value store in a JSON file.
   * @return Helpers\Stash
   */
  public static function Stash(): Helpers\Stash
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/stash.php");
  }

  /**
   * Returns an instance of the Spawn helper class.
   * This class forks the current OS process and runs the supplied callable
   * inside the child with a fully-isolated, freshly-bootstrapped SplitPHP
   * environment (loaders, event system, database connections, etc.).
   * @return Helpers\Spawn
   */
  public static function Spawn(): Helpers\Spawn
  {
    return ObjLoader::load(ROOT_PATH . "/core/helpers/spawn.php");
  }

  /**
   * Returns a brand-new SseStream instance ready to be configured and opened.
   *
   * SseStream is intentionally NOT cached as a singleton: every HTTP request
   * that opens an SSE connection requires its own, independent instance with
   * its own lifecycle (headers sent, session lock released, etc.). Returning a
   * shared instance after the first call would produce a stale, already-opened
   * object that can no longer emit events.
   *
   * Usage:
   *   $sse = Helpers::SseStream()         // fresh instance
   *       ->ttl(60)                        // optional: override defaults
   *       ->tickInterval(300)             // optional
   *       ->open();                        // sends headers, releases session
   *
   *   $sse->loop(function (SseStream $sse) use (&$seen, $stash): bool {
   *       $v = (int) $stash->get('myKey', 0);
   *       if ($v > $seen) { $seen = $v; $sse->emit('changed'); }
   *       return true;
   *   });
   *
   * @return Helpers\SseStream
   */
  public static function SSE(): Helpers\SseStream
  {
    include_once ROOT_PATH . "/core/helpers/ssestream.php";
    return new Helpers\SseStream();
  }
}
