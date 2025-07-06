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

namespace SplitPHP\Database;


final class SqlExpression
{
  private $expression;

  public function __construct($expression)
  {
    $this->expression = str_replace('rawsql:', '', $expression);
  }

  public function __toString()
  {
    return $this->expression;
  }

  public function get()
  {
    return $this->expression;
  }

  public function equals($other)
  {
    return $other instanceof self
      && $this->expression === $other->get();
  }
}

final class DbVocab
{
  /////////////
  // Datatypes:
  /////////////
  public const DATATYPE_STRING = 'string'; // variable-length text
  public const DATATYPE_TEXT = 'text'; // large text
  public const DATATYPE_INT = 'int'; // signed 32-bit int
  public const DATATYPE_BIGINT = 'bigInt'; // signed 64-bit int
  public const DATATYPE_DECIMAL = 'decimal'; // fixed precision
  public const DATATYPE_FLOAT = 'float'; // floating-point
  public const DATATYPE_DATE = 'date'; // YYYY-MM-DD
  public const DATATYPE_DATETIME = 'datetime'; // YYYY-MM-DD HH:MM:SS
  public const DATATYPE_TIME = 'time'; // HH:MM:SS
  public const DATATYPE_TIMESTAMP = 'timestamp'; // dateTime + timezone support
  public const DATATYPE_BOOL = 'boolean'; // tiny true/false
  public const DATATYPE_BLOB = 'blob'; // blob
  public const DATATYPE_JSON = 'json'; // JSON document
  public const DATATYPE_UUID = 'uuid'; // 128-bit identifier

  public const DATATYPE_GROUPS = [
    'text' => [
      self::DATATYPE_STRING,
      self::DATATYPE_TEXT,
    ],
    'numerical' => [
      self::DATATYPE_INT,
      self::DATATYPE_BIGINT,
      self::DATATYPE_DECIMAL,
      self::DATATYPE_FLOAT,
    ],
    'dateAndTime' => [
      self::DATATYPE_DATE,
      self::DATATYPE_DATETIME,
      self::DATATYPE_TIME,
      self::DATATYPE_TIMESTAMP
    ],
    'other' => [
      self::DATATYPE_BOOL,
      self::DATATYPE_BLOB,
      self::DATATYPE_JSON,
      self::DATATYPE_UUID,
    ]
  ];

  public const DATATYPES_ALL = [
    ...self::DATATYPE_GROUPS['text'],
    ...self::DATATYPE_GROUPS['numerical'],
    ...self::DATATYPE_GROUPS['dateAndTime'],
    ...self::DATATYPE_GROUPS['other'],
  ];

  //////////////
  // FK Actions:
  //////////////
  public const FKACTION_CASCADE = 'cascade';
  public const FKACTION_SETNULL = 'setNull';
  public const FKACTION_NOACTION = 'noAction';
  public const FKACTION_RESTRICT = 'restrict';
  public const FKACTIONS = [
    self::FKACTION_CASCADE,
    self::FKACTION_SETNULL,
    self::FKACTION_NOACTION,
    self::FKACTION_RESTRICT,
  ];

  //////////////
  // Index Types:
  //////////////
  public const IDX_PRIMARY = 'primary';
  public const IDX_INDEX = 'index';
  public const IDX_UNIQUE = 'unique';
  public const IDX_FULLTEXT = 'fullText';
  public const IDX_SPATIAL = 'spatial';
  public const INDEX_TYPES = [
    self::IDX_PRIMARY,
    self::IDX_INDEX,
    self::IDX_UNIQUE,
    self::IDX_FULLTEXT,
    self::IDX_SPATIAL,
  ];

  ////////////////
  // MISCELANEOUS:
  ////////////////
  public static function SQL_CURTIMESTAMP()
  {
    return new SqlExpression('CURRENT_TIMESTAMP');
  }
}
