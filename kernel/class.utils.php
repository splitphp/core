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
use stdClass;

/**
 * Class Utils
 * 
 * This class is a gateway object to extra miscellaneous functionality. There are some built-in misc functions here, like encrypt/decrypt, for example 
 * amongst others and you can register custom misc functions here too. The vendors's objects also will be loaded in this class's instance, 
 * which is available in all services. 
 *
 * @package SplitPHP
 */
final class Utils
{
  /**
   * @var array $methodsCollection
   * Stores all registered custom misc functions.
   */
  private static array $methodsCollection = [];

  /** 
   * Register the closure function received in $instructions as a custom static method of the Utils object, with the specified $methodName. 
   * 
   * @param string $methodName
   * @param callable $instructions
   * @return void 
   */
  public static function registerMethod(string $methodName, callable $instructions): void
  {
    if (is_callable($instructions))
      self::$methodsCollection[$methodName] = $instructions;
  }

  /** 
   * Calls a Utils's custom static method, previously registered with Utils::registerMethod(), then returns its result.
   * 
   * @param string $name
   * @param array $arguments = []
   * @return mixed
   */
  public static function __callstatic(string $name, array $arguments = []): mixed
  {
    try {
      if (!isset(self::$methodsCollection[$name]))
        throw new Exception('There is not a method named "' . $name . '" defined in class Utils. You can define it by calling "Utils::registerMethod()" to make it available. Check documentation for more info.');

      return call_user_func_array(self::$methodsCollection[$name], $arguments);
    } catch (Exception $ex) {
      Helpers::Log()->error('sys_error', $ex);
      die;
    }
  }

  /** 
   * Returns a line break, depending on the environment (CLI or Web).
   * 
   * @return string
   */
  public static function lineBreak(): string
  {
    return System::$bootType == 'cli' ? PHP_EOL : '<br>';
  }

  /** 
   * Outputs a given $data followed by an end-of-line.
   * 
   * @param mixed $data
   * @return void 
   */
  public static function printLn($data = ""): void
  {
    if (gettype($data) == 'array' || (gettype($data) == 'object' && $data instanceof StdClass)) {
      print_r($data);
    } else {
      echo $data;
      echo self::lineBreak();
    }
  }

  /** 
   * Encrypts the string passed in $data into a reversible hash, using the passed $key. Returns the encrypted hash.
   * 
   * @param string $data
   * @param string $key
   * @return string
   */
  public static function dataEncrypt(string $data, string $key): string
  {
    $m = 'AES-256-CBC';

    do {
      $f = openssl_random_pseudo_bytes(rand(1, 9), $sec);
    } while (!$sec);

    $iv = substr(hash('sha256', time() . $f), 0, 16);
    $dt = openssl_encrypt($data, $m, $key, 0, $iv);

    return base64_encode(serialize([$iv, $dt]));
  }

  /** 
   * Using the passed $key, decrypts the hash passed in $data into the original data, previously encrypted with Utils::dataEncrypt(). 
   * Returns the original data.
   * 
   * @param string $data
   * @param string $key
   * @return string
   */
  public static function dataDecrypt(string $data, string $key): string
  {
    $m = 'AES-256-CBC';

    $data = unserialize(base64_decode($data));
    $iv = $data[0];
    $data = $data[1];

    return openssl_decrypt($data, $m, $key, 0, $iv);
  }

  /** 
   * Returns a filtered associative array, where the keys match with the provided REGEX pattern.
   * 
   * You can also specify flags to modify the results. It uses the same flags available for PHP's 
   * preg_grep() function.
   * 
   * @param string $pattern
   * @param array $input
   * @param int $flags
   * @return array
   */
  public static function preg_grep_keys(string $pattern, array $input, $flags = 0): array
  {
    return array_intersect_key(
      $input,
      array_flip(
        preg_grep($pattern, array_keys($input), $flags)
      )
    );
  }

  /** 
   * Removes regex patterns specified in $filterRules from $data, then returns the modified $data.
   * 
   * @param array $filterRules
   * @param mixed $data
   * @return mixed
   */
  public static function filterData(array $filterRules, $data): mixed
  {
    foreach ($data as $key => $value) {
      if (gettype($value == 'array') || (gettype($value == 'object' && $value instanceof StdClass)))
        $data[$key] = self::filterData($filterRules, $value);

      // Remove any field that is not defined in the filter rules:
      if (!array_key_exists($key, $filterRules)) unset($data[$key]);

      $rule = $filterRules[$key];

      // Fix float decimal places:
      if (gettype($value) == 'double' && !is_null($rule->decimalPlaces)) {
        $data[$key] = round($value, $rule->decimalPlaces);
      }

      // Remove string content out of pattern:
      if (is_string($value) && !is_null($rule->pattern)) {
        $rest = preg_split($rule->pattern, $value);
        foreach ($rest as $strPartArray) {
          if (!empty($strPartArray)) {
            $strPart = $strPartArray[0];
            $data[$key] = str_replace($strPart, "", $value);
          }
        }
      }
    }

    return $data;
  }

  /** 
   * Checks for regex patterns specified in $filterRules in $data, if found, throws exception.
   * Returns true if the validation succeed or false in case of failure.
   * 
   * @param array $validationRules
   * @param mixed $data
   * @return boolean
   */
  public static function validateData(array $validationRules, $data): bool
  {
    // Check required fields:
    foreach ($validationRules as $field => $_rule) {
      if (!isset($_rule->dataType)) throw new Exception("Data type is required within input validation rules.");

      if ((!empty($_rule->required)) && empty($data[$field])) {
        $logObj = (object) [
          "date" => date('d/m/Y H:i:s'),
          "message" => 'Input validation failed.',
          "cause" => 'Required field empty or not found.',
          "route" => $_SERVER["REQUEST_URI"],
          "input_name" => $field
        ];
        Helpers::Log()->common('input_validation', json_encode($logObj));
        if (!empty($_rule->message)) throw new Exception($_rule->message);
        else return false;
      }
    }

    foreach ($data as $key => $value) {
      if (gettype($value) == 'array' || (gettype($value) == 'object' && $value instanceof StdClass))
        if (self::validateInputs($validationRules, $value) === false) return false;

      if (!array_key_exists($key, $validationRules)) continue;

      $rule = $validationRules[$key];

      // Check for forbidden field:
      if (is_null($rule->dataType)) {
        $logObj = (object) [
          "date" => date('d/m/Y H:i:s'),
          "message" => 'Input validation failed.',
          "cause" => 'Forbidden field found.',
          "route" => $_SERVER["REQUEST_URI"],
          "input_name" => $key,
          "input_value" => $value
        ];
        Helpers::Log()->common('input_validation', json_encode($logObj));
        if (!empty($rule->message)) throw new Exception($rule->message);
        else return false;
      }

      // Data type validation:
      if (gettype($value) != $rule->dataType) {
        $logObj = (object) [
          "date" => date('d/m/Y H:i:s'),
          "message" => 'Input validation failed.',
          "cause" => 'Invalid type.',
          "route" => $_SERVER["REQUEST_URI"],
          "input_name" => $key,
          "input_value" => $value
        ];
        Helpers::Log()->common('input_validation', json_encode($logObj));
        if (!empty($rule->message)) throw new Exception($rule->message);
        else return false;
      }

      // String length validation:
      if (is_string($value) && !empty($rule->length)) {
        if (strlen($value) != $rule->length) {
          $logObj = (object) [
            "date" => date('d/m/Y H:i:s'),
            "message" => 'Input validation failed.',
            "cause" => 'String length does not match rule.',
            "route" => $_SERVER["REQUEST_URI"],
            "input_name" => $key,
            "input_value" => $value
          ];
          Helpers::Log()->common('input_validation', json_encode($logObj));
          if (!empty($rule->message)) throw new Exception($rule->message);
          else return false;
        }
      }

      // String pattern validation:
      if (is_string($value) && !empty($rule->pattern)) {
        $rest = preg_split($rule->pattern, $value);
        if (count($rest) != 2 || !empty($rest[0]) || !empty($rest[1])) {
          $logObj = (object) [
            "date" => date('d/m/Y H:i:s'),
            "message" => 'Input validation failed.',
            "cause" => 'String does not match the required pattern.',
            "route" => $_SERVER["REQUEST_URI"],
            "input_name" => $key,
            "input_value" => $value
          ];
          Helpers::Log()->common('input_validation', json_encode($logObj));
          if (!empty($rule->message)) throw new Exception($rule->message);
          else return false;
        }
      }

      // Custom validation function:
      if (!empty($rule->custom) && $rule->custom($value) === false) {
        $logObj = (object) [
          "date" => date('d/m/Y H:i:s'),
          "message" => 'Input validation failed.',
          "cause" => 'Input did not pass custom validation method.',
          "route" => $_SERVER["REQUEST_URI"],
          "input_name" => $key,
          "input_value" => $value
        ];
        Helpers::Log()->common('input_validation', json_encode($logObj));
        if (!empty($rule->message)) throw new Exception($rule->message);
        else return false;
      }
    }

    return true;
  }

  /** 
   * Encodes the given $data into a string representing an XML of the data, and returns it.
   * 
   * @param mixed $data
   * @param string $node_block = 'nodes'
   * @param string $node_name = 'node'
   * @return string
   */
  public static function XML_encode($data, string $node_block = 'nodes', string $node_name = 'node'): string
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";

    $xml .= '<' . $node_block . '>' . "\n";
    $xml .= self::_dataToXML($data, $node_name);
    $xml .= '</' . $node_block . '>' . "\n";

    return $xml;
  }

  /** 
   * Convert the provided $content string to UTF-8 encoding, applying safety techniques.
   * 
   * @param string $content
   * @return string
   */
  public static function convertToUTF8(string $content): string
  {
    # detect original encoding
    $original_encoding = mb_detect_encoding($content, "UTF-8, ISO-8859-1, ISO-8859-15", true);
    # now convert
    if ($original_encoding != 'UTF-8') {
      $content = mb_convert_encoding($content, 'UTF-8', $original_encoding);
    }
    $bom = chr(239) . chr(187) . chr(191); # use BOM to be on safe side
    return $bom . $content;
  }

  /** 
   * Test value given in $string to check if it is a json-decodable string.
   * 
   * @param string $string
   * @return boolean
   */
  public static function isJson($string): bool
  {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

  /** 
   * Sanitizes the a given dataset, specified on $data, using htmlspecialchars() function, to avoid XSS attacks.
   * 
   * @param mixed $data
   * @return mixed 
   */
  public static function escapeHTML($data): mixed
  {
    if (gettype($data) == 'array' || (gettype($data) == 'object' && $data instanceof StdClass))
      foreach ($data as &$v)
        $v = self::escapeHTML($v);
    else if (gettype($data) == 'string')
      $data = htmlspecialchars($data);

    return $data;
  }

  /**
   * Cleans a given string by removing special characters and replacing spaces with underscores.
   * 
   * This function processes the input string to remove any special characters (e.g., punctuation)
   * and converts spaces to underscores for a cleaner format.
   * 
   * @param   String  $string The input string to be processed.
   * @return  String  The cleaned string with special characters removed and spaces replaced by underscores.
   */
  public static function stringToSlug(String $string): string
  {
    $string = preg_replace('/[^\w\s]/u', '', $string);
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = str_replace(' ', '-', $string);
    $string = strtolower($string);
    return $string;
  }

  /**
   * Simple boolean wrapper around preg_match().
   *
   * @param string $pattern  A valid PCRE pattern (including delimiters and modifiers)
   * @param string $subject  The string you want to test against
   * @return bool            TRUE if it matches, FALSE otherwise (or on error)
   */
  public static function regexTest(string $pattern, string $subject): bool
  {
    // preg_match() returns 1 if matched, 0 if no match, FALSE on error.
    return (bool) preg_match($pattern, $subject);
  }

  /**
   * Converts a given string to PascalCase format.
   *
   * This function takes a string, splits it into words, capitalizes the first letter of each word,
   * and concatenates them without spaces or underscores.
   *
   * @param string $string The input string to be converted.
   * @return string The converted string in PascalCase format.
   */
  public static function stringToPascalCase(string $string): string
  {
    // Split the string by spaces or underscores, capitalize each word, and join them back together.
    $words = preg_split('/[^a-zA-Z0-9]+/u', $string);
    $pascalCaseString = '';
    foreach ($words as $word) {
      $pascalCaseString .= ucfirst(strtolower($word));
    }
    return $pascalCaseString;
  }

  /**
   * Pads a given text to a specified length with spaces.
   *
   * This function truncates the text if it exceeds the specified length and pads it with spaces
   * if it is shorter than the specified length.
   *
   * @param string|null $text The text to be padded.
   * @param int $length The desired length of the output string.
   * @return string The padded or truncated text.
   */
  public static function pad($text, $length): string
  {
    $text = $text !== null ? (string) $text : '';
    return str_pad(substr($text, 0, $length), $length);
  }

  /**
   * Builds a separator line for a table based on the provided column widths.
   *
   * This function generates a string that represents a separator line for a table,
   * where each column's width is specified in the $columnWidths array.
   *
   * @param array<int, int> $columnWidths An array of integers representing the widths of each column.
   * @return string The generated separator line.
   */
  public static function buildSeparator($columnWidths): string
  {
    $line = '+';
    foreach ($columnWidths as $width) {
      $line .= str_repeat('-', $width + 2) . '+';
    }
    return $line;
  }

  /** 
   * Encodes the given $data into a string representing an XML of the data, and returns it.
   * 
   * @param mixed $data
   * @param string $node_name
   * @return string
   */
  private static function _dataToXML($data, $node_name): string
  {
    $xml = '';

    if (is_array($data) || is_object($data)) {
      foreach ($data as $key => $value) {
        if (is_numeric($key)) {
          $key = $node_name;
        }

        $xml .= '<' . $key . '>' . self::lineBreak() . self::_dataToXML($value, $node_name) . '</' . $key . '>' . self::lineBreak();
      }
    } else {
      $xml = htmlspecialchars($data, ENT_QUOTES) . self::lineBreak();
    }

    return $xml;
  }
}
