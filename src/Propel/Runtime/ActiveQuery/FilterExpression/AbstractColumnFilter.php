<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UnresolvedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * Filter statement on a query
 */
abstract class AbstractColumnFilter extends AbstractFilter
{
    /**
     * @var \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression
     */
    protected $queryColumn;

    /**
     * Operator literal.
     *
     * @var string
     */
    protected $operator;

    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $columnIdentifier
     * @param string $operator
     * @param mixed $value
     */
    public function __construct(Criteria $query, AbstractColumnExpression $columnIdentifier, string $operator, $value)
    {
        parent::__construct($query, $value);
        $this->queryColumn = $columnIdentifier;
        $this->operator = $operator;
    }

    /**
     * @param bool $useQuoteIfEnable = true
     *
     * @return string
     */
    public function getLocalColumnName(bool $useQuoteIfEnable = true): string
    {
        return $this->queryColumn->getColumnExpressionInQuery($useQuoteIfEnable);
    }

    /**
     * Column name without table prefix.
     *
     * Will be DB column name if column could be resolved.
     *
     * @return string
     */
    public function getColumnName(): string
    {
        return $this->queryColumn->getColumnName();
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression
     */
    public function getQueryColumn(): AbstractColumnExpression
    {
        return $this->queryColumn;
    }

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string
    {
        return $this->queryColumn->getTableAlias();
    }

    /**
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function getColumnMap(): ?ColumnMap
    {
        return $this->queryColumn->hasColumnMap()
            ? $this->queryColumn->getColumnMap()
            : null;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return array{table?: ?string, column?: string, value: mixed, type?: int}
     */
    protected function buildParameter(): array
    {
        return $this->buildParameterWithValue($this->value);
    }

    /**
     * @see AbstractFilter::buildStatement()
     *
     * @return void
     */
    protected function resolveUnresolved(): void
    {
        if ($this->queryColumn instanceof UnresolvedColumnExpression) {
            $this->queryColumn = $this->query->resolveColumn($this->queryColumn->getColumnExpressionInQuery());
        }
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param mixed $value
     *
     * @return array{table?: ?string, column?: string, value: mixed, type?: int}
     */
    protected function buildParameterWithValue($value): array
    {
        return $this->queryColumn->buildPdoParam($value);
    }

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     * @param array|null $parameter Optional parameter to use instead of default
     *
     * @return string Positional variable in query for the added parameter
     */
    protected function addParameter(array &$paramCollector, ?array $parameter = null): string
    {
        $parameter = $parameter ?: $this->buildParameter();

        return parent::addParameter($paramCollector, $parameter);
    }

    /**
     * This method checks another Criteria to see if they contain
     * the same attributes and hashtable entries.
     *
     * @param object|null $filter
     *
     * @return bool
     */
    public function equals(?object $filter): bool
    {
        return parent::equals($filter)
            && $filter instanceof static
            && $this->operator === $filter->operator
            && $this->queryColumn->equals($filter->queryColumn);
    }

    /**
     * Ensures deep cloning of attached objects
     *
     * @return void
     */
    public function __clone()
    {
        parent::__clone();
        $this->queryColumn = clone $this->queryColumn;
    }
}
