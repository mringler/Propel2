<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\Criterion;

use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface;

/**
 * List of conditions and combine operators (and/or)
 */
class ClauseList
{
    /**
     * @var string
     */
    public const AND_OPERATOR_LITERAL = 'AND';

    /**
     * @var string
     */
    public const OR_OPERATOR_LITERAL = 'OR';

    /**
     * Other connected Filter
     *
     * @var array<int, \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    protected $clauses = [];

    /**
     * Operators for connected filters
     * Only self::UND and self::ODER are accepted
     *
     * @var array<int, string>
     */
    protected $conjunctions = [];

    /**
     * Get the list of clauses in this Filter.
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getClauses(): array
    {
        return $this->clauses;
    }

    /**
     * Get the list of conjunctions in this Filter
     *
     * @return array
     */
    public function getConjunctions(): array
    {
        return $this->conjunctions;
    }

    /**
     * Append filter with operator.
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     * @param string $conjunction
     *
     * @return static
     */
    public function addFilter(ColumnFilterInterface $filter, string $conjunction = self::AND_OPERATOR_LITERAL)
    {
        $this->clauses[] = $filter;
        $this->conjunctions[] = $conjunction;

        return $this;
    }

    /**
     * Append an AND filter onto this Filter's list.
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return $this
     */
    public function addAnd(ColumnFilterInterface $filter)
    {
        $this->addFilter($filter, self::AND_OPERATOR_LITERAL);

        return $this;
    }

    /**
     * Append an OR filter onto this Filter's list.
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return $this
     */
    public function addOr(ColumnFilterInterface $filter)
    {
        $this->addFilter($filter, self::OR_OPERATOR_LITERAL);

        return $this;
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
        if ($this === $filter) {
            return true;
        }

        if (
            !$filter instanceof static
            || count($this->clauses) !== count($filter->clauses)
        ) {
            return false;
        }

        // check chained filter

        $clausesLength = count($this->clauses);
        for ($i = 0; $i < $clausesLength; $i++) {
            $sameConjunction = ($this->conjunctions[$i] === $filter->conjunctions[$i]);
            $sameFilter = $this->clauses[$i]->equals($filter->clauses[$i]);
            if (!$sameConjunction || !$sameFilter) {
                return false;
            }
        }

        return true;
    }

    /**
     * get an array of all filter attached to this
     * recursing through all sub filter
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getAttachedFilter(): array
    {
        $filters = [];
        foreach ($this->getClauses() as $filter) {
            $filters = array_merge($filters, $filter->getAttachedFilter());
        }

        return $filters;
    }

    /**
     * Ensures deep cloning of attached objects
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->clauses as $key => $filter) {
            $this->clauses[$key] = clone $filter;
        }
    }

    /**
     * Check if this or any of the attached filters is a Criterion
     *
     * @return bool
     */
    public function containsCriterion(): bool
    {
        foreach ($this->clauses as $clause) {
            if ($clause->containsCriterion()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of clauses in the list
     *
     * @return int
     */
    public function count(): int
    {
        $counted = 1;
        foreach ($this->clauses as $clause) {
            $counted += $clause->count();
        }

        return $counted;
    }
}
