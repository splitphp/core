<?php

namespace SplitPHP {
  interface DbMigration
  {
    public function up(callable $callback);
    public function down(callable $callback);
  }
}


namespace SplitPHP\DatabaseMigrations {

  use Exception;

  class RawExpression
  {
    protected string $expression;

    public function __construct(string $expression)
    {
      $this->expression = $expression;
    }

    public function getExpression(): string
    {
      return $this->expression;
    }
  }

  class ColumnBlueprint
  {
    private const TYPES = [
      'string',    // variable-length text
      'text',      // large text
      'int',       // signed 32-bit int
      'bigInt',    // signed 64-bit int
      'boolean',   // tiny true/false
      'decimal',   // fixed precision
      'float',     // floating-point
      'date',      // YYYY-MM-DD
      'dateTime',  // YYYY-MM-DD HH:MM:SS
      'time',      // HH:MM:SS
      'timestamp', // dateTime + timezone support
      'binary',    // blob
      'json',      // JSON document
      'uuid',      // 128-bit identifier
    ];

    public const CONSTRAINT_CASCADE  =  1;
    public const CONSTRAINT_SETNULL  =  2;
    public const CONSTRAINT_NOACTION =  3;
    public const CONSTRAINT_RESTRICT =  4;

    private $name;
    private $type;
    private $length;
    private $primaryKeyFlag;
    private $autoIncrementFlag;
    private $foreignKeyConstraint;
    private $nullableFlag;
    private $charset;
    private $collation;
    private $defaultValue;

    public final function __construct(
      string $name,
      string $type,
      ?int $length = null
    ) {
      if (!in_array($type, self::TYPES))
        throw new Exception("Invalid type '{$type}' for column '{$name}'. Allowed types are: " . implode(', ', self::TYPES));

      define('SQL_CURRENT_TIMESTAMP', new RawExpression('CURRENT_TIMESTAMP'));
      $this->name = $name;
      $this->type = $type;
      $this->length = $length;
      $this->primaryKeyFlag = false;
      $this->autoIncrementFlag = false;
      $this->nullableFlag = false;
      $this->charset = 'utf8mb4';
      $this->collation = 'utf8mb4_general_ci';
    }

    public final function info()
    {
      return (object) get_object_vars($this);
    }

    public final function setCharset(string $charset)
    {
      $this->charset = $charset;
      return $this;
    }

    public final function setCollation(string $collation)
    {
      $this->collation = $collation;
      return $this;
    }

    public final function primaryKey(bool $autoIncrement = true)
    {
      if (!empty($this->foreignKeyConstraint))
        throw new Exception("Column '{$this->name}' cannot be the primary key because it's already a foreign key to '{$this->foreignKeyConstraint->table}.{$this->foreignKeyConstraint->column}'.");

      $this->primaryKeyFlag = true;
      if ($autoIncrement) $this->autoIncrementFlag = true;

      return $this;
    }

    public final function autoIncrement()
    {
      $this->autoIncrementFlag = true;
      return $this;
    }

    public final function nullable()
    {
      $this->nullableFlag = true;
      return $this;
    }

    public final function foreignKey(
      string $table,
      string $column,
      int $onDelete = self::CONSTRAINT_CASCADE,
      int $onUpdate = self::CONSTRAINT_CASCADE
    ) {
      if ($this->primaryKeyFlag)
        throw new Exception("Column '{$this->name}' cannot be a foreign key because it's already the table's primary key.");

      $this->foreignKeyConstraint = (object) [
        'table' => $table,
        'column' => $column,
        'onDelete' => $onDelete,
        'onUpdate' => $onUpdate
      ];

      return $this;
    }

    public final function setDefaultVal($val)
    {
      if ($val === null && !$this->nullableFlag)
        throw new Exception("[Invalid default value error]: Column {$this->name} cannot be NULL.");
      if ($val === SQL_CURRENT_TIMESTAMP && !in_array($this->type, $this->timeTypes()))
        throw new Exception("[Invalid default value error]: Column {$this->name} cannot store a date or time value.");
      $this->defaultValue = $val;
    }

    private function timeTypes()
    {
      $result = [];
      foreach (self::TYPES as $t) {
        if (str_contains(strtolower($t), 'date') || str_contains(strtolower($t), 'time'))
          $result[] = $t;
      }

      return $result;
    }
  }

  class TableBlueprint
  {
    private $name;
    private $columns;

    public final function __construct(string $name)
    {
      $this->name = $name;
      $this->columns = [];
    }

    protected final function addColumn(
      string $name,
      string $type = 'int',
      ?int $length = null
    ) {
      $columnObj = new ColumnBlueprint($name, $type, $length);
      $this->columns[$name] = $columnObj;

      return $columnObj;
    }
  }
}
