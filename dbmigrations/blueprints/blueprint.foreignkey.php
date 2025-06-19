<?php

namespace SplitPHP\DbMigrations;

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
