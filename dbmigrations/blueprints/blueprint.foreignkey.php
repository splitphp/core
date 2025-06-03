<?php

namespace SplitPHP\DbMigrations;

use Exception;

final class ForeignKeyBlueprint extends Blueprint
{
  private $localColumns;
  private $referencedTable;
  private $referencedColumns;
  private $onUpdateAction;
  private $onDeleteAction;

  public final function __construct(TableBlueprint $tableRef, string|array $columns)
  {
    if (!is_array($columns)) $columns = [$columns];

    foreach ($columns as $clm)
      if (!is_string($clm) || is_numeric($clm))
        throw new Exception("Invalid column name '{$clm}' among columns set for foreign key.");

    $tbInfo = $tableRef->info();
    foreach ($tbInfo->foreignKeys as $fk)
      if ($fk->columns === $columns)
        throw new Exception("This combination of columns are already being used as foreign keys on this table '{$tbInfo->name}'.");

    $this->tableRef = $tableRef;
    $this->localColumns = $columns;
    $this->onUpdateAction = MigrationVocab::FKACTION_RESTRICT;
    $this->onDeleteAction = MigrationVocab::FKACTION_RESTRICT;
  }

  public function references(array|string $columns)
  {
    if (!is_array($columns)) $columns = [$columns];

    for ($i = 0; $i < count($columns); $i++)
      if (!is_string($columns[$i]) || is_numeric($columns[$i]))
        throw new Exception("Invalid column name '{$columns[$i]}' among columns set for foreign key.");

    $this->referencedColumns = $columns;
  }

  public function atTable(string $tbName)
  {
    $this->referencedTable = $tbName;
  }

  public function onUpdate(string $action)
  {
    if (!in_array($action, MigrationVocab::FKACTIONS))
      throw new Exception("Invalid foreign key on-update-action '{$action}'. Available actions: " . implode(', ', MigrationVocab::FKACTIONS));

    $this->onUpdateAction = $action;
  }

  public function onDelete(string $action)
  {
    if (!in_array($action, MigrationVocab::FKACTIONS))
      throw new Exception("Invalid foreign key on-delete-action '{$action}'. Available actions: " . implode(', ', MigrationVocab::FKACTIONS));

    $this->onDeleteAction = $action;
  }
}
