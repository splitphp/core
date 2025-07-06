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

use Exception;
use SplitPHP\Database\SqlExpression;
use SplitPHP\Database\DbVocab;

final class ProcedureBlueprint extends Blueprint
{
  private array $args;
  private object $output;
  private SqlExpression $instructions;

  public final function __construct(string $name)
  {
    require_once CORE_PATH.'/database/class.vocab.php';

    unset($this->tableRef);
    $this->name = $name;
  }

  public final function withArg(string $name, string $type){
    if (isset($this->args[$name]))
      throw new Exception("Argument '{$name}' already exists in procedure '{$this->name}'.");

    $this->args[] = (object) [
      'name' => $name,
      'type' => $type
    ];

    return $this;
  }

  public final function getArgs(): array {
    return $this->args ?? [];
  }

  public final function outputs(string $name, string $type){
    if (isset($this->output))
      throw new Exception("Output already defined for procedure '{$this->name}'.");

    $this->output = (object) [
      'name' => $name,
      'type' => $type
    ];

    return $this;
  }

  public final function getOutput(): ?object {
    return $this->output ?? null;
  }

  public final function setInstructions(string $instructions){
    if (isset($this->instructions))
      throw new Exception("Instructions already defined for procedure '{$this->name}'.");

    $this->instructions = new SqlExpression($instructions);

    return $this;
  }

  public final function getInstructions(): ?SqlExpression {
    return $this->instructions ?? null;
  }
}
