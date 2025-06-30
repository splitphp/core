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

use SplitPHP\Database\DbConnections;
use SplitPHP\Exceptions\DatabaseException;
use SplitPHP\Exceptions\EventException;
use Exception;
use stdClass;
use Throwable;

/**
 * Class Utils
 * 
 * This class is a gateway object to extra miscellaneous functionality. There are some built-in misc functions here, like encrypt/decrypt, for example 
 * amongst others and you can register custom misc functions here too. The vendors's objects also will be loaded in this class's instance, 
 * which is available in all services. 
 *
 * @package SplitPHP
 */
class Utils
{
  /**
   * @var array $summary
   * It is a collection to loaded vendor objects.
   */
  private $summary;

  /**
   * @var array $methodsCollection
   * Stores all registered custom misc functions.
   */
  private static $methodsCollection = [];

  /** 
   * This is the constructor of Utils class. It parses all vendors set in config.ini then register them in the summary. 
   * If the utils autoload, set in config.ini file, is on, automatically loads all vendors registered this way.
   * 
   * @return Utils 
   */
  public final function __construct() {}

  /** 
   * Returns a string representation of this class for printing purposes.
   * 
   * @return string 
   */
  public function __toString()
  {
    return "class:" . __CLASS__ . "()";
  }

  /** 
   * Outputs a given $data followed by an end-of-line.
   * 
   * @param mixed $data
   * @return void 
   */
  public static function printLn($data = "")
  {
    if (gettype($data) == 'array' || (gettype($data) == 'object' && $data instanceof StdClass)) {
      print_r($data);
    } else {
      echo $data;
      echo PHP_EOL;
    }
  }

  /** 
   * Register the closure function received in $instructions as a custom static method of the Utils object, with the specified $methodName. 
   * 
   * @param string $methodName
   * @param callable $instructions
   * @return void 
   */
  public static function registerMethod(string $methodName, callable $instructions)
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
  public static function __callstatic(string $name, array $arguments = [])
  {
    try {
      if (!isset(self::$methodsCollection[$name]))
        throw new Exception('There is not a method named "' . $name . '" defined in class Utils. You can define it by calling "Utils::registerMethod()" to make it available. Check documentation for more info.');

      return call_user_func_array(self::$methodsCollection[$name], $arguments);
    } catch (Exception $ex) {
      Helpers::Log()->add('sys_error', $ex->getMessage());
      die;
    }
  }

  /** 
   * Encrypts the string passed in $data into a reversible hash, using the passed $key. Returns the encrypted hash.
   * 
   * @param string $data
   * @param string $key
   * @return string
   */
  public static function dataEncrypt(string $data, string $key)
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
  public static function dataDecrypt(string $data, string $key)
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
  public static function preg_grep_keys(string $pattern, array $input, $flags = 0)
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
  public static function filterInputs(array $filterRules, $data)
  {
    foreach ($data as $key => $value) {
      if (gettype($value == 'array') || (gettype($value == 'object' && $value instanceof StdClass)))
        $data[$key] = self::filterInputs($filterRules, $value);

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
  public static function validateData(array $validationRules, $data)
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
        Helpers::Log()->add('input_validation', json_encode($logObj));
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
        Helpers::Log()->add('input_validation', json_encode($logObj));
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
        Helpers::Log()->add('input_validation', json_encode($logObj));
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
          Helpers::Log()->add('input_validation', json_encode($logObj));
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
          Helpers::Log()->add('input_validation', json_encode($logObj));
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
        Helpers::Log()->add('input_validation', json_encode($logObj));
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
  public static function XML_encode($data, string $node_block = 'nodes', string $node_name = 'node')
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
  public static function convertToUTF8(string $content)
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
  public static function isJson($string)
  {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

  /** 
   * Navigate the user agent to the specified $url.
   * 
   * @param string $url
   * @return void 
   */
  public static function navigateToUrl(string $url)
  {
    header('Location: ' . $url);

    die;
  }

  /** 
   * Sanitizes the a given dataset, specified on $payload, using htmlspecialchars() function, to avoid XSS attacks.
   * 
   * @param mixed $payload
   * @return mixed 
   */
  public static function escapeOutput($payload)
  {
    foreach ($payload as &$value) {
      if (gettype($value) == 'array' || (gettype($value) == 'object' && $value instanceof StdClass)) {
        $value = self::escapeOutput($value);
        continue;
      }

      if (!empty($value)) $value = htmlspecialchars($value);
    }

    return $payload;
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
  public static function stringToSlug(String $string)
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

  public static function pad($text, $length)
  {
    $text = $text !== null ? (string) $text : '';
    return str_pad(substr($text, 0, $length), $length);
  }

  public static function buildSeparator($columnWidths)
  {
    $line = '+';
    foreach ($columnWidths as $width) {
      $line .= str_repeat('-', $width + 2) . '+';
    }
    return $line;
  }

  public static function handleAppException(Throwable $exc)
  {
    if (DB_CONNECT == "on" && DB_TRANSACTIONAL == "on" && DbConnections::check('main')) {
      DbConnections::retrieve('main')->rollbackTransaction();
    }

    if (APPLICATION_LOG == "on") {
      // For Database Exceptions
      if ($exc instanceof DatabaseException) {
        echo "\033[31mERROR[Database]: " . $exc->getMessage() . ". In file '" . $exc->getFile() . "', line " . $exc->getLine() . ".\033[0m";
        echo PHP_EOL;
        Helpers::Log()->error('db_error', $exc, [
          'sqlState' => $exc->getSqlState(),
          'sqlCommand' => $exc->getSqlCmd()
        ]);
      }
      // For Event Exceptions
      elseif ($exc instanceof EventException) {
        echo "\033[31mERROR[Event:{$exc->getEvent()->getName()}]: " . $exc->getMessage() . ". In file '" . $exc->getFile() . "', line " . $exc->getLine() . ".\033[0m";
        echo PHP_EOL;
        Helpers::Log()->error('application_error', $exc);
      }
      // For other Exceptions
      else {
        echo "\033[31mERROR[Application]: " . $exc->getMessage() . ". In file '" . $exc->getFile() . "', line " . $exc->getLine() . ".\033[0m";
        echo PHP_EOL;
        Helpers::Log()->error('application_error', $exc);
      }
    }

    die;
  }

  /** 
   * Encodes the given $data into a string representing an XML of the data, and returns it.
   * 
   * @param mixed $data
   * @param string $node_name
   * @return string
   */
  private static function _dataToXML($data, $node_name)
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
