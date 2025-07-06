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

namespace SplitPHP\DbManager;

use Exception;
use SplitPHP\Database\DbVocab;
use SplitPHP\Database\Sql;

final class IndexBlueprint extends Blueprint
{
  private $type;
  private $columns;

  public final function __construct(TableBlueprint $tableRef, string $name, string $type)
  {
    if (!in_array($type, DbVocab::INDEX_TYPES))
      throw new Exception("Invalid type '{$type}' for index named: '{$name}'. Available types: " . implode(', ', DbVocab::INDEX_TYPES));

    $tbname = $tableRef->getName();
    foreach ($tableRef->getIndexes() as $idx) {
      // Check if this index is already defined for the table:
      if ($idx->getName() == $name)
        throw new Exception("Index '{$name}' is already defined for table '{$tbname}'.");

      // If it's a primary key, check if there is another primary key already defined for the table:
      if ($type == DbVocab::IDX_PRIMARY && $idx->getType() == DbVocab::IDX_PRIMARY)
        throw new Exception("Table '{$tbname}' already has one primary key defined, you can't define another one.");
    }
    $this->tableRef = $tableRef;
    $this->name = $type == DbVocab::IDX_PRIMARY ? Sql::INDEX_DICT[DbVocab::IDX_PRIMARY] : $name;
    $this->type = $type;
    $this->columns = [];
  }

  public function onColumn(string $name)
  {
    if (is_numeric($name))
      throw new Exception("Invalid column name '{$name}' for index '{$this->name}'.");

    $this->columns[] = $name;

    return $this;
  }

  public function setColumns(array $columns)
  {
    $columns = array_values($columns);

    for ($i = 0; $i < count($columns); $i++)
      if (!is_string($columns[$i]) || is_numeric($columns[$i]))
        throw new Exception("Invalid column name '{$columns[$i]}' among columns set for index '{$this->name}'.");

    $this->columns = array_merge($this->columns, $columns);
    return $this;
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getColumns(): array
  {
    return $this->columns;
  }
}
