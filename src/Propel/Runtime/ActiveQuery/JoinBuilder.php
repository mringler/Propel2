<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\FilterExpression\FilterFactory;

/**
 * Extracted from Criteria
 */
class JoinBuilder
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param string $left A String with the left side of the join.
     * @param string $right A String with the right side of the join.
     * @param string|null $joinType A String with the join operator
     *                        among Criteria::INNER_JOIN, Criteria::LEFT_JOIN,
     *                        and Criteria::RIGHT_JOIN
     *
     * @return \Propel\Runtime\ActiveQuery\Join
     */
    public static function buildJoin(Criteria $criteria, string $left, string $right, ?string $joinType = null): Join
    {
        $join = new Join();
        $join->setIdentifierQuoting($criteria->isIdentifierQuotingEnabled());
        $join->setJoinType($joinType);
        [$leftTableName, $leftTableAlias, $leftColumnName] = static::extractColumnData($criteria, $left);
        [$rightTableName, $rightTableAlias, $rightColumnName] = static::extractColumnData($criteria, $right);

        $join->addExplicitCondition(
            $leftTableName,
            $leftColumnName,
            $leftTableAlias,
            $rightTableName,
            $rightColumnName,
            $rightTableAlias,
            Join::EQUAL,
        );

        return $join;
    }

    /**
     * Add a join with multiple conditions
     *
     * @see http://propel.phpdb.org/trac/ticket/167, http://propel.phpdb.org/trac/ticket/606
     *
     * Example usage:
     * $c->addMultipleJoin([
     *     [LeftTableMap::LEFT_COLUMN, RightTableMap::RIGHT_COLUMN], // if no third argument, defaults to Criteria::EQUAL
     *     [FoldersTableMap::LFT, FoldersTableMap::RGT, Criteria::LESS_EQUAL ]
     *   ],
     *   Criteria::LEFT_JOIN
     * );
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param array<array{0: string|mixed, 1: string|mixed, 2?: string|null}> $conditions An array of conditions, each condition being an array (left, right, operator)
     * @param string|null $joinType A String with the join operator. Defaults to an implicit join.
     *
     * @return \Propel\Runtime\ActiveQuery\Join
     */
    public static function buildJoinWithMultipleConditions(Criteria $criteria, array $conditions, ?string $joinType = null): Join
    {
        $join = new Join();
        $join->setIdentifierQuoting($criteria->isIdentifierQuotingEnabled());
        $joinCondition = null;
        foreach ($conditions as $condition) {
            [$left, $right] = $condition;
            [$leftTableName, $leftTableAlias, $leftColumnName] = static::extractColumnData($criteria, $left);
            [$rightTableName, $rightTableAlias, $rightColumnName] = static::extractColumnData($criteria, $right);

            if (!$join->getRightTableName()) {
                $join->setRightTableName($rightTableName);
            }

            if (!$join->getRightTableAlias() && $rightTableAlias) {
                $join->setRightTableAlias($rightTableAlias);
            }

            $leftSide = static::buildNameFromParts($leftTableName, $leftTableAlias, $leftColumnName);
            $operator = $condition[2] ?? Join::EQUAL;
            $rightSide = static::buildNameFromParts($rightTableName, $rightTableAlias, $rightColumnName);

            $conditionClause = "$leftSide$operator$rightSide";
            $fullColumnName = "$leftTableName.$leftColumnName";

            $criterion = FilterFactory::build($criteria, $fullColumnName, Criteria::CUSTOM, $conditionClause);

            if ($joinCondition === null) {
                $joinCondition = $criterion;
            } else {
                $joinCondition->addAnd($criterion);
            }
        }
        $join->setJoinType($joinType);
        $join->setJoinCondition($joinCondition);

        return $join;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param string $columnIdentifier
     *
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    protected static function extractColumnData(Criteria $criteria, string $columnIdentifier): array
    {
        $pos = strrpos($columnIdentifier, '.');
        if (!$pos) {
            return [null, null, $columnIdentifier];
        }

        $tableAlias = substr($columnIdentifier, 0, $pos);
        $columnName = substr($columnIdentifier, $pos + 1);
        [$tableName, $tableAlias] = $criteria->getTableNameAndAlias($tableAlias);

        return [$tableName, $tableAlias, $columnName];
    }

    /**
     * @param string|null $tableName
     * @param string|null $tableAlias
     * @param string $columnName
     *
     * @return string
     */
    protected static function buildNameFromParts(?string $tableName, ?string $tableAlias, string $columnName): string
    {
        return implode('.', array_filter([$tableName, $tableAlias, $columnName]));
    }
}
