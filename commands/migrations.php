<?php

namespace SplitPHP\Commands;

use Exception;
use SplitPHP\ObjLoader;
use SplitPHP\Cli;
use SplitPHP\Utils;
use SplitPHP\Exceptions\DatabaseException;
use SplitPHP\Database\DbConnections;
use SplitPHP\DbMigrations\MigrationVocab;

class Migrations extends Cli
{
  private $sqlBuilder;

  public function init()
  {
    $this->sqlBuilder = ObjLoader::load(CORE_PATH . "/database/" . DBTYPE . "class.sql.php");

    $this->createMigrationTables();

    $this->addCommand('apply', function () {
      if (DB_CONNECT != 'on')
        throw new Exception("Database connections are turned off. Turn'em 'on' to perform migrations.");

      if (DB_TRANSACTIONAL == 'on')
        DbConnections::retrieve('main')->startTrasaction();
      try {

        if (DB_TRANSACTIONAL == 'on')
          DbConnections::retrieve('main')->commitTransaction();
      } catch (Exception $exc) {
        if (DB_TRANSACTIONAL == 'on')
          DbConnections::retrieve('main')->rollbackTransaction();

        throw $exc;
      }
    });
  }

  private function createMigrationTables()
  {
    // Create Table 'Migration':
    $columns = [
      (object)[
        'name' => 'id',
        'type' => MigrationVocab::DATATYPE_INT,
        'unsigned' => true,
        'nullable' => false,
        'autoIncrement' => true,
      ],
      (object)[
        'name' => 'date_exec',
        'type' => MigrationVocab::DATATYPE_DATETIME,
        'unsigned' => false,
        'nullable' => false,
        'autoIncrement' => false,
        'defaultValue' => MigrationVocab::SQL_CURTIMESTAMP(),
      ],
      (object)[
        'name' => 'filepath',
        'type' => MigrationVocab::DATATYPE_STRING,
        'length' => 255,
        'unsigned' => false,
        'nullable' => false,
        'autoIncrement' => false,
      ],
    ];

    $sql = $this->sqlBuilder
      ->create('SPLITPHP_MIGRATION', $columns)
      ->alter('SPLITPHP_MIGRATION')
      ->addKey('id', MigrationVocab::IDX_PRIMARY);

    DbConnections::retrieve('main')->runMany($sql->output(true));

    // Create Table "Migration's Operation":
    $columns = [
      (object)[
        'name' => 'id',
        'type' => MigrationVocab::DATATYPE_INT,
        'unsigned' => true,
        'nullable' => false,
        'autoIncrement' => true,
      ],
      (object)[
        'name' => 'id_migration',
        'type' => MigrationVocab::DATATYPE_INT,
        'unsigned' => true,
        'nullable' => false,
        'autoIncrement' => false,
      ],
      (object)[
        'name' => 'up',
        'type' => MigrationVocab::DATATYPE_TEXT,
        'nullable' => false,
        'autoIncrement' => false,
      ],
      (object)[
        'name' => 'down',
        'type' => MigrationVocab::DATATYPE_TEXT,
        'nullable' => false,
        'autoIncrement' => false,
      ],
    ];

    $sql = $this->sqlBuilder
      ->create('SPLITPHP_MIGRATION_OPERATION', $columns)
      ->alter('SPLITPHP_MIGRATION_OPERATION')
      ->addKey('id', MigrationVocab::IDX_PRIMARY)
      ->addKey('id_migration', MigrationVocab::IDX_INDEX, 'operation_refto_migration', separator: ';')
      ->alter('SPLITPHP_MIGRATION_OPERATION')
      ->addConstraint(
        localColumns: 'id_migration',
        refTable: 'SPLITPHP_MIGRATION',
        refColumns: 'id',
        onUpdate: MigrationVocab::FKACTION_CASCADE,
        onDelete: MigrationVocab::FKACTION_CASCADE,
        separator: ';'
      );

    DbConnections::retrieve('main')->runMany($sql->output(true));
  }

  private function runMigrationBlock($sqlobj)
  {
    try {
      DbConnections::retrieve('main')->runMany($sqlobj);
    } catch (DatabaseException $ex) {
      $errorCode = $ex->getCode();       // Ex.: 1061, 1068, 1005, 150 etc on MySQL

      $msg = $ex->getMessage();
if (in_array($errorCode, [1061, 1068, 1005, 150], true)
    && preg_match('/Duplicate key name|FOREIGN KEY constraint.*already exists/i', $msg)) {
    // Ignora duplicação
    return;
}

      // Caso contrário, relança a exceção normalmente
      throw $ex;
    }
  }
}
