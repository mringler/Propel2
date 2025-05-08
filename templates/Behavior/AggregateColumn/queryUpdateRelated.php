
/**
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 *
 * @return void
 */
protected function updateRelated<?=$relationName.$aggregateName?>s($con)
{
    if ($this-><?=$variableName?>s === null) {
        return;
    }

    foreach ($this-><?=$variableName?>s as $<?=$variableName?>) {
        $<?=$variableName?>-><?= $updateMethodName ?>($con);
    }
    $this-><?=$variableName?>s = null;
}
