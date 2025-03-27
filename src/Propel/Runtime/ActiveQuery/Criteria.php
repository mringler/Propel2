<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery;

use Exception;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteTypedColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\NormalizedFilterExpression;
use Propel\Runtime\ActiveQuery\Criterion\AbstractCriterion;
use Propel\Runtime\ActiveQuery\FilterExpression\AbstractColumnFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface;
use Propel\Runtime\ActiveQuery\FilterExpression\FilterFactory;
use Propel\Runtime\ActiveQuery\QueryExecutor\CountQueryExecutor;
use Propel\Runtime\ActiveQuery\QueryExecutor\DeleteAllQueryExecutor;
use Propel\Runtime\ActiveQuery\QueryExecutor\DeleteQueryExecutor;
use Propel\Runtime\ActiveQuery\QueryExecutor\InsertQueryExecutor;
use Propel\Runtime\ActiveQuery\QueryExecutor\SelectQueryExecutor;
use Propel\Runtime\ActiveQuery\QueryExecutor\UpdateQueryExecutor;
use Propel\Runtime\ActiveQuery\SqlBuilder\SelectQuerySqlBuilder;
use Propel\Runtime\Adapter\AdapterInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\Util\PropelConditionalProxy;

/**
 * This is a utility class for holding criteria information for a query.
 *
 * @author Hans Lellelid <hans@xmpl.org> (Propel)
 * @author Kaspars Jaudzems <kaspars.jaudzems@inbox.lv> (Propel)
 * @author Frank Y. Kim <frank.kim@clearink.com> (Torque)
 * @author John D. McNally <jmcnally@collab.net> (Torque)
 * @author Brett McLaughlin <bmclaugh@algx.net> (Torque)
 * @author Eric Dobbs <eric@dobbse.net> (Torque)
 * @author Henning P. Schmiedehausen <hps@intermeta.de> (Torque)
 * @author Sam Joseph <sam@neurogrid.com> (Torque)
 */
class Criteria
{
    /**
     * @var string
     */
    public const EQUAL = '=';

    /**
     * @var string
     */
    public const NOT_EQUAL = '<>';

    /**
     * @var string
     */
    public const ALT_NOT_EQUAL = '!=';

    /**
     * @var string
     */
    public const GREATER_THAN = '>';

    /**
     * @var string
     */
    public const LESS_THAN = '<';

    /**
     * @var string
     */
    public const GREATER_EQUAL = '>=';

    /**
     * @var string
     */
    public const LESS_EQUAL = '<=';

    /**
     * @var string
     */
    public const LIKE = ' LIKE ';

    /**
     * @var string
     */
    public const NOT_LIKE = ' NOT LIKE ';

    /**
     * @var string
     */
    public const CONTAINS_ALL = 'CONTAINS_ALL';

    /**
     * @var string
     */
    public const CONTAINS_SOME = 'CONTAINS_SOME';

    /**
     * @var string
     */
    public const CONTAINS_NONE = 'CONTAINS_NONE';

    /**
     * @var string
     */
    public const ILIKE = ' ILIKE ';

    /**
     * @var string
     */
    public const NOT_ILIKE = ' NOT ILIKE ';

    /**
     * @var string
     */
    public const CUSTOM = 'CUSTOM';

    /**
     * @var string
     */
    public const RAW = 'RAW';

    /**
     * @var string
     */
    public const CUSTOM_EQUAL = 'CUSTOM_EQUAL';

    /**
     * @var string
     */
    public const DISTINCT = 'DISTINCT';

    /**
     * @var string
     */
    public const IN = ' IN ';

    /**
     * @var string
     */
    public const NOT_IN = ' NOT IN ';

    /**
     * @var string
     */
    public const ALL = 'ALL';

    /**
     * @var string
     */
    public const JOIN = 'JOIN';

    /**
     * @var string
     */
    public const BINARY_AND = '&';

    /**
     * @var string
     */
    public const BINARY_OR = '|';

    /**
     * @var string
     */
    public const BINARY_ALL = 'BINARY_ALL';

    /**
     * @var string
     */
    public const BINARY_NONE = 'BINARY_NONE';

    /**
     * @var string
     */
    public const ASC = 'ASC';

    /**
     * @var string
     */
    public const DESC = 'DESC';

    /**
     * @var string
     */
    public const ISNULL = ' IS NULL ';

    /**
     * @var string
     */
    public const ISNOTNULL = ' IS NOT NULL ';

    /**
     * @var string
     */
    public const CURRENT_DATE = 'CURRENT_DATE';

    /**
     * @var string
     */
    public const CURRENT_TIME = 'CURRENT_TIME';

    /**
     * @var string
     */
    public const CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     */
    public const LEFT_JOIN = 'LEFT JOIN';

    /**
     * @var string
     */
    public const RIGHT_JOIN = 'RIGHT JOIN';

    /**
     * @var string
     */
    public const INNER_JOIN = 'INNER JOIN';

    /**
     * @var string
     */
    public const LOGICAL_OR = 'OR';

    /**
     * @var string
     */
    public const LOGICAL_AND = 'AND';

    /**
     * @var bool
     */
    protected $ignoreCase = false;

    /**
     * @var bool
     */
    protected $singleRecord = false;

    /**
     * Columns used in SELECT
     *
     * @var array<string|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>
     */
    protected $selectColumns = [];

    /**
     * Storage of aliased select data. Collection of column names.
     *
     * @var array<string>
     */
    protected $asColumns = [];

    /**
     * Storage of select modifiers data. Collection of modifier names.
     *
     * @var array<string>
     */
    protected $selectModifiers = [];

    /**
     * Lock to be used to retrieve rows (if any).
     *
     * @var \Propel\Runtime\ActiveQuery\Lock|null
     */
    protected $lock;

    /**
     * Storage of conditions data. Collection of Criterion objects.
     *
     * @var array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    protected $map = [];

    /**
     * Storage of ordering data. Collection of column names.
     *
     * @var array<string>
     */
    protected $orderByColumns = [];

    /**
     * Storage of grouping data. Collection of column names.
     *
     * @var array<string>
     */
    protected $groupByColumns = [];

    /**
     * Storage of having data.
     *
     * @var \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null
     */
    protected $having;

    /**
     * Storage of join data. collection of Join objects.
     *
     * @var array<\Propel\Runtime\ActiveQuery\Join>
     */
    protected $joins = [];

    /**
     * @var array<\Propel\Runtime\ActiveQuery\Criteria>
     */
    protected $selectQueries = [];

    /**
     * The name of the database.
     *
     * @var string
     */
    protected $dbName;

    /**
     * The primary table for this Criteria.
     * Useful in cases where there are no select or where
     * columns.
     *
     * @var string
     */
    protected $primaryTableName;

    /**
     * The name of the database as given in the constructor.
     *
     * @var string|null
     */
    protected $originalDbName;

    /**
     * To limit the number of rows to return. <code>-1</code> means return all
     * rows.
     *
     * @var int
     */
    protected $limit = -1;

    /**
     * To start the results at a row other than the first one.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * Comment to add to the SQL query
     *
     * @var string
     */
    protected $queryComment;

    /**
     * @var array<string>
     */
    protected $aliases = [];

    /**
     * @var bool
     */
    protected $useTransaction = false;

    /**
     * Storage for Criterions expected to be combined
     *
     * @var array<string, \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    protected $namedCriterions = [];

    /**
     * Default operator for combination of criterions
     *
     * @see addUsingOperator()
     * @var string Criteria::LOGICAL_AND or Criteria::LOGICAL_OR
     */
    protected $defaultCombineOperator = self::LOGICAL_AND;

    /**
     * @var \Propel\Runtime\Util\PropelConditionalProxy|null
     */
    protected $conditionalProxy;

    /**
     * Whether identifier should be quoted.
     *
     * @var bool
     */
    protected $identifierQuoting = false;

    /**
     * Creates a new instance with the default capacity which corresponds to
     * the specified database.
     *
     * @param string|null $dbName The database name.
     */
    public function __construct(?string $dbName = null)
    {
        $this->setDbName($dbName);
        $this->originalDbName = $dbName;
    }

    /**
     * @deprecated use aptly named Criteria::getColumnFilter().
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getMap(): array
    {
        return $this->getColumnFilter();
    }

    /**
     * Get the criteria map, i.e. the array of Criterions
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getColumnFilter(): array
    {
        return $this->map;
    }

    /**
     * Brings this criteria back to its initial state, so that it
     * can be reused as if it was new. Except if the criteria has grown in
     * capacity, it is left at the current capacity.
     *
     * @return $this
     */
    public function clear()
    {
        $this->map = [];
        $this->namedCriterions = [];
        $this->ignoreCase = false;
        $this->singleRecord = false;
        $this->selectModifiers = [];
        $this->lock = null;
        $this->selectColumns = [];
        $this->orderByColumns = [];
        $this->groupByColumns = [];
        $this->having = null;
        $this->asColumns = [];
        $this->joins = [];
        $this->selectQueries = [];
        $this->dbName = $this->originalDbName;
        $this->offset = 0;
        $this->limit = -1;
        $this->aliases = [];
        $this->useTransaction = false;

        return $this;
    }

    /**
     * Add an AS clause to the select columns. Usage:
     *
     * <code>
     * Criteria myCrit = new Criteria();
     * myCrit->addAsColumn('alias', 'ALIAS('.MyTableMap::ID.')');
     * </code>
     *
     * If the name already exists, it is replaced by the new clause.
     *
     * @param string $name Wanted Name of the column (alias).
     * @param string $clause SQL clause to select from the table
     *
     * @return $this A modified Criteria object.
     */
    public function addAsColumn(string $name, string $clause)
    {
        $this->asColumns[$name] = $clause;

        return $this;
    }

    /**
     * Get the column aliases.
     *
     * @return array An assoc array which map the column alias names
     *               to the alias clauses.
     */
    public function getAsColumns(): array
    {
        return $this->asColumns;
    }

    /**
     * Returns the column name associated with an alias (AS-column).
     *
     * @param string $as Alias
     *
     * @return string|null
     */
    public function getColumnForAs(string $as): ?string
    {
        if (isset($this->asColumns[$as])) {
            return $this->asColumns[$as];
        }

        return null;
    }

    /**
     * Allows one to specify an alias for a table that can
     * be used in various parts of the SQL.
     *
     * @param string $alias
     * @param string $table
     *
     * @return $this A modified Criteria object.
     */
    public function addAlias(string $alias, string $table)
    {
        $this->aliases[$alias] = $table;

        return $this;
    }

    /**
     * Remove an alias for a table (useful when merging Criterias).
     *
     * @param string $alias
     *
     * @return $this A modified Criteria object.
     */
    public function removeAlias(string $alias)
    {
        unset($this->aliases[$alias]);

        return $this;
    }

    /**
     * Returns the aliases for this Criteria
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Returns the table name associated with an alias.
     *
     * @param string $alias
     *
     * @return string|null
     */
    public function getTableForAlias(string $alias): ?string
    {
        if (isset($this->aliases[$alias])) {
            return $this->aliases[$alias];
        }

        return null;
    }

    /**
     * Returns the table name and alias based on a table alias or name.
     * Use this method to get the details of a table name that comes in a clause,
     * which can be either a table name or an alias name.
     *
     * Array($tableName, $tableAlias)
     *
     * @param string $tableAliasOrName
     *
     * @return array
     */
    public function getTableNameAndAlias(string $tableAliasOrName): array
    {
        if (isset($this->aliases[$tableAliasOrName])) {
            return [$this->aliases[$tableAliasOrName], $tableAliasOrName];
        }

        return [$tableAliasOrName, null];
    }

    /**
     * Get the keys of the criteria map, i.e. the list of columns bearing a condition
     * <code>
     * print_r($c->keys());
     *  => array('book.price', 'book.title', 'author.first_name')
     * </code>
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->map);
    }

    /**
     * @deprecated use Criteria::hasFilterOnColumn
     *
     * Does this Criteria object contain the specified key?
     *
     * @param string $column [table.]column
     *
     * @return bool True if this Criteria object contain the specified key.
     */
    public function containsKey(string $column): bool
    {
        return $this->hasFilterOnColumn($column);
    }

    /**
     * Does this Criteria object contain the specified key?
     *
     * @param string $columnName [table.]column
     *
     * @return bool True if this Criteria object contain the specified key.
     */
    public function hasFilterOnColumn(string $columnName): bool
    {
        // must use array_key_exists() because the key could
        // exist but have a NULL value (that'd be valid).
        return isset($this->map[$columnName]);
    }

    /**
     * Does this Criteria object contain the specified key and does it have a value set for the key
     *
     * @param string $column [table.]column
     *
     * @return bool True if this Criteria object contain the specified key and a value for that key
     */
    public function keyContainsValue(string $column): bool
    {
        // must use array_key_exists() because the key could
        // exist but have a NULL value (that'd be valid).
        return isset($this->map[$column]) && $this->map[$column]->hasValue();
    }

    /**
     * Whether this Criteria has any where columns.
     *
     * This counts conditions added with the add() method.
     *
     * @see Criteria::add()
     *
     * @return bool
     */
    public function hasWhereClause(): bool
    {
        return (bool)$this->map;
    }

    /**
     * Will force the sql represented by this criteria to be executed within
     * a transaction. This is here primarily to support the oid type in
     * postgresql. Though it can be used to require any single sql statement
     * to use a transaction.
     *
     * @param bool $v
     *
     * @return $this
     */
    public function setUseTransaction(bool $v)
    {
        $this->useTransaction = $v;

        return $this;
    }

    /**
     * Whether the sql command specified by this criteria must be wrapped
     * in a transaction.
     *
     * @return bool
     */
    public function isUseTransaction(): bool
    {
        return $this->useTransaction;
    }

    /**
     * Method to return criteria related to columns in a table.
     *
     * Make sure you call containsKey($column) prior to calling this method,
     * since no check on the existence of the $column is made in this method.
     *
     * @param string $column Column name.
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface A Criterion object.
     */
    public function getCriterion(string $column): ColumnFilterInterface
    {
        return $this->map[$column];
    }

    /**
     * Method to return the latest Criterion in a table.
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null A Criterion or null no Criterion is added.
     */
    public function getLastCriterion(): ?ColumnFilterInterface
    {
        $count = count($this->map);
        if ($count) {
            $map = array_values($this->map);

            return $map[$count - 1];
        }

        return null;
    }

    /**
     * Method to return a Criterion that is not added automatically
     * to this Criteria. This can be used to chain the
     * Criterions to form a more complex where clause.
     *
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string|null $column Full name of column (for example TABLE.COLUMN).
     * @param mixed|null $value
     * @param string|int|null $comparison Criteria comparison constant or PDO binding type
     * @param bool $hasAccessToOutputColumns If AS columns can be used in the statement (for example in HAVING clauses)
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface
     */
    public function getNewCriterion($column, $value = null, $comparison = null, $hasAccessToOutputColumns = false): ColumnFilterInterface
    {
        if (is_string($column)) {
            $column = $this->resolveColumn($column, $hasAccessToOutputColumns);
        }

        return FilterFactory::build($this, $column, $comparison, $value);
    }

    /**
     * Shortcut method to get an array of columns indexed by table.
     * <code>
     * print_r($c->getTablesColumns());
     *  => array(
     *       'book' => array('book.price', 'book.title'),
     *       'author' => array('author.first_name')
     *     )
     * </code>
     *
     * @return array array(table => array(table.column1, table.column2))
     */
    public function getTablesColumns(): array
    {
        $tables = [];
        foreach ($this->keys() as $key) {
            $tableName = substr($key, 0, strrpos($key, '.') ?: null);
            $tables[$tableName][] = $key;
        }

        return $tables;
    }

    /**
     * Method to return a comparison String.
     *
     * @param string $key String name of the key.
     *
     * @return string|null A String with the value of the object at key.
     */
    public function getComparison(string $key): ?string
    {
        if (isset($this->map[$key])) {
            $filter = $this->map[$key];

            if ($filter instanceof AbstractColumnFilter) {
                return $filter->getOperator();
            } elseif ($filter instanceof AbstractCriterion) {
                return $filter->getComparison();
            }

            return $this->map[$key]->getOperator();
        }

        return null;
    }

    /**
     * Get the Database(Map) name.
     *
     * @return string A String with the Database(Map) name.
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * @return \Propel\Runtime\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return Propel::getServiceContainer()->getAdapter($this->getDbName());
    }

    /**
     * Set the DatabaseMap name. If <code>null</code> is supplied, uses value
     * provided by <code>Configuration::getDefaultDatasource()</code>.
     *
     * @param string|null $dbName The Database (Map) name.
     *
     * @return $this
     */
    public function setDbName(?string $dbName = null)
    {
        $this->dbName = ($dbName ?? Propel::getServiceContainer()->getDefaultDatasource());

        return $this;
    }

    /**
     * Get the primary table for this Criteria.
     *
     * This is useful for cases where a Criteria may not contain
     * any SELECT columns or WHERE columns. This must be explicitly
     * set, of course, in order to be useful.
     *
     * @return string|null
     */
    public function getPrimaryTableName(): ?string
    {
        return $this->primaryTableName;
    }

    /**
     * Returns the name of the table as used in the query.
     *
     * Either the SQL name or an alias.
     *
     * @return string|null
     */
    public function getTableNameInQuery(): ?string
    {
        return $this->primaryTableName;
    }

    /**
     * Sets the primary table for this Criteria.
     *
     * This is useful for cases where a Criteria may not contain
     * any SELECT columns or WHERE columns. This must be explicitly
     * set, of course, in order to be useful.
     *
     * @param string $tableName
     *
     * @return $this
     */
    public function setPrimaryTableName(string $tableName)
    {
        $this->primaryTableName = $tableName;

        return $this;
    }

    /**
     * @param string $identifier
     *
     * @return bool
     */
    public function isIdentifiedBy(string $identifier): bool
    {
        return $identifier === $this->primaryTableName;
    }

    /**
     * Method to return a String table name.
     *
     * @param string $name The name of the key.
     *
     * @return string|null The value of table for criterion at key.
     */
    public function getTableName(string $name): ?string
    {
        if (isset($this->map[$name])) {
            return $this->map[$name]->getTableAlias();
        }

        return null;
    }

    /**
     * Method to return the value that was added to Criteria.
     *
     * @param string $name A String with the name of the key.
     *
     * @return mixed The value of object at key.
     */
    public function getValue(string $name)
    {
        if (isset($this->map[$name])) {
            return $this->map[$name]->getValue();
        }

        return null;
    }

    /**
     * An alias to getValue() -- exposing a Hashtable-like interface.
     *
     * @param string $key An Object.
     *
     * @return mixed The value within the Criterion (not the Criterion object).
     */
    public function get(string $key)
    {
        return $this->getValue($key);
    }

    /**
     * Overrides Hashtable put, so that this object is returned
     * instead of the value previously in the Criteria object.
     * The reason is so that it more closely matches the behavior
     * of the add() methods. If you want to get the previous value
     * then you should first Criteria.get() it yourself. Note, if
     * you attempt to pass in an Object that is not a String, it will
     * throw a NPE. The reason for this is that none of the add()
     * methods support adding anything other than a String as a key.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function put(string $key, $value)
    {
        $this->add($key, $value);

        return $this;
    }

    /**
     * Copies all of the mappings from the specified Map to this Criteria
     * These mappings will replace any mappings that this Criteria had for any
     * of the keys currently in the specified Map.
     *
     * if the map was another Criteria, its attributes are copied to this
     * Criteria, overwriting previous settings.
     *
     * @param mixed $t Mappings to be stored in this map.
     *
     * @return $this
     */
    public function putAll($t)
    {
        if (is_array($t)) {
            foreach ($t as $key => $value) {
                if ($value instanceof ColumnFilterInterface) {
                    $this->map[$key] = $value;
                } else {
                    $this->put($key, $value);
                }
            }
        } elseif ($t instanceof Criteria) {
            $this->joins = $t->joins;
        }

        return $this;
    }

    /**
     * This method adds a new criterion to the list of criterias.
     * If a criterion for the requested column already exists, it is
     * replaced.
     *
     * Column name must include table identifier (as in 'TABLE.id').
     *
     * Lots of ways to call this:
     * - add('book.id', 42, Criteria::NOT_EQUAL)
     * - add('book.id = ?', 42, \PDO::PARAM_INT)
     * - add(null, BookQuery::create()->filterBy(...), Criteria::EXISTS)
     * - add($this->getNewCriterion(...))
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string|null $columnOrClause The column to run the comparison on, or a Criterion object.
     * @param mixed $value
     * @param string|int|null $comparison A String.
     *
     * @return $this A modified Criteria object.
     */
    public function add($columnOrClause, $value = null, $comparison = null)
    {
        $columnOrClause = $this->getCriterionForCondition($columnOrClause, $value, $comparison);
        $key = $columnOrClause->getLocalColumnName(false);
        $this->map[$key] = $columnOrClause;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return implode(', ', $this->map) . "\n";
    }

    /**
     * This method creates a new criterion but keeps it for later use with combine()
     * Until combine() is called, the condition is not added to the query
     *
     * <code>
     * $crit = new Criteria();
     * $crit->addCond('cond1', $column1, $value1, Criteria::GREATER_THAN);
     * $crit->addCond('cond2', $column2, $value2, Criteria::EQUAL);
     * $crit->combine(array('cond1', 'cond2'), Criteria::LOGICAL_OR);
     * </code>
     *
     * Any comparison can be used.
     *
     * The name of the table must be used implicitly in the column name,
     * so the Column name must be something like 'TABLE.id'.
     *
     * @param string $name name to combine the criterion later
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause The column to run the comparison on, or AbstractCriterion object.
     * @param mixed|null $value
     * @param string|null $comparison A String.
     *
     * @return $this A modified Criteria object.
     */
    public function addCond(string $name, $columnOrClause, $value = null, ?string $comparison = null)
    {
        $this->namedCriterions[$name] = $this->getCriterionForCondition($columnOrClause, $value, $comparison);

        return $this;
    }

    /**
     * Combine several named criterions with a logical operator
     *
     * @param array $criterions array of the name of the criterions to combine
     * @param string $operator logical operator, either Criteria::LOGICAL_AND, or Criteria::LOGICAL_OR
     * @param string|null $name optional name to combine the criterion later
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return $this
     */
    public function combine(array $criterions = [], string $operator = self::LOGICAL_AND, ?string $name = null)
    {
        $operatorMethod = (strtoupper($operator) === self::LOGICAL_AND) ? 'addAnd' : 'addOr';
        $namedCriterions = [];
        foreach ($criterions as $key) {
            if (array_key_exists($key, $this->namedCriterions)) {
                $namedCriterions[] = $this->namedCriterions[$key];
                unset($this->namedCriterions[$key]);
            } else {
                throw new LogicException(sprintf('Cannot combine unknown condition %s', $key));
            }
        }
        $firstCriterion = clone array_shift($namedCriterions);
        foreach ($namedCriterions as $criterion) {
            $firstCriterion->$operatorMethod($criterion);
        }
        if ($name === null) {
            $this->addAnd($firstCriterion, null, null);
        } else {
            $this->addCond($name, $firstCriterion, null, null);
        }

        return $this;
    }

    /**
     * This is the way that you should add a join of two tables.
     * Example usage:
     * <code>
     * $c->addJoin(ProjectTableMap::ID, FooTableMap::PROJECT_ID, Criteria::LEFT_JOIN);
     * // LEFT JOIN FOO ON (PROJECT.ID = FOO.PROJECT_ID)
     * </code>
     *
     * @param array|string $left A String with the left side of the join.
     * @param array|string $right A String with the right side of the join.
     * @param string|null $joinType A String with the join operator
     *                        among Criteria::INNER_JOIN, Criteria::LEFT_JOIN,
     *                        and Criteria::RIGHT_JOIN
     *
     * @return $this A modified Criteria object.
     */
    public function addJoin($left, $right, ?string $joinType = null)
    {
        if (is_array($left) && is_array($right)) {
            $conditions = [];
            foreach ($left as $key => $value) {
                $condition = [$value, $right[$key]];
                $conditions[] = $condition;
            }

            $this->addMultipleJoin($conditions, $joinType);

            return $this;
        }

        $join = new Join();
        $join->setIdentifierQuoting($this->isIdentifierQuotingEnabled());

        // is the left table an alias ?
        /** @phpstan-var string $left */
        $dotpos = strrpos($left, '.') ?: null;
        $leftTableAlias = substr($left, 0, $dotpos);
        $leftColumnName = substr($left, $dotpos + 1);
        [$leftTableName, $leftTableAlias] = $this->getTableNameAndAlias($leftTableAlias);

        // is the right table an alias ?
        /** @phpstan-var string $right */
        $dotpos = strrpos($right, '.') ?: null;
        $rightTableAlias = substr($right, 0, $dotpos);
        $rightColumnName = substr($right, $dotpos + 1);
        [$rightTableName, $rightTableAlias] = $this->getTableNameAndAlias($rightTableAlias);

        $join->addExplicitCondition(
            $leftTableName,
            $leftColumnName,
            $leftTableAlias,
            $rightTableName,
            $rightColumnName,
            $rightTableAlias,
            Join::EQUAL,
        );

        $join->setJoinType($joinType);

        $this->addJoinObject($join);

        return $this;
    }

    /**
     * Add a join with multiple conditions
     *
     * @deprecated Use {@link \Propel\Runtime\ActiveQuery\Join::setJoinCondition()} instead
     *
     * @see http://propel.phpdb.org/trac/ticket/167, http://propel.phpdb.org/trac/ticket/606
     *
     * Example usage:
     * $c->addMultipleJoin(array(
     *     array(LeftTableMap::LEFT_COLUMN, RightTableMap::RIGHT_COLUMN), // if no third argument, defaults to Criteria::EQUAL
     *     array(FoldersTableMap::alias( 'fo', FoldersTableMap::LFT ), FoldersTableMap::alias( 'parent', FoldersTableMap::RGT ), Criteria::LESS_EQUAL )
     *   ),
     *   Criteria::LEFT_JOIN
     * );
     * @see addJoin()
     *
     * @param array $conditions An array of conditions, each condition being an array (left, right, operator)
     * @param string|null $joinType A String with the join operator. Defaults to an implicit join.
     *
     * @return $this A modified Criteria object.
     */
    public function addMultipleJoin(array $conditions, ?string $joinType = null)
    {
        $join = new Join();
        $join->setIdentifierQuoting($this->isIdentifierQuotingEnabled());
        $joinCondition = null;
        foreach ($conditions as $condition) {
            $left = $condition[0];
            $right = $condition[1];
            $pos = strrpos($left, '.');
            if ($pos) {
                $leftTableAlias = substr($left, 0, $pos);
                $leftColumnName = substr($left, $pos + 1);
                [$leftTableName, $leftTableAlias] = $this->getTableNameAndAlias($leftTableAlias);
            } else {
                [$leftTableName, $leftTableAlias] = [null, null];
                $leftColumnName = $left;
            }

            $pos = strrpos($right, '.');
            if ($pos) {
                $rightTableAlias = substr($right, 0, $pos);
                $rightColumnName = substr($right, $pos + 1);
                [$rightTableName, $rightTableAlias] = $this->getTableNameAndAlias($rightTableAlias);
            } else {
                [$rightTableName, $rightTableAlias] = [null, null];
                $rightColumnName = $right;
            }

            if (!$join->getRightTableName()) {
                $join->setRightTableName($rightTableName);
            }

            if (!$join->getRightTableAlias() && $rightTableAlias) {
                $join->setRightTableAlias($rightTableAlias);
            }

            $conditionClause = $leftTableAlias ? $leftTableAlias . '.' : ($leftTableName ? $leftTableName . '.' : '');
            $conditionClause .= $leftColumnName;
            $conditionClause .= $condition[2] ?? Join::EQUAL;
            $conditionClause .= $rightTableAlias ? $rightTableAlias . '.' : ($rightTableName ? $rightTableName . '.' : '');
            $conditionClause .= $rightColumnName;
            $fullColumnName = $leftTableName . '.' . $leftColumnName;
            $criterion = FilterFactory::build($this, $fullColumnName, self::CUSTOM, $conditionClause);

            if ($joinCondition === null) {
                $joinCondition = $criterion;
            } else {
                $joinCondition = $joinCondition->addAnd($criterion);
            }
        }
        $join->setJoinType($joinType);
        $join->setJoinCondition($joinCondition);

        $this->addJoinObject($join);

        return $this;
    }

    /**
     * Add a join object to the Criteria
     *
     * @param \Propel\Runtime\ActiveQuery\Join $join A join object
     *
     * @return $this A modified Criteria object
     */
    public function addJoinObject(Join $join)
    {
        if (!in_array($join, $this->joins)) { // compare equality, NOT identity
            $this->joins[] = $join;
        }

        return $this;
    }

    /**
     * Get the array of Joins.
     *
     * @return array<\Propel\Runtime\ActiveQuery\Join>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * This method returns an already defined join clause from the query
     *
     * @param string $name The name of the join clause
     *
     * @return \Propel\Runtime\ActiveQuery\Join A join object
     */
    public function getJoin(string $name): Join
    {
        return $this->joins[$name];
    }

    /**
     * @param string $name The name of the join clause
     *
     * @return bool
     */
    public function hasJoin(string $name): bool
    {
        return isset($this->joins[$name]);
    }

    /**
     * @deprecated use aptly named Criteria::findJoinByIdentifier().
     *
     * @param string $identifier Propel uses (possibly qualified) PascalCase for logical table name, snake_case for DB table name, or alias
     *
     * @return \Propel\Runtime\ActiveQuery\Join|null
     */
    public function getJoinByIdentifier(string $identifier): ?Join
    {
        return $this->findJoinByIdentifier($identifier);
    }

    /**
     * @param string $identifier Propel uses (possibly qualified) PascalCase for logical table name, snake_case for DB table name, or alias
     *
     * @return \Propel\Runtime\ActiveQuery\Join|null
     */
    public function findJoinByIdentifier(string $identifier): ?Join
    {
        if ($this->hasJoin($identifier)) {
            return $this->getJoin($identifier);
        }

        foreach ($this->joins as $join) {
            if ($join->isIdentifiedBy($identifier)) {
                return $join;
            }
        }

        return null;
    }

    /**
     * Adds a Criteria as subQuery in the From Clause.
     *
     * @param self $subQuery Criteria to build the subquery from
     * @param string|null $alias alias for the subQuery
     *
     * @return $this this modified Criteria object (Fluid API)
     */
    public function addSubquery(self $subQuery, ?string $alias = null)
    {
        if ($alias === null) {
            $alias = 'alias_' . ($subQuery->forgeSelectQueryAlias() + count($this->selectQueries));
        }
        $this->selectQueries[$alias] = $subQuery;

        return $this;
    }

    /**
     * @deprecated use aptly named Criteria::addSubquery().
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $subQueryCriteria Criteria to build the subquery from
     * @param string|null $alias alias for the subQuery
     * @param bool $addAliasAndSelectColumns
     *
     * @return static The current object, for fluid interface
     */
    public function addSelectQuery(Criteria $subQueryCriteria, ?string $alias = null, bool $addAliasAndSelectColumns = true)
    {
        if ($this instanceof ModelCriteria) {
            return $this->addSubquery($subQueryCriteria, $alias, $addAliasAndSelectColumns);
        }

        return $this->addSubquery($subQueryCriteria, $alias);
    }

    /**
     * Checks whether this Criteria has a subquery.
     *
     * @return bool
     */
    public function hasSelectQueries(): bool
    {
        return (bool)$this->selectQueries;
    }

    /**
     * Get the associative array of Criteria for the subQueries per alias.
     *
     * @return array<\Propel\Runtime\ActiveQuery\Criteria>
     */
    public function getSelectQueries(): array
    {
        return $this->selectQueries;
    }

    /**
     * Get the Criteria for a specific subQuery.
     *
     * @param string $alias alias for the subQuery
     *
     * @return self
     */
    public function getSelectQuery(string $alias): self
    {
        return $this->selectQueries[$alias];
    }

    /**
     * checks if the Criteria for a specific subQuery is set.
     *
     * @param string $alias alias for the subQuery
     *
     * @return bool
     */
    public function hasSelectQuery(string $alias): bool
    {
        return isset($this->selectQueries[$alias]);
    }

    /**
     * @return int
     */
    public function forgeSelectQueryAlias(): int
    {
        $aliasNumber = 0;
        foreach ($this->getSelectQueries() as $c1) {
            $aliasNumber += $c1->forgeSelectQueryAlias();
        }

        return ++$aliasNumber;
    }

    /**
     * Adds 'ALL' modifier to the SQL statement.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setAll()
    {
        $this->removeSelectModifier(self::DISTINCT);
        $this->addSelectModifier(self::ALL);

        return $this;
    }

    /**
     * Adds 'DISTINCT' modifier to the SQL statement.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setDistinct()
    {
        $this->removeSelectModifier(self::ALL);
        $this->addSelectModifier(self::DISTINCT);

        return $this;
    }

    /**
     * Adds a modifier to the SQL statement.
     * e.g. self::ALL, self::DISTINCT, 'SQL_CALC_FOUND_ROWS', 'HIGH_PRIORITY', etc.
     *
     * @param string $modifier The modifier to add
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function addSelectModifier(string $modifier)
    {
        // only allow the keyword once
        if (!$this->hasSelectModifier($modifier)) {
            $this->selectModifiers[] = $modifier;
        }

        return $this;
    }

    /**
     * Removes a modifier to the SQL statement.
     * Checks for existence before removal
     *
     * @param string $modifier The modifier to add
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function removeSelectModifier(string $modifier)
    {
        $this->selectModifiers = array_values(array_diff($this->selectModifiers, [$modifier]));

        return $this;
    }

    /**
     * Checks the existence of a SQL select modifier
     *
     * @param string $modifier The modifier to add
     *
     * @return bool
     */
    public function hasSelectModifier(string $modifier): bool
    {
        return in_array($modifier, $this->selectModifiers, true);
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\Lock|null Get read lock value.
     */
    public function getLock(): ?Lock
    {
        return $this->lock;
    }

    /**
     * Apply a shared read lock to be used to retrieve rows.
     *
     * @param array<string> $tableNames
     * @param bool $noWait
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function lockForShare(array $tableNames = [], bool $noWait = false)
    {
        $this->withLock(Lock::SHARED, $tableNames, $noWait);

        return $this;
    }

    /**
     * Apply an exclusive read lock to be used to retrieve rows.
     *
     * @param array<string> $tableNames
     * @param bool $noWait
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function lockForUpdate(array $tableNames = [], bool $noWait = false)
    {
        $this->withLock(Lock::EXCLUSIVE, $tableNames, $noWait);

        return $this;
    }

    /**
     * Apply a read lock to be used to retrieve rows.
     *
     * @see Lock::SHARED
     * @see Lock::EXCLUSIVE
     *
     * @param string $lockType
     * @param array<string> $tableNames
     * @param bool $noWait
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    protected function withLock(string $lockType, array $tableNames = [], bool $noWait = false)
    {
        $this->lock = new Lock($lockType, $tableNames, $noWait);

        return $this;
    }

    /**
     * Retrieve rows without any read locking.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function withoutLock()
    {
        $this->lock = null;

        return $this;
    }

    /**
     * Sets ignore case.
     *
     * @param bool $b True if case should be ignored.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setIgnoreCase(bool $b)
    {
        $this->ignoreCase = $b;

        return $this;
    }

    /**
     * Is ignore case on or off?
     *
     * @return bool True if case is ignored.
     */
    public function isIgnoreCase(): bool
    {
        return $this->ignoreCase;
    }

    /**
     * Set single record? Set this to <code>true</code> if you expect the query
     * to result in only a single result record (the default behaviour is to
     * throw a PropelException if multiple records are returned when the query
     * is executed). This should be used in situations where returning multiple
     * rows would indicate an error of some sort. If your query might return
     * multiple records but you are only interested in the first one then you
     * should be using setLimit(1).
     *
     * @param bool $b Set to TRUE if you expect the query to select just one record.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setSingleRecord(bool $b)
    {
        $this->singleRecord = $b;

        return $this;
    }

    /**
     * Is single record?
     *
     * @return bool True if a single record is being returned.
     */
    public function isSingleRecord(): bool
    {
        return $this->singleRecord;
    }

    /**
     * Set limit.
     *
     * @param int $limit An int with the value for limit.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get limit.
     *
     * @return int An int with the value for limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Set offset.
     *
     * @param int $offset An int with the value for offset.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setOffset(int $offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Get offset.
     *
     * @return int An int with the value for offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Add select column.
     *
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $name Name of the select column.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function addSelectColumn($name)
    {
        $this->selectColumns[] = $name;

        return $this;
    }

    /**
     * Remove select column.
     *
     * @param string $name Name of the select column.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function removeSelectColumn(string $name)
    {
        foreach ($this->selectColumns as $index => $column) {
            $columnName = $column instanceof AbstractColumnExpression ? $column->getColumnExpressionInQuery() : $column;
            if ($columnName === $name) {
                unset($this->selectColumns[$index]);
            }
        }

        return $this;
    }

    /**
     * Set the query comment, that appears after the first verb in the SQL query
     *
     * @param string|null $comment The comment to add to the query, without comment sign
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function setComment(?string $comment)
    {
        $this->queryComment = $comment;

        return $this;
    }

    /**
     * Get the query comment, that appears after the first verb in the SQL query
     *
     * @return string|null The comment to add to the query, without comment sign
     */
    public function getComment(): ?string
    {
        return $this->queryComment;
    }

    /**
     * Whether this Criteria has any select columns.
     *
     * This will include columns added with addAsColumn() method.
     *
     * @see addAsColumn()
     * @see addSelectColumn()
     *
     * @return bool
     */
    public function hasSelectClause(): bool
    {
        return (bool)$this->selectColumns || (bool)$this->asColumns;
    }

    /**
     * Get select columns.
     *
     * For BC, this returns a string array, resolved columns will be turned to string.
     * Use {@see static::getSelectColumnsRaw()} to get actual array.
     *
     * @return array<string> An array with the name of the select columns.
     */
    public function getSelectColumns(): array
    {
        return array_map(fn ($col) => $col instanceof AbstractColumnExpression ? $col->getColumnExpressionInQuery() : $col, $this->selectColumns);
    }

    /**
     * Get select columns.
     *
     * Use {@see static::getSelectColumns()} if you need stringified versions of the columns.
     *
     * @return array<string|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression> An array with the name of the select columns.
     */
    public function getSelectColumnsRaw(): array
    {
        return $this->selectColumns;
    }

    /**
     * Clears current select columns.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function clearSelectColumns()
    {
        $this->selectColumns = $this->asColumns = [];

        return $this;
    }

    /**
     * Get select modifiers.
     *
     * @return array An array with the select modifiers.
     */
    public function getSelectModifiers(): array
    {
        return $this->selectModifiers;
    }

    /**
     * Add group by column name.
     *
     * @param string $groupBy The name of the column to group by.
     *
     * @return $this A modified Criteria object.
     */
    public function addGroupByColumn(string $groupBy)
    {
        $this->groupByColumns[] = $groupBy;

        return $this;
    }

    /**
     * Add order by column name, explicitly specifying ascending.
     *
     * @param string $name The name of the column to order by.
     *
     * @return $this A modified Criteria object.
     */
    public function addAscendingOrderByColumn(string $name)
    {
        $this->orderByColumns[] = $name . ' ' . self::ASC;

        return $this;
    }

    /**
     * Add order by column name, explicitly specifying descending.
     *
     * @param string $name The name of the column to order by.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function addDescendingOrderByColumn(string $name)
    {
        $this->orderByColumns[] = $name . ' ' . self::DESC;

        return $this;
    }

    /**
     * Get order by columns.
     *
     * @return array<string> An array with the name of the order columns.
     */
    public function getOrderByColumns(): array
    {
        return $this->orderByColumns;
    }

    /**
     * Clear the order-by columns.
     *
     * @return $this Modified Criteria object (for fluent API)
     */
    public function clearOrderByColumns()
    {
        $this->orderByColumns = [];

        return $this;
    }

    /**
     * Clear the group-by columns.
     *
     * @return $this
     */
    public function clearGroupByColumns()
    {
        $this->groupByColumns = [];

        return $this;
    }

    /**
     * Get group by columns.
     *
     * @return array<string>
     */
    public function getGroupByColumns(): array
    {
        return $this->groupByColumns;
    }

    /**
     * Get Having Criterion.
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null A Criterion object that is the having clause.
     */
    public function getHaving(): ?ColumnFilterInterface
    {
        return $this->having;
    }

    /**
     * Remove an object from the criteria.
     *
     * @param string $key A string with the key to be removed.
     *
     * @return mixed|null The removed value.
     */
    public function remove(string $key)
    {
        if (!isset($this->map[$key])) {
            return null;
        }

        /** @var \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null $removed */
        $removed = $this->map[$key];
        unset($this->map[$key]);
        if ($removed instanceof ColumnFilterInterface) {
            return $removed->getValue();
        }

        return $removed;
    }

    /**
     * Build a string representation of the Criteria.
     *
     * @return string A String with the representation of the Criteria.
     */
    public function toString(): string
    {
        $sb = 'Criteria:';
        try {
            $params = [];
            $sb .= "\nSQL (may not be complete): " . $this->createSelectSql($params);

            $sb .= "\nParams: ";
            $paramstr = [];
            foreach ($params as $param) {
                $paramstr[] = (isset($param['table']) ? $param['table'] . '.' : '')
                    . ($param['column'] ?? '')
                    . (isset($param['value']) ? ' => ' . var_export($param['value'], true) : '');
            }
            $sb .= implode(', ', $paramstr);
        } catch (Exception $exc) {
            $sb .= '(Error: ' . $exc->getMessage() . ')';
        }

        return $sb;
    }

    /**
     * Returns the size (count) of this criteria.
     *
     * @return int
     */
    public function size(): int
    {
        return count($this->map);
    }

    /**
     * This method checks another Criteria to see if they contain
     * the same attributes and hashtable entries.
     *
     * @param self $crit
     *
     * @return bool
     */
    public function equals(self $crit): bool
    {
        if ($this === $crit) {
            return true;
        }

        if ($this->size() === $crit->size()) {
            // Important: nested criterion objects are checked

            $criteria = $crit; // alias
            if (
                $this->offset === $criteria->getOffset()
                && $this->limit === $criteria->getLimit()
                && $this->ignoreCase === $criteria->isIgnoreCase()
                && $this->singleRecord === $criteria->isSingleRecord()
                && $this->dbName === $criteria->getDbName()
                && $this->selectModifiers === $criteria->getSelectModifiers()
                && $this->getSelectColumns() === $criteria->getSelectColumns()
                && $this->asColumns === $criteria->getAsColumns()
                && $this->orderByColumns === $criteria->getOrderByColumns()
                && $this->groupByColumns === $criteria->getGroupByColumns()
                && $this->aliases === $criteria->getAliases()
            ) { // what about having ??
                foreach ($criteria->keys() as $key) {
                    if ($this->hasFilterOnColumn($key)) {
                        $a = $this->getCriterion($key);
                        $b = $criteria->getCriterion($key);
                        if (!$a->equals($b)) {
                            return false;
                        }
                    } else {
                        return false;
                    }
                }

                $joins = $criteria->getJoins();
                if (count($joins) !== count($this->joins)) {
                    return false;
                }

                foreach ($joins as $key => $join) {
                    if (empty($this->joins[$key]) || !$join->equals($this->joins[$key])) {
                        return false;
                    }
                }

                $aLock = $this->lock;
                $bLock = $criteria->getLock();
                if ($aLock instanceof Lock && !$aLock->equals($bLock)) {
                    return false;
                }
                if ($bLock instanceof Lock && !$bLock->equals($aLock)) {
                    return false;
                }

                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Add the content of a Criteria to the current Criteria
     * In case of conflict, the current Criteria keeps its properties
     *
     * @param self $criteria The criteria to read properties from
     * @param string|null $operator The logical operator used to combine conditions
     *                           Defaults to Criteria::LOGICAL_AND, also accepts Criteria::LOGICAL_OR
     *                           This parameter is deprecated, use _or() instead
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return $this The current criteria object
     */
    public function mergeWith(self $criteria, ?string $operator = null)
    {
        // merge limit
        $limit = $criteria->getLimit();
        if ($limit && $this->getLimit() === -1) {
            $this->limit = $limit;
        }

        // merge offset
        $offset = $criteria->getOffset();
        if ($offset && $this->getOffset() === 0) {
            $this->offset = $offset;
        }

        // merge select modifiers
        $selectModifiers = $criteria->getSelectModifiers();
        if ($selectModifiers && !$this->selectModifiers) {
            $this->selectModifiers = $selectModifiers;
        }

        // merge lock
        $lock = $criteria->getLock();
        if ($lock && !$this->lock) {
            $this->lock = $lock;
        }

        // merge select columns
        $this->selectColumns = array_merge($this->getSelectColumnsRaw(), $criteria->getSelectColumnsRaw());

        // merge as columns
        $commonAsColumns = array_intersect_key($this->getAsColumns(), $criteria->getAsColumns());
        if ($commonAsColumns) {
            throw new LogicException('The given criteria contains an AsColumn with an alias already existing in the current object');
        }
        $this->asColumns = array_merge($this->getAsColumns(), $criteria->getAsColumns());

        // merge orderByColumns
        $orderByColumns = array_merge($this->getOrderByColumns(), $criteria->getOrderByColumns());
        $this->orderByColumns = array_unique($orderByColumns);

        // merge groupByColumns
        $groupByColumns = array_merge($this->getGroupByColumns(), $criteria->getGroupByColumns());
        $this->groupByColumns = array_unique($groupByColumns);

        // merge where conditions
        if ($operator === self::LOGICAL_OR) {
            $this->_or();
        }
        $isFirstCondition = true;
        foreach ($criteria->getColumnFilter() as $key => $criterion) {
            if ($isFirstCondition && $this->defaultCombineOperator === self::LOGICAL_OR) {
                $this->addOr($criterion, null, null, false);
                $this->defaultCombineOperator = self::LOGICAL_AND;
            } elseif ($this->hasFilterOnColumn($key)) {
                $this->addAnd($criterion);
            } else {
                $this->add($criterion);
            }
            $isFirstCondition = false;
        }

        // merge having
        $having = $criteria->getHaving();
        if ($having) {
            if ($this->getHaving()) {
                $this->addHaving($this->getHaving()->addAnd($having));
            } else {
                $this->addHaving($having);
            }
        }

        // merge alias
        $commonAliases = array_intersect_key($this->getAliases(), $criteria->getAliases());
        if ($commonAliases) {
            throw new LogicException('The given criteria contains an alias already existing in the current object');
        }
        $this->aliases = array_merge($this->getAliases(), $criteria->getAliases());

        // merge join
        $this->joins = array_merge($this->getJoins(), $criteria->getJoins());

        return $this;
    }

    /**
     * This method adds a prepared Criterion object to the Criteria as a having clause.
     * You can get a new, empty Criterion object with the
     * getNewCriterion() method.
     *
     * Can be called
     * - addHaving('fooCol', 42)
     * - addHaving('fooCol', 42, Criteria::GREATER_THAN)
     * - addHaving('fooCol > 42')
     * - addHaving('fooCol > ?', 42, \PDO::PARAM_INT)
     * - addHaving('fooCol', 42, Criteria::GREATER_THAN, \PDO::PARAM_INT) // for AS columns
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause A filter or a SQL clause with a question mark placeholder, or a column name
     * @param mixed $value The value to bind in the condition
     * @param string|int|null $comparison A PDO::PARAM_ class constant or an operator
     * @param int|null $pdoType A PDO::PARAM_ class constant
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return $this A modified Criteria object.
     */
    public function addHaving($columnOrClause, $value = null, $comparison = null, ?int $pdoType = null)
    {
        if ($pdoType !== null && is_string($columnOrClause)) {
            if (!NormalizedFilterExpression::isColumnLiteral($columnOrClause)) {
                throw new PropelException("Expected column name, but found '$columnOrClause'");
            }
            $columnOrClause = new RemoteTypedColumnExpression($this, null, $columnOrClause, $pdoType);
        }
        $this->having = $this->getCriterionForCondition($columnOrClause, $value, $comparison, true);

        return $this;
    }

    /**
     * Build a Criterion.
     *
     * This method has multiple signatures, and behaves differently according to it:
     *
     *  - If the first argument is a Criterion, it just returns this Criterion.
     *    <code>$c->getCriterionForCondition($criterion); // returns $criterion</code>
     *
     *  - If the last argument is a PDO::PARAM_* constant value, create a Criterion
     *    using Criteria::RAW and $comparison as a type.
     *    <code>$c->getCriterionForCondition('foo like ?', '%bar%', PDO::PARAM_STR);</code>
     *
     *  - Otherwise, create a classic Criterion based on a column name and a comparison.
     *    <code>$c->getCriterionForCondition(BookTableMap::TITLE, 'War%', Criteria::LIKE);</code>
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string|null $columnOrClause A Criterion, or a SQL clause with a question mark placeholder, or a column name
     * @param mixed|null $value The value to bind in the condition
     * @param string|int|null $comparison A Criteria class constant, or a PDO::PARAM_ class constant
     * @param bool $hasAccessToOutputColumns If AS columns can be used in the statement (for example in HAVING clauses)
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface
     */
    protected function getCriterionForCondition(
        $columnOrClause,
        $value = null,
        $comparison = null,
        bool $hasAccessToOutputColumns = false
    ): ColumnFilterInterface {
        if ($columnOrClause instanceof ColumnFilterInterface) {
            // it's already a Criterion, so ignore $value and $comparison
            return $columnOrClause;
        }

        if (is_string($columnOrClause) && NormalizedFilterExpression::isColumnLiteral($columnOrClause)) {
            $columnOrClause = $this->resolveColumn($columnOrClause, $hasAccessToOutputColumns);
        }

        // $comparison is one of Criteria's constants, or a PDO binding type
        // something like $c->add(BookTableMap::TITLE, 'War%', Criteria::LIKE);
        return FilterFactory::build($this, $columnOrClause, $comparison, $value);
    }

    /**
     * If a criterion for the requested column already exists, the condition is "AND"ed to the existing criterion (necessary for Propel 1.4 compatibility).
     * If no criterion for the requested column already exists, the condition is "AND"ed to the latest criterion.
     * If no criterion exist, the condition is added a new criterion
     *
     * Any comparison can be used.
     *
     * Supports a number of different signatures:
     *  - addAnd(column, value, comparison)
     *  - addAnd(column, value)
     *  - addAnd(Criterion)
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause The column to run the comparison on (e.g. BookTableMap::ID), or Criterion object
     * @param mixed|null $value
     * @param mixed|null $condition
     * @param bool $preferColumnCondition
     *
     * @return static A modified Criteria object.
     */
    public function addAnd($columnOrClause, $value = null, $condition = null, bool $preferColumnCondition = true)
    {
        return $this->addFilter(self::LOGICAL_AND, $columnOrClause, $value, $condition, $preferColumnCondition);
    }

    /**
     * If a prior criterion exists, the condition is "OR"ed to it.
     * If no criterion exist, the condition is added a new criterion
     *
     * Any comparison can be used.
     *
     * Supports a number of different signatures:
     *  - addOr(column, value, comparison)
     *  - addOr(column, value)
     *  - addOr(Criterion)
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause The column to run the comparison on (e.g. BookTableMap::ID), or Criterion object
     * @param mixed $value
     * @param mixed $condition
     * @param bool $preferColumnCondition
     *
     * @return static A modified Criteria object.
     */
    public function addOr($columnOrClause, $value = null, $condition = null, bool $preferColumnCondition = true)
    {
        return $this->addFilter(self::LOGICAL_OR, $columnOrClause, $value, $condition, $preferColumnCondition);
    }

    /**
     * @param string $andOr
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause
     * @param mixed $value
     * @param string|int|null $condition
     * @param bool $preferColumnCondition
     *
     * @return static
     */
    protected function addFilter(string $andOr, $columnOrClause, $value = null, $condition = null, bool $preferColumnCondition = true)
    {
        $filter = $this->getCriterionForCondition($columnOrClause, $value, $condition);

        if ($andOr === self::LOGICAL_OR) {
            $parentFilter = $this->getLastCriterion();
        } else {
            $key = $filter->getLocalColumnName();
            $parentFilter = $preferColumnCondition && $this->hasFilterOnColumn($key) ? $this->getCriterion($key) : null;
        }

        if (!$parentFilter) {
            return $this->add($filter);
        }
        ($andOr === self::LOGICAL_OR) ? $parentFilter->addOr($filter) : $parentFilter->addAnd($filter);

        return $this;
    }

    /**
     * Overrides Criteria::add() to use the default combine operator
     *
     * @see Criteria::add()
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|string $columnOrClause The column to run the comparison on (e.g. BookTableMap::ID), or Criterion object
     * @param mixed $value
     * @param string|null $operator A String, like Criteria::EQUAL.
     * @param bool $preferColumnCondition If true, the condition is combined with an existing condition on the same column
     * (necessary for Propel 1.4 compatibility).
     * If false, the condition is combined with the last existing condition.
     *
     * @return static A modified Criteria object.
     */
    public function addUsingOperator($columnOrClause, $value = null, ?string $operator = null, bool $preferColumnCondition = true)
    {
        if ($this->defaultCombineOperator === self::LOGICAL_OR) {
            $this->defaultCombineOperator = self::LOGICAL_AND;

            return $this->addOr($columnOrClause, $value, $operator, $preferColumnCondition);
        }

        return $this->addAnd($columnOrClause, $value, $operator, $preferColumnCondition);
    }

    /**
     * Method to create an SQL query based on values in a Criteria.
     *
     * This method creates only prepared statement SQL (using ? where values
     * will go). The second parameter ($params) stores the values that need
     * to be set before the statement is executed. The reason we do it this way
     * is to let the PDO layer handle all escaping & value formatting.
     *
     * @param array $params Parameters that are to be replaced in prepared statement.
     *
     * @return string
     */
    public function createSelectSql(array &$params): string
    {
        $preparedStatementDto = SelectQuerySqlBuilder::createSelectSql($this, $params);
        $params = $preparedStatementDto->getParameters();

        return $preparedStatementDto->getSqlStatement();
    }

    /**
     * @deprecated resolve the tableMap yourself and use Criteria::quoteColumnIdentifier().
     *
     * Quotes identifier based on $this->isIdentifierQuotingEnabled() and $tableMap->isIdentifierQuotingEnabled.
     *
     * @param string $string
     * @param string $tableName
     *
     * @return string
     */
    public function quoteIdentifier(string $string, string $tableName = ''): string
    {
        if ($this->isIdentifierQuotingEnabled()) {
            return $this->getAdapter()->quote($string);
        }

        //find table name and ask tableMap if quoting is enabled
        $pos = strrpos($string, '.');
        if (!$tableName && $pos !== false) {
            $tableName = substr($string, 0, $pos);
        }

        $tableMapName = $this->getTableForAlias($tableName) ?: $tableName;

        if ($tableMapName) {
            $dbMap = Propel::getServiceContainer()->getDatabaseMap($this->getDbName());
            if ($dbMap->hasTable($tableMapName)) {
                $tableMap = $dbMap->getTable($tableMapName);
                if ($tableMap->isIdentifierQuotingEnabled()) {
                    return $this->getAdapter()->quote($string);
                }
            }
        }

        return $string;
    }

    /**
     * Quotes identifier based on $this->isIdentifierQuotingEnabled() and $tableMap->isIdentifierQuotingEnabled.
     *
     * @param string|null $tableAlias
     * @param string $columnAlias
     * @param \Propel\Runtime\Map\TableMap|null $tableMap
     *
     * @return string
     */
    public function quoteColumnIdentifier(?string $tableAlias, string $columnAlias, ?TableMap $tableMap = null): string
    {
        if (!$this->isIdentifierQuotingEnabled() && !($tableMap && $tableMap->isIdentifierQuotingEnabled())) {
            return $tableAlias ? "$tableAlias.$columnAlias" : $columnAlias;
        }

        return $this->getAdapter()->quoteColumnIdentifier($tableAlias, $columnAlias);
    }

    /**
     * @param string $clause
     * @param bool $hasAccessToOutputColumns If AS columns can be used in the statement (for example in HAVING clauses)
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\NormalizedFilterExpression
     */
    public function normalizeFilterExpression(string $clause, bool $hasAccessToOutputColumns = false): NormalizedFilterExpression
    {
        $columnProcessor = fn ($s) => $this->resolveColumn($s, $hasAccessToOutputColumns);

        return NormalizedFilterExpression::normalizeExpression($clause, $columnProcessor);
    }

    /**
     * @param string $columnIdentifier
     * @param bool $hasAccessToOutputColumns If AS columns can be used in the statement (for example in HAVING clauses)
     * @param bool $failSilently
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression
     */
    public function resolveColumn(string $columnIdentifier, bool $hasAccessToOutputColumns = false, bool $failSilently = true): AbstractColumnExpression
    {
        // Regular Criteria has no TableMap, all columns can be considered remote.
        return RemoteColumnExpression::fromString($this, $columnIdentifier);
    }

    /**
     * @deprecated use ModelCriteria::replaceColumnNames()
     *
     * @param string $sql
     *
     * @return bool
     */
    public function replaceNames(string &$sql): bool
    {
        $normalizedExpression = $this->normalizeFilterExpression($sql);
        $sql = $normalizedExpression->getNormalizedFilterExpression();

        return (bool)$normalizedExpression->getReplacedColumns();
    }

    /**
     * Default implementation.
     *
     * @param string $sql
     *
     * @return string
     */
    public function replaceColumnNames(string $sql): string
    {
        $normalizedExpression = $this->normalizeFilterExpression($sql);

        return $normalizedExpression->getNormalizedFilterExpression();
    }

    /**
     * Method to perform inserts based on values and keys in a
     * Criteria.
     * <p>
     * If the primary key is auto incremented the data in Criteria
     * will be inserted and the auto increment value will be returned.
     * <p>
     * If the primary key is included in Criteria then that value will
     * be used to insert the row.
     * <p>
     * If no primary key is included in Criteria then we will try to
     * figure out the primary key from the database map and insert the
     * row with the next available id using util.db.IDBroker.
     * <p>
     * If no primary key is defined for the table the values will be
     * inserted as specified in Criteria and null will be returned.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con A ConnectionInterface connection.
     *
     * @return mixed The primary key for the new row if the primary key is auto-generated. Otherwise will return null.
     */
    public function doInsert(?ConnectionInterface $con = null)
    {
        return InsertQueryExecutor::execute($this, $con);
    }

    /**
     * @param self|null $criteria
     *
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function getPrimaryKey(?self $criteria = null): ?ColumnMap
    {
        if (!$criteria) {
            $criteria = $this;
        }
        // Assume all the keys are for the same table.
        $keys = $criteria->keys();
        $key = $keys[0];
        $table = $criteria->getTableName($key);

        $pk = null;

        if ($table) {
            $dbMap = Propel::getServiceContainer()->getDatabaseMap($criteria->getDbName());

            $pks = $dbMap->getTable($table)->getPrimaryKeys();
            if ($pks) {
                $pk = array_shift($pks);
            }
        }

        return $pk;
    }

    /**
     * Method used to update rows in the DB. Rows are selected based
     * on selectCriteria and updated using values in updateValues.
     * <p>
     * Use this method for performing an update of the kind:
     * <p>
     * WHERE some_column = some value AND could_have_another_column =
     * another value AND so on.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $updateValues A Criteria object containing values used in set clause.
     * @param \Propel\Runtime\Connection\ConnectionInterface $con The ConnectionInterface connection object to use.
     *
     * @return int The number of rows affected by last update statement.
     *             For most uses there is only one update statement executed, so this number will
     *             correspond to the number of rows affected by the call to this method.
     *             Note that the return value does require that this information is returned
     *             (supported) by the Propel db driver.
     */
    public function doUpdate(Criteria $updateValues, ConnectionInterface $con): int
    {
        return UpdateQueryExecutor::execute($this, $updateValues, $con);
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Propel\Runtime\DataFetcher\DataFetcherInterface
     */
    public function doCount(?ConnectionInterface $con = null): DataFetcherInterface
    {
        return CountQueryExecutor::execute($this, $con);
    }

    /**
     * Checks whether the Criteria needs to use column aliasing
     * This is implemented in a service class rather than in Criteria itself
     * in order to avoid doing the tests when it's not necessary (e.g. for SELECTs)
     *
     * @return bool
     */
    public function needsSelectAliases(): bool
    {
        $columnNames = [];
        foreach ($this->getSelectColumnsRaw() as $fullyQualifiedColumnName) {
            if ($fullyQualifiedColumnName instanceof AbstractColumnExpression) {
                if (!$fullyQualifiedColumnName->getTableAlias()) {
                    continue;
                }
                $columnName = $fullyQualifiedColumnName->getColumnName();
            } else {
                $pos = strrpos($fullyQualifiedColumnName, '.');
                if ($pos === false) {
                    continue;
                }
                $columnName = substr($fullyQualifiedColumnName, $pos);
            }
            if (isset($columnNames[$columnName])) {
                // more than one column with the same name, so aliasing is required
                return true;
            }
            $columnNames[$columnName] = true;
        }

        return false;
    }

    /**
     * Issue a DELETE query based on the current ModelCriteria
     * This method is called by ModelCriteria::delete() inside a transaction
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con a connection object
     *
     * @return int The number of deleted rows
     */
    public function doDelete(?ConnectionInterface $con = null): int
    {
        return DeleteQueryExecutor::execute($this, $con);
    }

    /**
     * Issue a DELETE query based on the current ModelCriteria deleting all rows in the table
     * This method is called by ModelCriteria::deleteAll() inside a transaction
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con a connection object
     *
     * @return int The number of deleted rows
     */
    public function doDeleteAll(?ConnectionInterface $con = null): int
    {
        return DeleteAllQueryExecutor::execute($this, $con);
    }

    /**
     * Builds, binds and executes a SELECT query based on the current object.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con A connection object
     *
     * @return \Propel\Runtime\DataFetcher\DataFetcherInterface A dataFetcher using the connection, ready to be fetched
     */
    public function doSelect(?ConnectionInterface $con = null): DataFetcherInterface
    {
        return SelectQueryExecutor::execute($this, $con);
    }

    // Fluid operators

    /**
     * @return $this
     */
    public function _or()
    {
        $this->defaultCombineOperator = self::LOGICAL_OR;

        return $this;
    }

    /**
     * @return $this
     */
    public function _and()
    {
        $this->defaultCombineOperator = self::LOGICAL_AND;

        return $this;
    }

    // Fluid Conditions

    /**
     * Returns the current object if the condition is true,
     * or a PropelConditionalProxy instance otherwise.
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria|\Propel\Runtime\Util\PropelConditionalProxy
     */
    public function _if($cond)
    {
        $cond = (bool)$cond; // Intentionally not typing the param to allow for evaluation inside this function

        $this->conditionalProxy = new PropelConditionalProxy($this, $cond, $this->conditionalProxy);

        return $this->conditionalProxy->getCriteriaOrProxy();
    }

    /**
     * Returns a PropelConditionalProxy instance.
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria|\Propel\Runtime\Util\PropelConditionalProxy
     */
    public function _elseif($cond)
    {
        $cond = (bool)$cond; // Intentionally not typing the param to allow for evaluation inside this function

        if (!$this->conditionalProxy) {
            throw new LogicException(__METHOD__ . ' must be called after _if()');
        }

        return $this->conditionalProxy->_elseif($cond);
    }

    /**
     * Returns a PropelConditionalProxy instance.
     * Allows for conditional statements in a fluid interface.
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria|\Propel\Runtime\Util\PropelConditionalProxy
     */
    public function _else()
    {
        if (!$this->conditionalProxy) {
            throw new LogicException(__METHOD__ . ' must be called after _if()');
        }

        return $this->conditionalProxy->_else();
    }

    /**
     * Returns the current object
     * Allows for conditional statements in a fluid interface.
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria|\Propel\Runtime\Util\PropelConditionalProxy
     */
    public function _endif()
    {
        if (!$this->conditionalProxy) {
            throw new LogicException(__METHOD__ . ' must be called after _if()');
        }

        $this->conditionalProxy = $this->conditionalProxy->getParentProxy();

        if ($this->conditionalProxy) {
            return $this->conditionalProxy->getCriteriaOrProxy();
        }

        // reached last level
        return $this;
    }

    /**
     * Ensures deep cloning of attached objects
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->map as $key => $criterion) {
            $this->map[$key] = clone $criterion;
        }

        foreach ($this->joins as $key => $join) {
            $this->joins[$key] = clone $join;
        }

        if ($this->having !== null) {
            $this->having = clone $this->having;
        }
    }

    /**
     * @return bool
     */
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuoting;
    }

    /**
     * @param bool $identifierQuoting
     *
     * @return $this
     */
    public function setIdentifierQuoting(bool $identifierQuoting)
    {
        $this->identifierQuoting = $identifierQuoting;

        return $this;
    }
}
