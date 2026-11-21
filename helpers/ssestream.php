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

use Exception;

/**
 * Class SseStream
 *
 * A framework-level helper for driving a Server-Sent Events (SSE) connection.
 * It is completely agnostic – it knows nothing about authentication, business
 * entities, or application logic. Any service or route can use it to push
 * typed JSON events to a browser client over a single, long-lived HTTP
 * connection.
 *
 * ── Basic usage ──────────────────────────────────────────────────────────────
 *
 *   // 1. Open the stream (sends headers, releases the session lock).
 *   $sse = Helpers::SseStream()->open();
 *
 *   // 2. Push an event whenever something interesting happens.
 *   $sse->emit('orderUpdated', ['id' => 42]);
 *
 *   // 3. Run the built-in polling loop (blocks until TTL expires or client
 *   //    disconnects; invokes $checkFn at each tick).
 *   $sse->loop(function (SseStream $sse): bool {
 *       if ($something_changed) {
 *           $sse->emit('myEvent', ['foo' => 'bar']);
 *       }
 *       return true; // returning false terminates the loop early
 *   });
 *
 * ── Fluent configuration (before open()) ─────────────────────────────────────
 *
 *   Helpers::SseStream()
 *       ->ttl(120)               // max seconds before graceful reconnect
 *       ->tickInterval(500)      // ms between loop ticks (default 300)
 *       ->reconnectDelay(3000)   // ms hint sent to browser for reconnection
 *       ->open()
 *       ->loop(fn($sse) => ...);
 *
 * ── Manual emit (no built-in loop) ───────────────────────────────────────────
 *
 *   $sse = Helpers::SseStream()->open();
 *   while (true) {
 *       // your own logic
 *       $sse->emit('ping', ['ts' => time()]);
 *       if ($done) break;
 *   }
 *   $sse->close();
 *
 * @package SplitPHP\Helpers
 */
class SseStream
{
  // ── Configuration defaults ──────────────────────────────────────────────────

  /**
   * Maximum number of seconds a single SSE connection is kept alive before
   * the helper sends a "reconnect" event and terminates. The browser's native
   * EventSource will then reconnect automatically.
   *
   * Keep this value safely below the Nginx/proxy fastcgi_read_timeout so the
   * connection is always closed gracefully from the server side.
   *
   * @var int
   */
  private int $ttl = 55;

  /**
   * How long (in microseconds) the polling loop sleeps between each tick.
   * 300 000 µs = 300 ms — responsive enough for real-time UX while generating
   * far less CPU/IO pressure than the old 100 ms long-polling loops.
   *
   * @var int
   */
  private int $tickIntervalUs = 300000;

  /**
   * The `retry:` field sent in the initial comment, telling the browser how
   * many milliseconds to wait before it attempts to reconnect after the
   * connection drops unexpectedly. Value in milliseconds.
   *
   * @var int
   */
  private int $reconnectDelayMs = 2000;

  // ── Internal state ──────────────────────────────────────────────────────────

  /**
   * Unix timestamp at which open() was called. Used to enforce $ttl.
   * @var int|null
   */
  private ?int $startedAt = null;

  /**
   * Whether open() has already been called on this instance.
   * @var bool
   */
  private bool $opened = false;

  // ── Fluent configuration ────────────────────────────────────────────────────

  /**
   * Sets the maximum lifetime (in seconds) of the SSE connection before a
   * graceful "reconnect" event is emitted and the stream is closed.
   *
   * @param  int    $seconds
   * @return static
   */
  public function ttl(int $seconds): static
  {
    $this->ttl = $seconds;
    return $this;
  }

  /**
   * Sets how long (in milliseconds) the built-in loop sleeps between each
   * tick. Lower values mean more responsive event delivery but higher CPU
   * pressure; higher values are more efficient for infrequent events.
   *
   * @param  int    $ms  Interval in milliseconds (e.g. 300 for 300 ms).
   * @return static
   */
  public function tickInterval(int $ms): static
  {
    $this->tickIntervalUs = $ms * 1000;
    return $this;
  }

  /**
   * Sets the `retry:` hint (in milliseconds) sent to the browser, instructing
   * EventSource how long to wait before attempting a reconnection after an
   * unexpected disconnect.
   *
   * @param  int    $ms  Delay in milliseconds (e.g. 3000 for 3 s).
   * @return static
   */
  public function reconnectDelay(int $ms): static
  {
    $this->reconnectDelayMs = $ms;
    return $this;
  }

  // ── Lifecycle ───────────────────────────────────────────────────────────────

  /**
   * Sends the SSE HTTP headers and immediately releases the PHP session lock.
   *
   * Releasing the session lock (`session_write_close()`) is the single most
   * important step to prevent request serialization: without it, every
   * concurrent request from the same browser tab/session is queued behind
   * this long-lived connection.
   *
   * Must be called before any emit() or loop() call.
   *
   * @return static
   * @throws Exception if open() has already been called on this instance.
   */
  public function open(): static
  {
    if ($this->opened) {
      throw new Exception('SseStream::open() has already been called on this instance. Create a new instance for each request.');
    }

    // ── HTTP headers ──────────────────────────────────────────────────────
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    // Tell reverse proxies (Nginx, CDNs, load-balancers) not to buffer this
    // response. The `X-Accel-Buffering: no` is specifically understood by
    // Nginx's proxy_pass / fastcgi_pass modules.
    header('X-Accel-Buffering: no');

    // ── Session lock ──────────────────────────────────────────────────────
    // Release the session lock so concurrent requests from the same client
    // are not serialized behind this long-running connection.
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    // ── Output buffering ──────────────────────────────────────────────────
    // Disable any remaining output-buffering layers so event frames reach the
    // client immediately without being held in PHP's internal buffer.
    while (ob_get_level() > 0) {
      ob_end_flush();
    }

    $this->startedAt = time();
    $this->opened    = true;

    // Send the retry hint and flush headers to the client immediately.
    echo "retry: {$this->reconnectDelayMs}\n\n";
    flush();

    return $this;
  }

  /**
   * Emits a single SSE event frame to the connected client.
   *
   * The payload is JSON-encoded automatically. The event `type` field is
   * included inside the data object so EventSource consumers can use a single
   * `onmessage` handler and branch on `event.type` — no need to register
   * named `addEventListener` listeners on the client side.
   *
   * Format sent over the wire:
   *   data: {"type":"<eventType>", ...payload}\n\n
   *
   * @param  string $eventType  A short camelCase identifier (e.g. "orderUpdated").
   * @param  array  $payload    Arbitrary data to merge alongside "type".
   * @return static
   * @throws Exception if open() has not been called yet.
   */
  public function emit(string $eventType, array $payload = []): static
  {
    $this->assertOpened();

    $frame = array_merge(['type' => $eventType], $payload);
    echo 'data: ' . json_encode($frame) . "\n\n";
    flush();

    return $this;
  }

  /**
   * Closes the stream gracefully by emitting a "reconnect" event — the
   * conventional signal that tells the client the server is intentionally
   * ending the connection and the browser should reconnect.
   *
   * After close() the script should exit (or simply return from the route
   * handler). No more emit() calls should be made after this.
   *
   * @return void
   */
  public function close(): void
  {
    if (!$this->opened) return;

    $this->emit('reconnect');
    // The PHP process exits naturally when the route handler returns; there
    // is nothing more to clean up on the server side.
  }

  // ── Built-in polling loop ───────────────────────────────────────────────────

  /**
   * Runs the SSE main loop, invoking `$tickFn` on every tick until one of the
   * following conditions is met:
   *
   *   1. The connection lifetime exceeds `$ttl` → emits "reconnect" and stops.
   *   2. `connection_aborted()` returns true (client closed the tab/browser).
   *   3. `$tickFn` returns `false` (caller signals an intentional stop).
   *
   * The callable receives this SseStream instance as its first argument so it
   * can call emit() directly inside the tick without needing a reference to
   * the stream from the outer scope.
   *
   *   Example:
   *     $sse->loop(function (SseStream $sse) use ($stash, &$seen): bool {
   *         $current = (int) $stash->get('myVersion', 0);
   *         if ($current > $seen) {
   *             $seen = $current;
   *             $sse->emit('dataChanged', ['version' => $current]);
   *         }
   *         return true; // keep looping
   *     });
   *
   * @param  callable $tickFn  Invoked once per tick. Receives ($this). Must
   *                           return bool — false terminates the loop early.
   * @return void
   * @throws Exception if open() has not been called yet.
   */
  public function tick(callable $tickFn): void
  {
    $this->assertOpened();

    while (true) {
      // ── Stop conditions ──────────────────────────────────────────────────
      if (connection_aborted()) break;

      if ((time() - $this->startedAt) >= $this->ttl) {
        $this->close();
        return;
      }

      // ── Tick ─────────────────────────────────────────────────────────────
      $continue = $tickFn($this);

      if ($continue === false) {
        $this->close();
        return;
      }

      // ── Sleep ─────────────────────────────────────────────────────────────
      usleep($this->tickIntervalUs);
    }

    // Client disconnected — no graceful close needed (the socket is gone).
  }

  // ── Internals ───────────────────────────────────────────────────────────────

  /**
   * Asserts that open() has been called before any output method is invoked.
   *
   * @throws Exception
   */
  private function assertOpened(): void
  {
    if (!$this->opened) {
      throw new Exception('SseStream: you must call open() before emitting events or starting the loop.');
    }
  }
}
