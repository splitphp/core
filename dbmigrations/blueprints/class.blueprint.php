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

namespace SplitPHP\DbMigrations;

use SplitPHP\Database\DbVocab;

abstract class Blueprint
{
  private const INTERNAL_PROPS = [
    'tableRef'
  ];

  protected $tableRef;
  protected $name;
  protected $dropFlag = false;

  public function Column(
    string $name,
    string $type = 'int',
    ?int $length = null
  ) {
    return $this->tableRef->Column(
      name: $name,
      type: $type,
      length: $length
    );
  }

  public function Index(
    string $name,
    string $type = 'INDEX'
  ) {
    return $this->tableRef->Index(
      name: $name,
      type: $type
    );
  }

  public function Foreign(array|string $columns)
  {
    return $this->tableRef->Foreign($columns);
  }

  public final function drop()
  {
    $this->dropFlag = true;
    return $this;
  }

  public final function isToDrop(): bool
  {
    return $this->dropFlag;
  }

  public final function getName(): string
  {
    return $this->name;
  }

  public final function getTableRef(): ?TableBlueprint
  {
    return $this->tableRef;
  }

  // Shortcut column definition functions:
  public final function id($columnName)
  {
    return $this->Column($columnName)
      ->unsigned()
      ->primary()
      ->autoIncrement();
  }

  public final function string($columnName, $length = 255)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_STRING,
      length: $length
    );
  }

  public final function text($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TEXT
    );
  }

  public final function int($columnName)
  {
    return $this->Column($columnName);
  }

  public final function bigInt($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_BIGINT
    );
  }

  public final function decimal($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DECIMAL
    );
  }

  public final function float($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_FLOAT
    );
  }

  public final function date($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DATE
    );
  }

  public final function datetime($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_DATETIME
    );
  }

  public final function time($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TIME
    );
  }

  public final function timestamp($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_TIMESTAMP
    );
  }

  public final function boolean($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_BOOL
    );
  }

  public final function blob($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_BLOB
    );
  }

  public final function json($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_JSON
    );
  }

  public final function uuid($columnName)
  {
    return $this->Column(
      name: $columnName,
      type: DbVocab::DATATYPE_UUID
    );
  }
}
