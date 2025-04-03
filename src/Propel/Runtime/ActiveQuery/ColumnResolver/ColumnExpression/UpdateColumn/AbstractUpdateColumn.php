<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteTypedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * A column used in an insert or update, including value expression.
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractUpdateColumn extends RemoteTypedColumnExpression
{
    /**
     * @var mixed
     */
    protected $value;

    /**
     * A column used in an insert or update, including value expression.
     *
     * @param mixed $value
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string|null $tableAlias
     * @param string $columnName
     * @param int $pdoType
     * @param \Propel\Runtime\Map\ColumnMap|null $columnMap
     */
    protected function __construct($value, Criteria $sourceQuery, ?string $tableAlias, string $columnName, int $pdoType, ?ColumnMap $columnMap = null)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnName, $pdoType, $columnMap);
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param array<array{table?: ?string, column?: string, value: mixed, type?: int}> $paramCollector
     *
     * @return void
     */
    public function collectParam(array &$paramCollector): void
    {
        $paramCollector[] = parent::buildPdoParam($this->value);
    }

    /**
     * @param int $positionIndex
     *
     * @return string
     */
    public function buildAssignmentClause(int &$positionIndex): string
    {
        $columnNameInUpdate = $this->getColumnExpressionInQuery(true, true);
        $expression = $this->buildExpression($positionIndex);

        return "$columnNameInUpdate=$expression";
    }

    /**
     * Build expression in query.
     *
     * @param int $positionIndex
     *
     * @return string
     */
    abstract public function buildExpression(int &$positionIndex): string;
}
