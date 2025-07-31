<?php

namespace SplitPHP\Helpers;

/**
 * Stash class for managing a simple key-value store in a JSON file.
 * This class allows you to set, get, delete, and retrieve all key-value pairs.
 * It initializes the stash file if it does not exist and provides methods to manipulate the data.
 * The stash file is stored in the cache directory of the application.
 * @package SplitPHP\Helpers
 */
class Stash{
  /**
   * Default Path to the stash file.
   */
  private const STASH_FILE = ROOT_PATH . '/cache/stash.json';

  /**
   * Path to the stash file, can be set to a custom path.
   * @var string|null
   */
  private ?string $stashFilePath = null;

  /**
   * Constructor initializes the stash file.
   * If the stash file does not exist, it creates an empty JSON file.
   */
  public function __construct()
  {
    $this->initStash();
  }

  /**
   * Sets a custom path for the stash file.
   * The path should be relative to the cache directory.
   * @param string $path
   */
  public function setStashFilePath(string $path): void
  {
    $path = str_replace(ROOT_PATH.'/cache', '', $path);
    $path = ROOT_PATH . '/cache/' . ltrim($path, '/');

    $this->stashFilePath = $path;

    $this->initStash();
  }

  /**
   * Gets the value associated with a key from the stash.
   * If the key does not exist, it returns the provided default value.
   * @param string $key
   * @param mixed $default
   * @return mixed
   */
  public function get($key, $default = null)
  {
    $stash = $this->getAll();
    return $stash[$key] ?? $default;
  }

  /**
   * Sets a key-value pair in the stash.
   * If the key already exists, it updates the value.
   * @param string $key
   * @param mixed $value
   */
  public function set($key, $value): void
  {
    $stash = $this->getAll();
    $stash[$key] = $value;
    $this->save($stash);
  }

  /**
   * Deletes a key-value pair from the stash.
   * If the key does not exist, it does nothing.
   * @param string $key
   */
  public function delete($key): void
  {
    $stash = $this->getAll();
    unset($stash[$key]);
    $this->save($stash);
  }

  /**
   * Retrieves all key-value pairs from the stash.
   * Returns an associative array of all stored values.
   * @return array
   */
  public function getAll(): array
  {
    return json_decode(file_get_contents($this->stashFilePath ?? self::STASH_FILE), true) ?? [];
  }

  /**
   * Saves the current stash to the stash file.
   * @param array $stash
   */
  private function save(array $stash): void
  {
    file_put_contents($this->stashFilePath ?? self::STASH_FILE, json_encode($stash));
  }

  /**
   * Initializes the stash file if it does not exist.
   * Creates an empty JSON file to ensure the stash can be used.
   */
  private function initStash(): void
  {
    if (!file_exists($this->stashFilePath ?? self::STASH_FILE)) {
      file_put_contents($this->stashFilePath ?? self::STASH_FILE, json_encode([]));
    }
  }
}