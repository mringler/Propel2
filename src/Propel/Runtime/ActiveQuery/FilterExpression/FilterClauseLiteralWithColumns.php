<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use LogicException;
use Propel\Common\Exception\SetColumnConverterException;
use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\ColumnMap;

/**
 * A full filter statement given as a string, i.e. `col = ?`, `col1 = col2`, `col IS NULL`, `1=1`
 *
 * Column expression in clause must be resolvable by ColumnResolver::resolveColumns(), so
 * PDO types can be inferred.
 */
class FilterClauseLiteralWithColumns extends AbstractFilterClauseLiteral
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param string $clause
     * @param mixed $value
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException
     */
    public function __construct(Criteria $query, string $clause, $value = null)
    {
        parent::__construct($query, $clause, $value);

        if ($this->value && !$this->hasResolvedColumns()) {
            throw new InvalidClauseException("{$this->clause} - Cannot find column to determine value type");
        }

        $referenceColumn = AbstractColumnExpression::findFirstColumnExpressionWithColumnMap($this->resolvedColumns);
        if ($referenceColumn) {
            $this->applyOperatorBehavior($referenceColumn);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $referenceColumn
     *
     * @throws \LogicException
     *
     * @return void
     */
    protected function applyOperatorBehavior(AbstractColumnExpression $referenceColumn)
    {
        if (!$referenceColumn->hasColumnMap()) {
            throw new LogicException('reference column has to have a column map.');
        }
        $columnMap = $referenceColumn->getColumnMap();

        $this->value = static::convertValueForColumn($this->value, $columnMap);

        if (!$this->value && $this->isInOperator()) {
            $this->clause = $this->isNotInOperator() ? '1=1' : '1<>1'; // see InColumnFilter::buildFilterClause()
        }

        if ($columnMap->isSetType() && $this->isInOperator()) { // see old ModelCriteria::getCriterionForClause()
            $this->clause = $this->isNotInOperator()
                ? substr($this->clause, 0, -8) . '& ? = 0'
                : substr($this->clause, 0, -4) . '& ?';
        }

        if ($this->value === null && $this->isBinaryOperator()) {
            $this->clause = (stripos($this->clause, '= 0') !== false) ? '1=1' : '1<>1'; // see BinaryColumnFilter::buildFilterClause()
        }
    }

    /**
     * @return bool
     */
    protected function isInOperator(): bool
    {
        return strtoupper(substr($this->clause, -5)) === ' IN ?';
    }

    /**
     * @return bool
     */
    protected function isNotInOperator(): bool
    {
        return strtoupper(substr($this->clause, -9)) === ' NOT IN ?';
    }

    /**
     * @return bool
     */
    protected function isBinaryOperator(): bool
    {
        return stripos($this->clause, '& ?') !== false;
    }

    /**
     * @see FilterClauseLiteral::buildParameterByPosition()
     *
     * @param int $position
     * @param mixed $value
     *
     * @return array
     */
    protected function buildParameterByPosition(int $position, $value): array
    {
        $columnIndex = count($this->resolvedColumns) === 1 ? 0 : $position;

        return $this->resolvedColumns[$columnIndex]->buildPdoParam($value);
    }

    /**
     * Converts value for some column types
     *
     * @param mixed $value The value to convert
     * @param \Propel\Runtime\Map\ColumnMap $colMap The ColumnMap object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return mixed The converted value
     */
    public static function convertValueForColumn($value, ColumnMap $colMap)
    {
        if ($colMap->getType() === 'OBJECT' && is_object($value)) {
            $value = serialize($value);
        } elseif ($colMap->getType() === 'ARRAY' && is_array($value)) {
            $value = '| ' . implode(' | ', $value) . ' |';
        } elseif ($colMap->getType() === PropelTypes::ENUM && $value !== null) {
            $value = (is_array($value))
                ? array_map([$colMap, 'getValueSetKey'], $value)
                : $colMap->getValueSetKey($value);
        } elseif ($colMap->isSetType() && $value !== null) {
            try {
                $value = SetColumnConverter::convertToInt($value, $colMap->getValueSet());
            } catch (SetColumnConverterException $e) {
                throw new PropelException(sprintf('Value "%s" is not accepted in this set column', $e->getValue()), $e->getCode(), $e);
            }
        }

        return $value;
    }
}
