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
use SplitPHP\Database\Dbmetadata;


final class ForeignKeyBlueprint extends Blueprint
{
  private $localColumns;
  private $referencedTable;
  private $referencedColumns;
  private $onUpdateAction;
  private $onDeleteAction;

  public final function __construct(TableBlueprint $tableRef, string|array $columns, ?string $name = null)
  {
    if (!is_array($columns)) $columns = [$columns];

    foreach ($columns as $clm)
      if (!is_string($clm) || is_numeric($clm))
        throw new Exception("Invalid column name '{$clm}' among columns set for foreign key.");

    $tbname = $tableRef->getName();
    foreach ($tableRef->getForeignKeys() as $fk)
      if ($fk->localColumns === $columns)
        throw new Exception("This combination of columns are already being used as foreign keys on this table '{$tbname}'.");

    $this->tableRef = $tableRef;
    $this->localColumns = $columns;
    $this->name = $name ?? "fk_" . uniqid();
    $this->onUpdateAction = DbVocab::FKACTION_RESTRICT;
    $this->onDeleteAction = DbVocab::FKACTION_RESTRICT;
  }

  public function getLocalColumns(): array
  {
    return $this->localColumns;
  }

  public function getReferencedTable(): ?string
  {
    return $this->referencedTable;
  }

  public function getReferencedColumns(): array
  {
    return $this->referencedColumns;
  }

  public function references(array|string $columns)
  {
    if (!is_array($columns)) $columns = [$columns];

    for ($i = 0; $i < count($columns); $i++)
      if (!is_string($columns[$i]) || is_numeric($columns[$i]))
        throw new Exception("Invalid column name '{$columns[$i]}' among columns set for foreign key.");

    $this->referencedColumns = $columns;

    return $this;
  }

  public function atTable(string $tbName)
  {
    // $existingTables = Dbmetadata::listTables();
    // if (!in_array($tbName, $existingTables))
    //   throw new Exception("Table '{$tbName}', referenced in this Foreign Key, doesn't exist.");

    $this->referencedTable = $tbName;

    return $this;
  }

  public function onUpdate(string $action)
  {
    if (!in_array($action, DbVocab::FKACTIONS))
      throw new Exception("Invalid foreign key on-update-action '{$action}'. Available actions: " . implode(', ', DbVocab::FKACTIONS));

    $this->onUpdateAction = $action;
    return $this;
  }

  public function getOnUpdateAction(): string
  {
    return $this->onUpdateAction;
  }

  public function onDelete(string $action)
  {
    if (!in_array($action, DbVocab::FKACTIONS))
      throw new Exception("Invalid foreign key on-delete-action '{$action}'. Available actions: " . implode(', ', DbVocab::FKACTIONS));

    $this->onDeleteAction = $action;
    return $this;
  }

  public function getOnDeleteAction(): string
  {
    return $this->onDeleteAction;
  }
}
