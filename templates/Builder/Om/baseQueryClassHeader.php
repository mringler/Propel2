<?php
    /*
     * vars:
     *  - string $tableName
     *  - string $tableDesc
     *  - string $queryClass
     *  - string $modelClass
     *  - string $parentClass
     *  - string $entityNotFoundExceptionClass
     *  - string $unqualifiedClassName
     * 
     *  - bool $addTimestamp
     *  - string $propelVersion
     * 
     *  - Column[] $columns
     *  - string[] $relationNames (both ref and backref)
     *  - string[] $relatedTableQueryClassNames 
     */
?>

/**
 * Base class that represents a query for the `<?= $tableName ?>` table.
 *
<?php if ($tableDesc): ?>
 * <?= $tableDesc ?> 
 *
<?php endif; ?>
<?php if ($addTimestamp): ?>
 * This class was autogenerated by Propel <?= $propelVersion ?> on:
 *
 * <?= strftime('%c') ?> 
 *
<?php endif; ?>
<?php foreach($columns as $column):?>
 * @method     <?= $queryClass ?> orderBy<?= $column->getPhpName() ?>($order = Criteria::ASC) Order by the <?= $column->getName() ?> column
<?php endforeach;?>
 *
<?php foreach($columns as $column):?>
 * @method     <?= $queryClass ?> groupBy<?= $column->getPhpName() ?>() Group by the <?= $column->getName() ?> column
<?php endforeach;?>
 *
 * @method     <?= $queryClass ?> leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     <?= $queryClass ?> rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     <?= $queryClass ?> innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     <?= $queryClass ?> leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     <?= $queryClass ?> rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     <?= $queryClass ?> innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
<?php foreach($relationNames as $relationName):?>
 * @method     <?= $queryClass ?> leftJoin<?= $relationName ?>($relationAlias = null) Adds a LEFT JOIN clause to the query using the <?= $relationName ?> relation
 * @method     <?= $queryClass ?> rightJoin<?= $relationName ?>($relationAlias = null) Adds a RIGHT JOIN clause to the query using the <?= $relationName ?> relation
 * @method     <?= $queryClass ?> innerJoin<?= $relationName ?>($relationAlias = null) Adds a INNER JOIN clause to the query using the <?= $relationName ?> relation
 *
 * @method     <?= $queryClass ?> joinWith<?= $relationName ?>($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the <?= $relationName ?> relation
 *
 * @method     <?= $queryClass ?> leftJoinWith<?= $relationName ?>() Adds a LEFT JOIN clause and with to the query using the <?= $relationName ?> relation
 * @method     <?= $queryClass ?> rightJoinWith<?= $relationName ?>() Adds a RIGHT JOIN clause and with to the query using the <?= $relationName ?> relation
 * @method     <?= $queryClass ?> innerJoinWith<?= $relationName ?>() Adds a INNER JOIN clause and with to the query using the <?= $relationName ?> relation
 *
<?php endforeach; ?>
 * @method     <?= $modelClass ?>|null findOne(?ConnectionInterface $con = null) Return the first <?= $modelClass ?> matching the query
 * @method     <?= $modelClass ?> findOneOrCreate(?ConnectionInterface $con = null) Return the first <?= $modelClass ?> matching the query, or a new <?= $modelClass ?> object populated from the query conditions when no match is found
 *
<?php foreach($columns as $column):?>
 * @method     <?= $modelClass ?>|null findOneBy<?= $column->getPhpName() ?>(<?= $column->getPhpType() ?> $<?= $column->getName() ?>) Return the first <?= $modelClass ?> filtered by the <?= $column->getName() ?> column
<?php endforeach;?>
 *
 * @method     <?= $modelClass ?> requirePk($key, ?ConnectionInterface $con = null) Return the <?= $modelClass ?> by primary key and throws <?= $entityNotFoundExceptionClass ?> when not found
 * @method     <?= $modelClass ?> requireOne(?ConnectionInterface $con = null) Return the first <?= $modelClass ?> matching the query and throws <?= $entityNotFoundExceptionClass ?> when not found
 *
<?php foreach($columns as $column):?>
 * @method     <?= $modelClass ?> requireOneBy<?= $column->getPhpName() ?>(<?= $column->getPhpType() ?> $<?= $column->getName() ?>) Return the first <?= $modelClass ?> filtered by the <?= $column->getName() ?> column and throws <?= $entityNotFoundExceptionClass ?> when not found
<?php endforeach;?>
 *
 * @method     <?= $modelClass ?>[]|Collection find(?ConnectionInterface $con = null) Return <?= $modelClass ?> objects based on current ModelCriteria
 * @psalm-method Collection&\Traversable<<?= $modelClass ?>> find(?ConnectionInterface $con = null) Return <?= $modelClass ?> objects based on current ModelCriteria
 * @method     \Propel\Runtime\Collection\ObjectCollection<<?= $modelClass ?>> findObjects(?ConnectionInterface $con = null) Get <?= $modelClass ?> objects in ObjectCollection
 *
<?php foreach($columns as $column):?>
 * @method     <?= $modelClass ?>[]|Collection findBy<?= $column->getPhpName() ?>(<?= $column->getPhpType() ?>|array<<?= $column->getPhpType() ?>> $<?= $column->getName() ?>) Return <?= $modelClass ?> objects filtered by the <?= $column->getName() ?> column
 * @psalm-method Collection&\Traversable<<?= $modelClass ?>> findBy<?= $column->getPhpName() ?>(<?= $column->getPhpType() ?>|array<<?= $column->getPhpType() ?>> $<?= $column->getName() ?>) Return <?= $modelClass ?> objects filtered by the <?= $column->getName() ?> column
<?php endforeach;?>
 *
 * @method     <?= $modelClass ?>[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ?ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 * @psalm-method \Propel\Runtime\Util\PropelModelPager&\Traversable<<?= $modelClass ?>> paginate($page = 1, $maxPerPage = 10, ?ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 * @template ParentQuery of ModelCriteria|null = null
 * @extends <?= $parentClass ?><ParentQuery>
 */
abstract class <?= $unqualifiedClassName ?> extends <?= $parentClass ?> 
{
