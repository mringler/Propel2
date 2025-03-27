<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use LogicException;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\CriterionFactory;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;

class FilterFactory
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string|null $columnOrClause
     * @param string|int|null $comparison
     * @param mixed $value
     *
     * @throws \LogicException
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface
     */
    public static function build(Criteria $query, $columnOrClause, $comparison = null, $value = null): ColumnFilterInterface
    {
        if (is_int($comparison)) {
            // $comparison is a PDO::PARAM_* constant value
            // something like $c->add('foo like ?', '%bar%', PDO::PARAM_STR);

            if ($columnOrClause === null) {
                throw new InvalidClauseException('Empty clause in column filter - Passing PDO type to Criteria::where()/Criteria::and() requires a non-empty clause.');
            } elseif ($columnOrClause instanceof AbstractColumnExpression) {
                throw new LogicException('Column of clause with PDO types was resolved, but should have remained a string. Resolved column: ' . print_r($columnOrClause, true));
            }

            return new FilterClauseLiteralWithPdoTypes($query, $columnOrClause, $value, $comparison);
        }

        if ($value instanceof Criteria) {
            if (is_string($columnOrClause)) {
                throw new LogicException("Cannot use unresolved column name as input for subquery filter ($comparison)");
            }

            return static::buildSubqueryFilter($query, $columnOrClause, $comparison, $value);
        }

        if ($comparison === null) {
            $comparison = Criteria::EQUAL;
        }

        if (is_string($columnOrClause)) {
            return new FilterClauseLiteralWithColumns($query, $columnOrClause);

            // TODO still needed for having, group by, join
            return CriterionFactory::build($query, $columnOrClause, $comparison, $value);
        } else {
            return static::buildFilterCondition($query, $columnOrClause, $comparison, $value);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null $column
     * @param string|null $comparison
     * @param mixed $value
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\AbstractFilter
     */
    protected static function buildFilterCondition(Criteria $query, ?AbstractColumnExpression $column, $comparison = null, $value = null): AbstractFilter
    {
        switch ($comparison) {
            case Criteria::IN:
            case Criteria::NOT_IN:
                // table.column IN (?, ?) or table.column NOT IN (?, ?)
                // something like $c->add(BookTableMap::TITLE, array('foo', 'bar'), Criteria::IN);
                return new InColumnFilter($query, $column, $comparison, $value);
            case Criteria::LIKE:
            case Criteria::NOT_LIKE:
            case Criteria::ILIKE:
            case Criteria::NOT_ILIKE:
                // table.column LIKE ? or table.column NOT LIKE ?  (or ILIKE for Postgres)
                // something like $c->add(BookTableMap::TITLE, 'foo%', Criteria::LIKE);
                return new LikeColumnFilter($query, $column, $comparison, $value);
            case Criteria::BINARY_NONE:
            case Criteria::BINARY_ALL:
                // table.column & ? = 0 (Similar to  "NOT IN")
                // something like $c->add(BookTableMap::SOME_ARRAY_VAR, 26, Criteria::BINARY_NONE);
                return new BinaryColumnFilter($query, $column, $comparison, $value);
            case Criteria::CUSTOM:
                // custom expression with no parameter binding
                // something like $c->add(BookTableMap::TITLE, "CONCAT(book.TITLE, 'bar') = 'foobar'", Criteria::CUSTOM);
                return new FilterClauseLiteralWithColumns($query, $value);
            default:
                // simple comparison
                // something like $c->add(BookTableMap::PRICE, 12, Criteria::GREATER_THAN);
                return new ColumnFilter($query, $column, $comparison, $value);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $outerQuery
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null $column
     * @param string|null $comparison
     * @param \Propel\Runtime\ActiveQuery\Criteria $innerQuery
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\AbstractFilter
     */
    protected static function buildSubqueryFilter(
        Criteria $outerQuery,
        $column,
        ?string $comparison,
        Criteria $innerQuery
    ): AbstractFilter {
        if ($comparison === null) {
            $comparison = Criteria::IN;
        }

        switch ($comparison) {
            case ExistsColumnFilter::TYPE_EXISTS:
            case ExistsColumnFilter::TYPE_NOT_EXISTS:
                return new ExistsColumnFilter($outerQuery, null, $comparison, $innerQuery);
            default:
                return new ColumnToQueryFilter($outerQuery, $column, $comparison, $innerQuery);
        }
    }
}
