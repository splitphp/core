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

namespace SplitPHP\Helpers;

use SplitPHP\AppLoader;
use SplitPHP\ModLoader;
use SplitPHP\EventDispatcher;
use SplitPHP\EventListener;
use SplitPHP\ObjLoader;
use SplitPHP\Database\Database;
use SplitPHP\Database\DbCredentials;
use Exception;
use ReflectionProperty;
use Throwable;

/**
 * Class Spawn
 *
 * Forks the current OS process and executes the supplied callable inside the
 * child with a fully-isolated, freshly-bootstrapped SplitPHP environment.
 *
 * Every stateful singleton managed by the framework (ObjLoader cache, database
 * connections, AppLoader / ModLoader maps, EventListener registry, and
 * EventDispatcher's event-discovery cache) is reset and re-initialised from
 * scratch in the child so that it operates completely independently from the
 * parent process.
 *
 * Usage – synchronous (parent blocks until the child finishes):
 *
 *   Helpers::Spawn()->run(function () {
 *       // full framework available: services, DB, helpers, events…
 *   });
 *
 * Usage – asynchronous with wait (parent blocks at wait()):
 *
 *   $spawn = Helpers::Spawn()->async();
 *   $spawn->run(function () { ... });
 *   $spawn->run(function () { ... });
 *   $spawn->wait(); // blocks until all background children finish
 *
 * Usage – detached (fire-and-forget, no zombie processes):
 *
 *   $spawn = Helpers::Spawn()->detach();
 *   $spawn->run(function () { ... }); // parent never blocks; no zombies
 *   $spawn->run(function () { ... });
 *
 * @package SplitPHP\Helpers
 */
class Spawn
{
  /**
   * @var bool $async
   * When true, run() returns immediately and the child runs in the background.
   */
  private bool $async = false;

  /**
   * @var bool $detached
   * When true, SIGCHLD is ignored so the kernel auto-reaps children
   * and the parent never needs to call wait().
   */
  private bool $detached = false;

  /**
   * @var int[] $pendingChildren
   * PIDs of async child processes that have not yet been waited on.
   */
  private array $pendingChildren = [];

  /**
   * Configure whether spawned processes should run asynchronously.
   *
   * When called with true (the default), subsequent run() calls return
   * immediately and the children run in the background. Calling wait()
   * afterwards will block until every background child finishes.
   *
   * @param bool $async
   * @return static
   */
  public function async(bool $async = true): static
  {
    $this->async = $async;
    return $this;
  }

  /**
   * Enable detached (fire-and-forget) mode.
   *
   * In this mode the parent installs SIGCHLD => SIG_IGN before each fork so
   * the kernel automatically reaps every child as soon as it exits — no zombie
   * processes accumulate and the parent never needs to call wait().
   *
   * Note: detached mode and wait() are mutually exclusive. Calling wait() on
   * a detached Spawn instance has no effect because no PIDs are tracked.
   *
   * @return static
   */
  public function detach(): static
  {
    $this->detached = true;
    $this->async    = true; // detached implies async
    return $this;
  }

  /**
   * Forks the current process and executes $fn inside the child with a
   * completely fresh SplitPHP environment.
   *
   * In synchronous mode (default) the parent blocks until the child exits.
   * In async mode the parent returns immediately; call wait() later if you
   * need to ensure all children have finished.
   * In detached mode the parent returns immediately and the kernel reaps the
   * child automatically — no zombies, no need for wait().
   *
   * @param callable $fn  Callable to run inside the isolated child process.
   * @return int          PID of the spawned child process.
   * @throws Exception    If the pcntl extension is missing or the fork fails.
   */
  public function run(callable $fn): int
  {
    if (!function_exists('pcntl_fork')) {
      throw new Exception(
        "Spawn helper requires the 'pcntl' PHP extension, which is not available in the current environment."
      );
    }

    // In detached mode, tell the kernel to auto-reap children so no zombie
    // entries accumulate in the process table.
    if ($this->detached) {
      pcntl_signal(SIGCHLD, SIG_IGN);
    }

    $pid = pcntl_fork();

    if ($pid === -1) {
      throw new Exception("Failed to fork child process.");
    }

    // ── CHILD PROCESS ──────────────────────────────────────────────────────
    if ($pid === 0) {
      try {
        $this->bootstrapChild();
        $fn();
      } catch (Throwable $e) {
        error_log(
          "[Spawn] Child process error: " . $e->getMessage() .
            " in " . $e->getFile() . ":" . $e->getLine()
        );
        exit(1);
      }
      exit(0);
    }

    // ── PARENT PROCESS ─────────────────────────────────────────────────────
    if ($this->detached) {
      // Kernel reaps the child automatically; no PID tracking needed.
    } elseif ($this->async) {
      $this->pendingChildren[] = $pid;
    } else {
      pcntl_waitpid($pid, $status);
    }

    return $pid;
  }

  /**
   * Blocks until every background (async) child process spawned by this
   * instance has finished. Has no effect if no async children are pending.
   *
   * @return void
   */
  public function wait(): void
  {
    foreach ($this->pendingChildren as $pid) {
      pcntl_waitpid($pid, $status);
    }
    $this->pendingChildren = [];
  }

  // ── Private ──────────────────────────────────────────────────────────────

  /**
   * Resets all framework singletons and static caches inherited from the
   * parent process, then re-initialises a clean SplitPHP environment.
   *
   * Steps performed:
   *   1. Clear the ObjLoader singleton registry.
   *   2. Close all inherited database connections and open fresh ones.
   *   3. Clear the EventListener registry so listeners are re-registered.
   *   4. Reset AppLoader and ModLoader maps.
   *   5. Reset the EventDispatcher's discovered-events cache.
   *   6. Re-run AppLoader::init(), ModLoader::init(), EventDispatcher::init().
   *
   * @return void
   */
  private function bootstrapChild(): void
  {
    // 1. ObjLoader singleton cache — every helper / service accessed inside the
    //    callable will receive a brand-new instance scoped to this process.
    $this->resetStatic(ObjLoader::class, 'collection', []);

    // 2. Database connections — the parent's socket file-descriptors are
    //    inherited by the child; close them here and open fresh ones so the two
    //    processes never share a socket.
    $this->resetDatabaseConnections();

    // 3. EventListener registry — clear so AppLoader / ModLoader can
    //    re-register listeners for this process without duplicates.
    $this->resetStatic(EventListener::class, 'listeners', []);

    // 4. AppLoader and ModLoader maps — reset so their init() methods
    //    re-discover routes, services, and event listeners from scratch.
    $this->resetStatic(AppLoader::class, 'map', null);
    $this->resetStatic(ModLoader::class, 'maps', []);

    // 5. EventDispatcher's discovered-event cache.
    $this->resetStatic(EventDispatcher::class, 'events', null);

    // 6. Re-initialise the framework. All constants (ROOT_PATH, CORE_PATH,
    //    config constants, etc.) are already defined by the parent before the
    //    fork so no further bootstrapping is necessary.
    AppLoader::init();
    ModLoader::init();
    EventDispatcher::init();
  }

  /**
   * Closes every database connection inherited from the parent process
   * (child-side only; the parent's socket is unaffected) and then opens
   * equivalent fresh connections using the already-defined configuration
   * constants.
   *
   * @return void
   */
  private function resetDatabaseConnections(): void
  {
    if (!defined('DB_CONNECT') || DB_CONNECT !== 'on') {
      return;
    }

    // Retrieve the names of all connections the parent had open.
    $ref = new ReflectionProperty(Database::class, 'connections');
    $ref->setAccessible(true);
    $inheritedNames = array_keys((array) $ref->getValue(null));

    // Disconnect and remove every inherited connection (child side only).
    foreach ($inheritedNames as $name) {
      Database::removeCnn($name);
    }

    // Open fresh connections using constants already set before the fork.
    Database::getCnn('main', new DbCredentials(
      host: DBHOST,
      port: DBPORT,
      user: DBUSER,
      pass: DBPASS
    ));

    Database::getCnn('readonly', new DbCredentials(
      host: DBHOST,
      port: DBPORT,
      user: DBUSER_READONLY,
      pass: DBPASS_READONLY
    ));
  }

  /**
   * Uses reflection to overwrite a private or protected static property on
   * a class, bypassing its visibility modifier.
   *
   * @param string $class    Fully-qualified class name.
   * @param string $property Property name.
   * @param mixed  $value    New value to assign.
   * @return void
   */
  private function resetStatic(string $class, string $property, mixed $value): void
  {
    $ref = new ReflectionProperty($class, $property);
    $ref->setAccessible(true);
    $ref->setValue(null, $value);
  }
}
