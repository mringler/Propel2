
/**
 * Finds the related <?=$foreignTable->getPhpName()?> objects and keep them for later
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 *
 * @return void
 */
protected function findRelated<?=$relationName.$aggregateName?>s($con)
{
    $criteria = clone $this;
    if ($this->useAliasInSQL) {
        $alias = $this->getModelAlias();
        $criteria->removeAlias($alias);
    } else {
        $alias = '';
    }

    $this-><?=$variableName?>s = <?=$foreignQueryName?>::create()
        ->join<?=$refRelationName?>($alias)
        ->mergeWith($criteria)
        ->findObjects($con);
}
