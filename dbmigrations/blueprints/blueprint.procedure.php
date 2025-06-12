<?php

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
