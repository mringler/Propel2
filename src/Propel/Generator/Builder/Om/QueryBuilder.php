<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Builder\Util\PropelTemplate;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\ExistsQueryCriterion;

/**
 * Generates a base Query class for user object model (OM).
 *
 * This class produces the base query class (e.g. BaseBookQuery) which contains
 * all the custom-built query methods.
 *
 * @author Francois Zaninotto
 */
class QueryBuilder extends AbstractOMBuilder
{
    /**
     * Returns the package for the [base] object classes.
     *
     * @return string
     */
    #[\Override]
    public function getPackage(): string
    {
        return parent::getPackage() . '.Base';
    }

    /**
     * Returns the namespace for the query object classes.
     *
     * @return string|null
     */
    #[\Override]
    public function getNamespace(): ?string
    {
        $namespace = parent::getNamespace();

        return $namespace ? "$namespace\\Base" : 'Base';
    }

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getStubQueryBuilder()->getUnprefixedClassName();
    }

    /**
     * Returns parent class name that extends TableQuery Object if is set this class must extends ModelCriteria for be compatible
     *
     * @return string
     */
    public function getParentClass(): string
    {
        $parentClass = $this->getBehaviorContent('parentClass');
        if ($parentClass) {
            return $parentClass;
        }

        $baseQueryClass = $this->getTable()->getBaseQueryClass();
        if ($baseQueryClass) {
            return $baseQueryClass;
        }

        return 'TypedModelCriteria';
    }

    /**
     * Adds class phpdoc comment and opening of class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassOpen(string &$script): void
    {
        $table = $this->getTable();
        $collectionBuilder = $this->builderFactory->createObjectCollectionBuilder($this->getTable());

        $script .= $this->renderTemplate('baseQueryClassHeader.php', [
            'tableName' => $table->getName(),
            'tableDesc' => $table->getDescription(),
            'queryClass' => $this->getQueryClassName(),
            'modelClass' => $this->getObjectClassName(),
            'parentClass' => $this->getParentClass(),
            'entityNotFoundExceptionClass' => $this->getEntityNotFoundExceptionClass(),
            'unqualifiedClassName' => $this->getUnqualifiedClassName(),

            'addTimestamp' => $this->getBuildProperty('generator.objectModel.addTimeStamp'),
            'propelVersion' => $this->getBuildProperty('general.version'),

            'columns' => $table->getColumns(),

            'relationNames' => $this->getRelationNames(),
            'relatedTableQueryClassNames' => $this->getRelatedTableQueryClassNames(),

            'objectCollectionType' => $collectionBuilder->resolveTableCollectionClassType(),
        ]);
    }

    /**
     * Get names of all foreign key relations to and from this table.
     *
     * @return array<string>
     */
    protected function getRelationNames(): array
    {
        $table = $this->getTable();
        $fkRelationNames = array_map([$this, 'getFKPhpNameAffix'], $table->getForeignKeys());
        $refFkRelationNames = array_filter(array_map([$this, 'getRefFKPhpNameAffix'], $table->getReferrers()));

        return array_merge($fkRelationNames, $refFkRelationNames);
    }

    /**
     * Get query class names of all tables connected to this table with a foreign key relation.
     *
     * @return array<string>
     */
    protected function getRelatedTableQueryClassNames(): array
    {
        $table = $this->getTable();
        $fkTables = array_map(fn ($fk) => $fk->getForeignTable(), $table->getForeignKeys());
        $refFkTables = array_map(fn ($fk) => $fk->getTable(), $table->getReferrers());
        $relationTables = array_merge($fkTables, $refFkTables);

        return array_map(fn ($table) => $this->getNewStubQueryBuilder($table)->getQueryClassName(true), $relationTables);
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this method
     * if you want to change that behavior.
     *
     * @see ObjectBuilder::addClassBody()
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassBody(string &$script): void
    {
        $table = $this->getTable();

        // namespaces
        $this->declareClasses(
            '\Propel\Runtime\Propel',
            '\Propel\Runtime\ActiveQuery\ModelCriteria',
            '\Propel\Runtime\ActiveQuery\TypedModelCriteria',
            '\Propel\Runtime\ActiveQuery\Criteria',
            '\Propel\Runtime\ActiveQuery\FilerExpression\FilterFactory',
            '\Propel\Runtime\ActiveQuery\ModelJoin',
            '\Exception',
            '\Propel\Runtime\Exception\PropelException',
        );
        $this->declareClassFromBuilder($this->getStubQueryBuilder(), 'Child');
        $this->declareClassFromBuilder($this->getTableMapBuilder());
        $additionalModelClasses = $table->getAdditionalModelClassImports();
        if ($additionalModelClasses) {
            $this->declareClasses(...$additionalModelClasses);
        }

        // apply behaviors
        $this->applyBehaviorModifier('queryAttributes', $script, '    ');
        $this->addEntityNotFoundExceptionClass($script);
        $this->addConstructor($script);
        $this->addFactory($script);
        $this->addFindPk($script);
        $this->addFindPkSimple($script);
        $this->addFindPkComplex($script);
        $this->addFindPks($script);
        $this->addFilterByPrimaryKey($script);
        $this->addFilterByPrimaryKeys($script);
        foreach ($this->getTable()->getColumns() as $col) {
            $this->addFilterByCol($script, $col);
            if ($col->isNamePlural()) {
                if ($col->getType() === PropelTypes::PHP_ARRAY) {
                    $this->addFilterByArrayCol($script, $col);
                } elseif ($col->isSetType()) {
                    $this->addFilterBySetCol($script, $col);
                }
            }
        }
        foreach ($this->getTable()->getForeignKeys() as $fk) {
            $this->addFilterByFK($script, $fk);
            $this->addJoinFk($script, $fk);
            $this->addUseFKQuery($script, $fk);
        }
        foreach ($this->getTable()->getReferrers() as $refFK) {
            $this->addFilterByRefFK($script, $refFK);
            $this->addJoinRefFk($script, $refFK);
            $this->addUseRefFKQuery($script, $refFK);
        }
        foreach ($this->getTable()->getCrossRelations() as $crossFKs) {
            $this->addFilterByCrossFK($script, $crossFKs);
        }
        $this->addPrune($script);
        $this->addBasePreSelect($script);
        $this->addBasePreDelete($script);
        $this->addBasePostDelete($script);
        $this->addBasePreUpdate($script);
        $this->addBasePostUpdate($script);

        // add the insert, update, delete, etc. methods
        if (!$table->isAlias() && !$table->isReadOnly()) {
            $this->addDeleteMethods($script);
        }

        // apply behaviors
        $this->applyBehaviorModifier('staticConstants', $script, '    ');
        $this->applyBehaviorModifier('staticAttributes', $script, '    ');
        $this->applyBehaviorModifier('staticMethods', $script, '    ');
        $this->applyBehaviorModifier('queryMethods', $script, '    ');
    }

    /**
     * Adds the entityNotFoundExceptionClass property which is necessary for the `requireOne` method
     * of the `ModelCriteria`
     *
     * @param string $script
     *
     * @return void
     */
    protected function addEntityNotFoundExceptionClass(string &$script): void
    {
        $script .= "    protected \$entityNotFoundExceptionClass = '" . addslashes($this->getEntityNotFoundExceptionClass()) . "';\n";
    }

    /**
     * @return string|null
     */
    private function getEntityNotFoundExceptionClass(): ?string
    {
        return $this->getBuildProperty('generator.objectModel.entityNotFoundExceptionClass');
    }

    /**
     * Adds the doDeleteAll(), etc. methods.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDeleteMethods(string &$script): void
    {
        $this->addDoDeleteAll($script);
        $this->addDelete($script);

        if ($this->isDeleteCascadeEmulationNeeded()) {
            $this->addDoOnDeleteCascade($script);
        }

        if ($this->isDeleteSetNullEmulationNeeded()) {
            $this->addDoOnDeleteSetNull($script);
        }
    }

    /**
     * Whether the platform in use requires ON DELETE CASCADE emulation and whether there are references to this table.
     *
     * @return bool
     */
    protected function isDeleteCascadeEmulationNeeded(): bool
    {
        $table = $this->getTable();
        if ((!$this->getPlatform()->supportsNativeDeleteTrigger() || $this->getBuildProperty('generator.objectModel.emulateForeignKeyConstraints')) && count($table->getReferrers()) > 0) {
            foreach ($table->getReferrers() as $fk) {
                if ($fk->getOnDelete() === ForeignKey::CASCADE) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the platform in use requires ON DELETE SETNULL emulation and whether there are references to this table.
     *
     * @return bool
     */
    protected function isDeleteSetNullEmulationNeeded(): bool
    {
        $table = $this->getTable();
        if ((!$this->getPlatform()->supportsNativeDeleteTrigger() || $this->getBuildProperty('generator.objectModel.emulateForeignKeyConstraints')) && count($table->getReferrers()) > 0) {
            foreach ($table->getReferrers() as $fk) {
                if ($fk->getOnDelete() === ForeignKey::SETNULL) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Closes class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassClose(string &$script): void
    {
        $script .= "
}
";
        $this->applyBehaviorModifier('queryFilter', $script, '');
    }

    /**
     * Adds the constructor for this object.
     *
     * @see addConstructor()
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructor(string &$script): void
    {
        $this->addConstructorComment($script);
        $this->addConstructorOpen($script);
        $this->addConstructorBody($script);
        $this->addConstructorClose($script);
    }

    /**
     * Adds the comment for the constructor
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructorComment(string &$script): void
    {
        $script .= "
    /**
     * Initializes internal state of " . $this->getClassName() . " object.
     *
     * @param string \$dbName The database name
     * @param string \$modelName The phpName of a model, e.g. 'Book'
     * @param string \$modelAlias The alias for the model in this query, e.g. 'b'
     */";
    }

    /**
     * Adds the function declaration for the constructor
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructorOpen(string &$script): void
    {
        $table = $this->getTable();
        $script .= "
    public function __construct(\$dbName = '" . $table->getDatabase()->getName() . "', \$modelName = '" . addslashes($this->getNewStubObjectBuilder($table)->getFullyQualifiedClassName()) . "', \$modelAlias = null)
    {";
    }

    /**
     * Adds the function body for the constructor
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructorBody(string &$script): void
    {
        $script .= "
        parent::__construct(\$dbName, \$modelName, \$modelAlias);";
    }

    /**
     * Adds the function close for the constructor
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructorClose(string &$script): void
    {
        $script .= "
    }
";
    }

    /**
     * Adds the factory for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactory(string &$script): void
    {
        $this->addFactoryComment($script);
        $this->addFactoryOpen($script);
        $this->addFactoryBody($script);
        $this->addFactoryClose($script);
    }

    /**
     * Adds the comment for the factory
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactoryComment(string &$script): void
    {
        $classname = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($this->getTable()));
        $script .= "
    /**
     * Returns a new " . $classname . " object.
     *
     * @param string \$modelAlias The alias of a model in the query
     * @param \Propel\Runtime\ActiveQuery\Criteria \$criteria Optional Criteria to build the query from
     *
     * @return " . $classname . "<null>
     */";
    }

    /**
     * Adds the function declaration for the factory
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactoryOpen(string &$script): void
    {
        $script .= "
    public static function create(?string \$modelAlias = null, ?Criteria \$criteria = null): Criteria
    {";
    }

    /**
     * Adds the function body for the factory
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactoryBody(string &$script): void
    {
        $classname = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($this->getTable()));
        $script .= "
        if (\$criteria instanceof " . $classname . ") {
            return \$criteria;
        }
        \$query = new " . $classname . "();
        if (\$modelAlias !== null) {
            \$query->setModelAlias(\$modelAlias);
        }
        if (\$criteria instanceof Criteria) {
            \$query->mergeWith(\$criteria);
        }

        return \$query;";
    }

    /**
     * Adds the function close for the factory
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactoryClose(string &$script): void
    {
        $script .= "
    }
";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addFindPk(string &$script): void
    {
        $class = $this->getObjectClassName();
        $tableMapClassName = $this->getTableMapClassName();
        $table = $this->getTable();

        if (!$table->hasCompositePrimaryKey()) {
            $pkType = 'mixed';
            $codeExample = '$obj  = $c->findPk(12, $con);';
        } else {
            $colNames = array_map(fn (Column $col) => '$' . $col->getName(), $table->getPrimaryKey());
            $pkType = 'array[' . implode(', ', $colNames) . ']';
            $randomPkValues = array_slice([12, 34, 56, 78, 91], 0, count($colNames));

            $pkCsv = implode(', ', $randomPkValues);
            $codeExample = "\$obj = \$c->findPk(array($pkCsv), \$con);";
        }

        $buildPoolKeyStatement = $this->getBuildPoolKeyStatement($table->getPrimaryKey());

        $script .= "
    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $codeExample
     * </code>
     *
     * @param $pkType \$key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con an optional connection object
     *
     * @return $class|array|mixed the result, formatted by the current formatter
     */
    public function findPk(\$key, ?ConnectionInterface \$con = null)
    {";

        if (!$table->hasPrimaryKey()) {
            $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
            $script .= "
        throw new LogicException('The {$this->getObjectName()} object has no primary key');
    }
";

            return;
        }

        $script .= "
        if (\$key === null) {
            return null;
        }

        if (\$con === null) {
            \$con = Propel::getServiceContainer()->getReadConnection({$this->getTableMapClass()}::DATABASE_NAME);
        }

        \$this->basePreSelect(\$con);

        if (!\$this->isEmpty()) {
            return \$this->findPkComplex(\$key, \$con);
        }

        \$poolKey = $buildPoolKeyStatement;
        \$obj = {$tableMapClassName}::getInstanceFromPool(\$poolKey);
        if (\$obj !== null) {
            return \$obj;
        }

        return \$this->findPkSimple(\$key, \$con);
    }
";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addFindPkSimple(string &$script): void
    {
        $table = $this->getTable();

        // this method is not needed if the table has no primary key
        if (!$table->hasPrimaryKey()) {
            return;
        }

        $usesConcreteInheritance = $table->usesConcreteInheritance();
        if ($table->isAbstract() && !$usesConcreteInheritance) {
            $tableName = $table->getPhpName();
            $script .= "
    protected function findPkSimple(\$key, ConnectionInterface \$con)
    {
        throw new PropelException('$tableName is declared abstract, you cannot query it.');
    }
";

            return;
        }

        $tableMapClassName = $this->getTableMapClassName();
        $ARClassName = $this->getObjectClassName();
        $this->declareClassFromBuilder($this->getStubObjectBuilder());
        $this->declareClasses('\PDO');

        $isBulkLoad = $table->isBulkLoadTable();
        $query = $this->buildSimpleSqlSelectStatement($table, !$isBulkLoad);
        $buildPoolKeyStatement = $this->getBuildPoolKeyStatement($table->getPrimaryKey(), $isBulkLoad ? '$pk' : '$key');
        $bindValueStatements = $isBulkLoad ? '' : $this->buildPrimaryKeyColumnBindingStatements($table);
        $ifWhile = $isBulkLoad ? 'while' : 'if';

            $script .= "
    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param mixed \$key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con A connection object
     *
     * @throws \\Propel\\Runtime\\Exception\\PropelException
     *
     * @return $ARClassName A model object, or null if the key is not found
     */
    protected function findPkSimple(\$key, ConnectionInterface \$con)
    {
        \$sql = '$query';
        try {
            \$stmt = \$con->prepare(\$sql);$bindValueStatements
            \$stmt->execute();
        } catch (Exception \$e) {
            Propel::log(\$e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', \$sql), 0, \$e);
        }
        \$obj = null;
        $ifWhile (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {";

        if (!$usesConcreteInheritance) {
            $classNameLiteral = $ARClassName;
        } else {
            $classNameLiteral = '$cls';

            $script .= "
            {$classNameLiteral} = {$tableMapClassName}::getOMClass(\$row, 0, false);";
        }

        $script .= "
            /** @var $ARClassName \$obj */
            \$obj = new $classNameLiteral();
            \$obj->hydrate(\$row);";

        if ($isBulkLoad) {
            $script .= "
            \$pk = \$obj->getPrimaryKey();";
        }

        $script .= "
            {$tableMapClassName}::addInstanceToPool(\$obj, $buildPoolKeyStatement);
        }
        \$stmt->closeCursor();";

        if ($isBulkLoad) {
            $buildPoolKeyStatementFromKey = $this->getBuildPoolKeyStatement($table->getPrimaryKey());

            $script .= "
        \$poolKey = $buildPoolKeyStatementFromKey;

        return {$tableMapClassName}::getInstanceFromPool(\$poolKey);
    }
";
        } else {
            $script .= "

        return \$obj;
    }
";
        }
    }

    /**
     * Create select SQL statement for the given table with binding statements
     * for the primary key.
     *
     * @param \Propel\Generator\Model\Table $table
     * @param bool $withBinding If false, pk column bindings are omitted
     *
     * @return string
     */
    protected function buildSimpleSqlSelectStatement(Table $table, bool $withBinding = true): string
    {
        $selectColumns = array_filter(array_map(fn (Column $col) => $col->isLazyLoad() ? null : $col->getName(), $table->getColumns()));
        $selectColumnsCSV = implode(', ', array_map([$this, 'quoteIdentifier'], $selectColumns));

        if (!$withBinding) {
            return sprintf('SELECT %s FROM %s', $selectColumnsCSV, $this->quoteIdentifier($table->getName()));
        }

        $conditions = [];
        foreach ($table->getPrimaryKey() as $index => $column) {
            $quotedColumnName = $this->quoteIdentifier($column->getName());
            $conditions[] = sprintf('%s = :p%d', $quotedColumnName, $index);
        }

        return sprintf(
            'SELECT %s FROM %s WHERE %s',
            $selectColumnsCSV,
            $this->quoteIdentifier($table->getName()),
            implode(' AND ', $conditions),
        );
    }

    /**
     * Build PHP column binding statements for the primary key of a table (i.e. "$stmt->bindValue(':p0', $key, PDO::PARAM_INT);".
     *
     * @param \Propel\Generator\Model\Table $table
     * @param string $keyVariableLiteral
     *
     * @return string
     */
    protected function buildPrimaryKeyColumnBindingStatements(Table $table, string $keyVariableLiteral = '$key'): string
    {
        $platform = $this->getPlatform();
        $columns = (array)$table->getPrimaryKey();
        $tab = '            ';

        if (!$table->hasCompositePrimaryKey()) {
            return $platform->getColumnBindingPHP($columns[0], "':p0'", $keyVariableLiteral, $tab);
        }

        $statements = '';
        foreach ((array)$table->getPrimaryKey() as $index => $column) {
            $accessorExpression = "{$keyVariableLiteral}[$index]"; // i.e. "$key[2]"
            $statements .= $platform->getColumnBindingPHP($column, "':p$index'", $accessorExpression, $tab);
        }

        return $statements;
    }

    /**
     * Build PHP statement that creates a hash key from column values.
     *
     * @param array<\Propel\Generator\Model\Column> $pkColumns Columns used to build hash.
     * @param string $varLiteral The literal for the variable holding the key in the script.
     *
     * @return string
     */
    protected function getBuildPoolKeyStatement(array $pkColumns, string $varLiteral = '$key'): string
    {
        $numberOfPks = count($pkColumns);
        if ($numberOfPks === 1) {
            return $this->getTableMapBuilder()->getInstancePoolKeySnippet($varLiteral);
        }
        $pkIndexes = range(0, $numberOfPks - 1);
        $pkVariableLiteral = preg_filter('/(\d+)/', $varLiteral . '[${1}]', $pkIndexes); // put ids into "$key[]"

        return $this->getTableMapBuilder()->getInstancePoolKeySnippet($pkVariableLiteral);
    }

    /**
     * Adds the findPk method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFindPkComplex(string &$script): void
    {
        $class = $this->getObjectClassName();
        $table = $this->getTable();

        // this method is not needed if the table has no primary key
        if (!$table->hasPrimaryKey()) {
            return;
        }

        $this->declareClasses('\Propel\Runtime\Connection\ConnectionInterface');
        $script .= "
    /**
     * Find object by primary key.
     *
     * @param mixed \$key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con A connection object
     *
     * @return " . $class . "|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex(\$key, ConnectionInterface \$con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
        \$dataFetcher = \$criteria
            ->filterByPrimaryKey(\$key)
            ->doSelect(\$con);

        return \$criteria->getFormatter()->init(\$criteria)->formatOne(\$dataFetcher);
    }
";
    }

    /**
     * Adds the findPks method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFindPks(string &$script): void
    {
        $this->declareClasses(
            '\Propel\Runtime\Collection\Collection',
            '\Propel\Runtime\Connection\ConnectionInterface',
            '\Propel\Runtime\Propel',
        );
        $table = $this->getTable();
        $pks = $table->getPrimaryKey();
        $count = count($pks);
        $script .= "
    /**
     * Find objects by primary key
     * <code>";
        if ($count === 1) {
            $script .= "
     * \$objs = \$c->findPks(array(12, 56, 832), \$con);";
        } else {
            $script .= "
     * \$objs = \$c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), \$con);";
        }
        $script .= "
     * </code>
     * @param array \$keys Primary keys to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con an optional connection object
     *
     * @return Collection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks(\$keys, ?ConnectionInterface \$con = null)
    {";
        if (!$table->hasPrimaryKey()) {
            $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
            $script .= "
        throw new LogicException('The {$this->getObjectName()} object has no primary key');
    }
";

            return;
        }

        $script .= "
        if (null === \$con) {
            \$con = Propel::getServiceContainer()->getReadConnection(\$this->getDbName());
        }
        \$this->basePreSelect(\$con);
        \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
        \$dataFetcher = \$criteria
            ->filterByPrimaryKeys(\$keys)
            ->doSelect(\$con);

        return \$criteria->getFormatter()->init(\$criteria)->format(\$dataFetcher);
    }
";
    }

    /**
     * Adds the filterByPrimaryKey method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFilterByPrimaryKey(string &$script): void
    {
        $script .= "
    /**
     * Filter the query by primary key
     *
     * @param mixed \$key Primary key to use for the query
     *
     * @return \$this
     */
    public function filterByPrimaryKey(\$key)
    {";
        $table = $this->getTable();

        if (!$table->hasPrimaryKey()) {
            $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
            $script .= "
        throw new LogicException('The {$this->getObjectName()} object has no primary key');
    }
";

            return;
        }

        $tableMapClassName = $this->getTableMapClassName();
        $pks = $table->getPrimaryKey();
        if (count($pks) === 1) {
            // simple primary key
            $col = $pks[0];
            $colName = $col->getName();
            $script .= "
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');
        \$this->addUsingOperator(\$resolvedColumn, \$key, Criteria::EQUAL);

        return \$this;";
        } else {
            $script .= "
        \$tableMap = $tableMapClassName::getTableMap();";
            // composite primary key
            $i = 0;
            foreach ($pks as $col) {
                $const = $this->getColumnConstant($col);
                $colName = $col->getName();
                $script .= "
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');
        \$this->addUsingOperator(\$resolvedColumn, \$key[$i], Criteria::EQUAL);";
                $i++;
            }
            $script .= "

        return \$this;";
        }
        $script .= "
    }
";
    }

    /**
     * Adds the filterByPrimaryKey method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFilterByPrimaryKeys(string &$script): void
    {
        $script .= "
    /**
     * Filter the query by a list of primary keys
     *
     * @param array|int \$keys The list of primary key to use for the query
     *
     * @return \$this
     */
    public function filterByPrimaryKeys(\$keys)
    {";
        $table = $this->getTable();

        if (!$table->hasPrimaryKey()) {
            $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
            $script .= "
        throw new LogicException('The {$this->getObjectName()} object has no primary key');
    }
";

            return;
        }

        $tableMapClassName = $this->getTableMapClassName();
        $pks = $table->getPrimaryKey();
        if (count($pks) === 1) {
            // simple primary key
            $col = $pks[0];
            $colName = $col->getName();
            $const = $this->getColumnConstant($col);
            $script .= "
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');
        \$this->addUsingOperator(\$resolvedColumn, \$keys, Criteria::IN);

        return \$this;";
        } else {
            // composite primary key
            $script .= "
        if (empty(\$keys)) {
            \$this->addFilter(null, '1<>1', Criteria::CUSTOM);

            return \$this;
        }
        foreach (\$keys as \$key) {";
            $i = 0;
            foreach ($pks as $i => $col) {
                $colName = $col->getName();
                $addOp = ($i === 0) ? '$this->addOr($filter0);' : "\$filter0->addAnd(\$filter$i);";
                ($i > 0) && $script .= "\n";
                $script .= "
            \$resolvedColumn$i = \$this->resolveLocalColumnByName('$colName');
            \$filter$i = \$this->buildFilter(\$resolvedColumn$i, \$key[$i], Criteria::EQUAL);
            {$addOp}";
            }
            $script .= "
        }

        return \$this;";
        }
        $script .= "
    }
";
    }

    /**
     * Adds the filterByCol method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterByCol(string &$script, Column $col): void
    {
        $colPhpName = $col->getPhpName();
        $colName = $col->getName();
        $variableName = $col->getCamelCaseName();
        $colName = $col->getName();
        $tableMapClassName = $this->getTableMapClassName();
        $script .= "
    /**
     * Filter the query on the $colName column
     *";
        if ($col->isNumericType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName(1234); // WHERE $colName = 1234
     * \$query->filterBy$colPhpName(array(12, 34)); // WHERE $colName IN (12, 34)
     * \$query->filterBy$colPhpName(array('min' => 12)); // WHERE $colName > 12
     * </code>";
            if ($col->isForeignKey()) {
                foreach ($col->getForeignKeys() as $fk) {
                    $script .= "
     *
     * @see       filterBy" . $this->getFKPhpNameAffix($fk) . '()';
                }
            }
            $script .= "
     *
     * @param mixed \$$variableName The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => \$minValue, 'max' => \$maxValue) for intervals.";
        } elseif ($col->isTemporalType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName('2011-03-14'); // WHERE $colName = '2011-03-14'
     * \$query->filterBy$colPhpName('now'); // WHERE $colName = '2011-03-14'
     * \$query->filterBy$colPhpName(array('max' => 'yesterday')); // WHERE $colName > '2011-03-13'
     * </code>
     *
     * @param mixed \$$variableName The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => \$minValue, 'max' => \$maxValue) for intervals.";
        } elseif ($col->getType() == PropelTypes::PHP_ARRAY) {
            $script .= "
     * @param array \$$variableName The values to use as filter.";
        } elseif ($col->isTextType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName('fooValue');   // WHERE $colName = 'fooValue'
     * \$query->filterBy$colPhpName('%fooValue%', Criteria::LIKE); // WHERE $colName LIKE '%fooValue%'
     * \$query->filterBy$colPhpName(['foo', 'bar']); // WHERE $colName IN ('foo', 'bar')
     * </code>
     *
     * @param string|string[] \$$variableName The value to use as filter.";
        } elseif ($col->isBooleanType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName(true); // WHERE $colName = true
     * \$query->filterBy$colPhpName('yes'); // WHERE $colName = true
     * </code>
     *
     * @param bool|string \$$variableName The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').";
        } else {
            $script .= "
     * @param mixed \$$variableName The value to use as filter";
        }
        $script .= "
     * @param string|null \$comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return \$this
     */
    public function filterBy$colPhpName(\$$variableName = null, ?string \$comparison = null)
    {
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');";

        if ($col->isNumericType() || $col->isTemporalType()) {
            $script .= "
        if (is_array(\$$variableName)) {
            \$useMinMax = false;
            if (isset(\${$variableName}['min'])) {
                \$this->addUsingOperator(\$resolvedColumn, \${$variableName}['min'], Criteria::GREATER_EQUAL);
                \$useMinMax = true;
            }
            if (isset(\${$variableName}['max'])) {
                \$this->addUsingOperator(\$resolvedColumn, \${$variableName}['max'], Criteria::LESS_EQUAL);
                \$useMinMax = true;
            }
            if (\$useMinMax) {
                return \$this;
            }
            if (\$comparison === null) {
                \$comparison = Criteria::IN;
            }
        }";
        } elseif ($col->getType() == PropelTypes::OBJECT) {
            $script .= "
        if (is_object(\$$variableName)) {
            \$$variableName = serialize(\$$variableName);
        }";
        } elseif ($col->getType() == PropelTypes::PHP_ARRAY) {
            $script .= "
        if ( \$comparison === null 
            || \$comparison === Criteria::CONTAINS_ALL 
            || \$comparison === Criteria::CONTAINS_SOME 
            || \$comparison === Criteria::CONTAINS_NONE
        ) {
            \$andOr = (\$comparison === Criteria::CONTAINS_SOME) ? Criteria::LOGICAL_OR : Criteria::LOGICAL_AND;
            \$operator = (\$comparison === Criteria::CONTAINS_NONE) ? Criteria::NOT_LIKE : Criteria::LIKE;
            foreach (\$$variableName as \$value) {
                \$this->addFilterWithConjunction(\$andOr, \$resolvedColumn, \"%| \$value |%\", \$operator);
            }
            if (\$comparison == Criteria::CONTAINS_NONE) {
                \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);
            }

            return \$this;
        }";
        } elseif ($col->isSetType()) { // TODO
            $this->declareClasses(
                'Propel\Common\Util\SetColumnConverter',
                'Propel\Common\Exception\SetColumnConverterException',
            );
            $script .= "
        \$valueSet = $tableMapClassName::getValueSet(" . $this->getColumnConstant($col) . ");
        try {
            \${$variableName} = SetColumnConverter::convertToInt(\${$variableName}, \$valueSet);
        } catch (SetColumnConverterException \$e) {
            throw new PropelException(sprintf('Value \"%s\" is not accepted in this set column', \$e->getValue()), \$e->getCode(), \$e);
        }
        if (\$comparison === null || \$comparison == Criteria::CONTAINS_ALL) {
            if (\${$variableName} === 0) {
                return \$this;
            }
            \$comparison = Criteria::BINARY_ALL;
        } elseif (\$comparison == Criteria::CONTAINS_SOME || \$comparison == Criteria::IN) {
            if (\${$variableName} === 0) {
                return \$this;
            }
            \$comparison = Criteria::BINARY_AND;
        } elseif (\$comparison == Criteria::CONTAINS_NONE) {
            \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');
            if (\${$variableName} !== 0) {
                \$this->addFilter(\$resolvedColumn, \${$variableName}, Criteria::BINARY_NONE);
            }
            \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);

            return \$this;
        }";
        } elseif ($col->getType() == PropelTypes::ENUM) {
            $script .= "
        \$valueSet = " . $this->getTableMapClassName() . '::getValueSet(' . $this->getColumnConstant($col) . ");
        if (is_scalar(\$$variableName)) {
            if (!in_array(\$$variableName, \$valueSet)) {
                throw new PropelException(sprintf('Value \"%s\" is not accepted in this enumerated column', \$$variableName));
            }
            \$$variableName = array_search(\$$variableName, \$valueSet);
        } elseif (is_array(\$$variableName)) {
            \$convertedValues = [];
            foreach (\$$variableName as \$value) {
                if (!in_array(\$value, \$valueSet)) {
                    throw new PropelException(sprintf('Value \"%s\" is not accepted in this enumerated column', \$value));
                }
                \$convertedValues [] = array_search(\$value, \$valueSet);
            }
            \$$variableName = \$convertedValues;
            if (\$comparison === null) {
                \$comparison = Criteria::IN;
            }
        }";
        } elseif ($col->isTextType()) {
            $script .= "
        if (\$comparison === null && is_array(\$$variableName)) {
            \$comparison = Criteria::IN;
        }";
        } elseif ($col->isBooleanType()) {
            $script .= "
        if (is_string(\$$variableName)) {
            \$$variableName = in_array(strtolower(\$$variableName), array('false', 'off', '-', 'no', 'n', '0', ''), true) ? false : true;
        }";
        } elseif ($col->isUuidBinaryType()) {
            $uuidSwapFlag = $this->getUuidSwapFlagLiteral();
            $script .= "
        \$$variableName = UuidConverter::uuidToBinRecursive(\$$variableName, $uuidSwapFlag);";
        }
        $script .= "
        \$this->addUsingOperator(\$resolvedColumn, \$$variableName, \$comparison);

        return \$this;
    }
";
    }

    /**
     * Adds the singular filterByCol method for an Array column.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterByArrayCol(string &$script, Column $col): void
    {
        $singularPhpName = $col->getPhpSingularName();
        $colName = $col->getName();
        $variableName = $col->getCamelCaseName();
        $script .= "
    /**
     * Filter the query on the $colName column
     * @param mixed \$$variableName The value to use as filter
     * @param string|null \$comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return \$this
     */
    public function filterBy$singularPhpName(\$$variableName = null, ?string \$comparison = null)
    {
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');
        if (\$comparison == Criteria::CONTAINS_NONE) {
            \$$variableName = '%| ' . \$$variableName . ' |%';
            \$comparison = Criteria::NOT_LIKE;
            \$this->addAnd(\$resolvedColumn, \$$variableName, \$comparison);
            \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);

            return \$this;
        }

        if ((\$comparison === null || \$comparison == Criteria::CONTAINS_ALL) && is_scalar(\$$variableName)) {
            \$$variableName = '%| ' . \$$variableName . ' |%';
            \$comparison = Criteria::LIKE;
        }
        \$this->addUsingOperator(\$resolvedColumn, \$$variableName, \$comparison);

        return \$this;
    }
";
    }

    /**
     * Adds the singular filterByCol method for an Array column.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterBySetCol(string &$script, Column $col): void
    {
        $colPhpName = $col->getPhpName();
        $singularPhpName = $col->getPhpSingularName();
        $colName = $col->getName();
        $variableName = $col->getCamelCaseName();
        $script .= "
    /**
     * Filter the query on the $colName column
     * @param mixed \$$variableName The value to use as filter
     * @param string \$comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return \$this
     */
    public function filterBy$singularPhpName(\$$variableName = null, ?string \$comparison = null)
    {
        \$this->filterBy$colPhpName(\$$variableName, \$comparison);

        return \$this;
    }
";
    }

    /**
     * Adds the filterByFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addFilterByFk(string &$script, ForeignKey $fk): void
    {
        $this->declareClasses(
            '\Propel\Runtime\Collection\ObjectCollection',
            '\Propel\Runtime\Exception\PropelException',
        );
        $fkTable = $fk->getForeignTable();
        $fkStubObjectBuilder = $this->getNewObjectBuilder($fkTable);
        $this->declareClassFromBuilder($fkStubObjectBuilder);
        $fkPhpName = $this->getClassNameFromBuilder($fkStubObjectBuilder, true);
        $relationName = $this->getFKPhpNameAffix($fk);
        $objectName = '$' . $fkTable->getCamelCaseName();
        $script .= "
    /**
     * Filter the query by a related $fkPhpName object
     *";
        if ($fk->isComposite()) {
            $script .= "
     * @param $fkPhpName $objectName The related object to use as filter";
        } else {
            $script .= "
     * @param $fkPhpName|ObjectCollection $objectName The related object(s) to use as filter";
        }
        $script .= "
     * @param string|null \$comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \\Propel\\Runtime\\Exception\\PropelException
     *
     * @return \$this
     */
    public function filterBy$relationName($objectName, ?string \$comparison = null)
    {
        if ($objectName instanceof $fkPhpName) {
            return \$this";

        foreach ($fk->getMapping() as $mapping) {
            [$localColumn, $rightValueOrColumn] = $mapping;
            $columnName = $localColumn->getName();
            $value = ($rightValueOrColumn instanceof Column)
                ? "{$objectName}->get" . $rightValueOrColumn->getPhpName() . '()'
                : var_export($rightValueOrColumn, true);
                $script .= "
                ->addUsingOperator(\$this->resolveLocalColumnByName('$columnName'), $value, \$comparison)";
        }

        $script .= ';';
        if (!$fk->isComposite()) {
            $columnName = $fk->getLocalColumn()->getName();
            $foreignColumnName = $fk->getForeignColumn()->getPhpName();
            $keyColumn = $fk->getForeignTable()->hasCompositePrimaryKey() ? $foreignColumnName : 'PrimaryKey';
            $script .= "
        } elseif ($objectName instanceof ObjectCollection) {
            if (\$comparison === null) {
                \$comparison = Criteria::IN;
            }

            \$this
                ->addUsingOperator(\$this->resolveLocalColumnByName('$columnName'), {$objectName}->toKeyValue('$keyColumn', '$foreignColumnName'), \$comparison);

            return \$this;";
        }
        $script .= "
        } else {";
        if ($fk->isComposite()) {
            $script .= "
            throw new PropelException('filterBy$relationName() only accepts arguments of type $fkPhpName');";
        } else {
            $script .= "
            throw new PropelException('filterBy$relationName() only accepts arguments of type $fkPhpName or Collection');";
        }
        $script .= "
        }
    }
";
    }

    /**
     * Adds the filterByRefFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addFilterByRefFk(string &$script, ForeignKey $fk): void
    {
        $this->declareClasses(
            '\Propel\Runtime\Collection\ObjectCollection',
            '\Propel\Runtime\Exception\PropelException',
        );
        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $fkStubObjectBuilder = $this->getNewObjectBuilder($fkTable);
        $targetClassName = $this->declareClassFromBuilder($fkStubObjectBuilder);
        $targetClassNameFq = $this->getClassNameFromBuilder($fkStubObjectBuilder, true);
        $relationName = $fk->getIdentifierReversed();
        $objectName = '$' . $fkTable->getCamelCaseName();
        $script .= "
    /**
     * Filter the query by a related $relationName object
     *
     * @param $targetClassNameFq|\Propel\Runtime\Collection\ObjectCollection<$targetClassNameFq> $objectName the related object to use as filter
     * @param string|null \$comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return \$this
     */
    public function filterBy$relationName($targetClassName|ObjectCollection $objectName, ?string \$comparison = null)
    {
        if ($objectName instanceof $targetClassNameFq) {
            \$this";
        foreach ($fk->getInverseMapping() as $mapping) {
            /** @var \Propel\Generator\Model\Column $foreignColumn */
            [$localValueOrColumn, $foreignColumn] = $mapping;
            $rightValue = "{$objectName}->get" . $foreignColumn->getPhpName() . '()';

            if ($localValueOrColumn instanceof Column) {
                $columnName = $localValueOrColumn->getName();
                $script .= "
                ->addUsingOperator(\$this->resolveLocalColumnByName('$columnName'), $rightValue, \$comparison)";
            } else {
                $leftValue = var_export($localValueOrColumn, true);
                $bindingType = $foreignColumn->getPDOType();
                $script .= "
                ->where(\"$leftValue = ?\", $rightValue, $bindingType)";
            }
        }
        $script .= ';';
        if (!$fk->isComposite()) {
            $script .= "
        } elseif ($objectName instanceof ObjectCollection) {
            \$this
                ->use{$relationName}Query()
                ->filterByPrimaryKeys({$objectName}->getPrimaryKeys())
                ->endUse();";
        }
        $script .= "
        } else {";
        if ($fk->isComposite()) {
            $script .= "
            throw new PropelException('filterBy$relationName() only accepts arguments of type $targetClassNameFq');";
        } else {
            $script .= "
            throw new PropelException('filterBy$relationName() only accepts arguments of type $targetClassNameFq or Collection');";
        }
        $script .= "
        }

        return \$this;
    }
";
    }

    /**
     * Adds the joinFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addJoinFk(string &$script, ForeignKey $fk): void
    {
        $queryClass = $this->getQueryClassName();
        $fkTable = $fk->getForeignTable();
        $relationName = $this->getFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);
        $this->addJoinRelated($script, $fkTable, $queryClass, $relationName, $joinType);
    }

    /**
     * Adds the joinRefFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addJoinRefFk(string &$script, ForeignKey $fk): void
    {
        $queryClass = $this->getQueryClassName();
        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $relationName = $this->getRefFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);
        $this->addJoinRelated($script, $fkTable, $queryClass, $relationName, $joinType);
    }

    /**
     * Adds a joinRelated method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addJoinRelated(
        string &$script,
        Table $fkTable,
        string $queryClass,
        string $relationName,
        string $joinType
    ): void {
        $script .= "
    /**
     * Adds a JOIN clause to the query using the " . $relationName . " relation
     *
     * @param string|null \$relationAlias Optional alias for the relation
     * @param string|null \$joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \$this
     */
    public function join" . $relationName . '(?string $relationAlias = null, ?string $joinType = ' . $joinType . ")
    {
        \$tableMap = \$this->getTableMap();
        \$relationMap = \$tableMap->getRelation('" . $relationName . "');

        // create a ModelJoin object for this join
        \$join = new ModelJoin();
        \$join->setJoinType(\$joinType);
        \$leftAlias = \$this->useAliasInSQL ? \$this->getModelAlias() : null;
        \$join->setupJoinCondition(\$this, \$relationMap, \$leftAlias, \$relationAlias);
        if (\$previousJoin = \$this->getPreviousJoin()) {
            \$join->setPreviousJoin(\$previousJoin);
        }

        // add the ModelJoin to the current object
        if (\$relationAlias) {
            \$this->addAlias(\$relationAlias, \$relationMap->getRightTable()->getName());
            \$this->addJoinObject(\$join, \$relationAlias);
        } else {
            \$this->addJoinObject(\$join, '" . $relationName . "');
        }

        return \$this;
    }
";
    }

    /**
     * Adds the useFkQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addUseFkQuery(string &$script, ForeignKey $fk): void
    {
        $fkTable = $fk->getForeignTable();
        $fkQueryBuilder = $this->getNewStubQueryBuilder($fkTable);
        $queryClass = $this->getClassNameFromBuilder($fkQueryBuilder, true);
        $relationName = $this->getFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);

        $this->addUseRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addWithRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addUseRelatedExistsQuery($script, $fkTable, $queryClass, $relationName);
        $this->addUseRelatedInQuery($script, $fkTable, $queryClass, $relationName);
    }

    /**
     * Adds the useFkQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addUseRefFkQuery(string &$script, ForeignKey $fk): void
    {
        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $fkQueryBuilder = $this->getNewStubQueryBuilder($fkTable);
        $queryClass = $this->getClassNameFromBuilder($fkQueryBuilder, true);
        $relationName = $this->getRefFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);

        $this->addUseRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addWithRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addUseRelatedExistsQuery($script, $fkTable, $queryClass, $relationName);
        $this->addUseRelatedInQuery($script, $fkTable, $queryClass, $relationName);
    }

    /**
     * Adds a useRelatedQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addUseRelatedQuery(string &$script, Table $fkTable, string $queryClass, string $relationName, string $joinType): void
    {
        $script .= "
    /**
     * Use the $relationName relation " . $fkTable->getPhpName() . " object
     *
     * @see useQuery()
     *
     * @param string \$relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param string \$joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $queryClass<static> A secondary query class using the current class as primary query
     */
    public function use" . $relationName . 'Query($relationAlias = null, $joinType = ' . $joinType . ")
    {
        /** @var $queryClass<static> \$query */
        \$query = \$this->join" . $relationName . "(\$relationAlias, \$joinType)
            ->useQuery(\$relationAlias ?: '$relationName', '$queryClass');

        return \$query;        
    }
";
    }

    /**
     * Adds a useExistsQuery and useNotExistsQuery to the object script.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable The target of the relation
     * @param string $queryClass Query object class name that will be returned by the exists statement.
     * @param string $relationName Name of the relation
     *
     * @return void
     */
    protected function addUseRelatedExistsQuery(string &$script, Table $fkTable, string $queryClass, string $relationName): void
    {
        $vars = [
            'queryClass' => $queryClass,
            'relationDescription' => $this->getRelationDescription($relationName, $fkTable),
            'relationName' => $relationName,
            'existsType' => ExistsQueryCriterion::TYPE_EXISTS,
            'notExistsType' => ExistsQueryCriterion::TYPE_NOT_EXISTS,
        ];
        $templatePath = $this->getTemplatePath(__DIR__);

        $template = new PropelTemplate();
        $filePath = $templatePath . 'baseQueryExistsMethods.php';
        $template->setTemplateFile($filePath);

        $script .= $template->render($vars);
    }

    /**
     * Adds a useInQuery and useNotInQuery to the object script.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable The target of the relation
     * @param string $queryClass Query object class name that will be returned by the IN statement.
     * @param string $relationName Name of the relation
     *
     * @return void
     */
    protected function addUseRelatedInQuery(string &$script, Table $fkTable, string $queryClass, string $relationName): void
    {
        $vars = [
            'queryClass' => $queryClass,
            'relationDescription' => $this->getRelationDescription($relationName, $fkTable),
            'relationName' => $relationName,
            'inType' => trim(Criteria::IN),
            'notInType' => trim(Criteria::NOT_IN),
        ];
        $templatePath = $this->getTemplatePath(__DIR__);

        $template = new PropelTemplate();
        $filePath = $templatePath . 'baseQueryInMethods.php';
        $template->setTemplateFile($filePath);

        $script .= $template->render($vars);
    }

    /**
     * @param string $relationName
     * @param \Propel\Generator\Model\Table $fkTable
     *
     * @return string
     */
    protected function getRelationDescription(string $relationName, Table $fkTable): string
    {
        return ($relationName === $fkTable->getPhpName()) ?
            "relation to $relationName table" :
            "$relationName relation to the {$fkTable->getPhpName()} table";
    }

    /**
     * Adds a withRelatedQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addWithRelatedQuery(string &$script, Table $fkTable, string $queryClass, string $relationName, string $joinType): void
    {
        $script .= "
    /**
     * Use the {$relationName} relation {$fkTable->getPhpName()} object
     *
     * @param callable({$queryClass}):{$queryClass} \$callable A function working on the related query
     *
     * @param string|null \$relationAlias optional alias for the relation
     *
     * @param string|null \$joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \$this
     */
    public function with{$relationName}Query(
        callable \$callable,
        ?string \$relationAlias = null,
        ?string \$joinType = {$joinType}
    ) {
        \$relatedQuery = \$this->use{$relationName}Query(
            \$relationAlias,
            \$joinType
        );
        \$callable(\$relatedQuery);
        \$relatedQuery->endUse();

        return \$this;
    }
";
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossRelation $crossFKs
     *
     * @return void
     */
    protected function addFilterByCrossFK(string &$script, CrossRelation $crossFKs): void
    {
        $relationName = $this->getRefFKPhpNameAffix($crossFKs->getIncomingForeignKey(), false);

        foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
            $crossRefTable = $crossFK->getTable();
            $foreignTable = $crossFK->getForeignTable();
            $fkPhpName = $foreignTable->getPhpName();
            $crossTableName = $crossRefTable->getName();
            $relName = $this->getFKPhpNameAffix($crossFK, false);
            $objectName = '$' . $foreignTable->getCamelCaseName();
            $script .= "
    /**
     * Filter the query by a related $fkPhpName object
     * using the $crossTableName table as cross reference
     *
     * @param $fkPhpName $objectName the related object to use as filter
     * @param string \$comparison Operator to use for the column comparison, defaults to Criteria::EQUAL and Criteria::IN for queries
     *
     * @return \$this
     */
    public function filterBy{$relName}($objectName, ?string \$comparison = null)
    {
        \$this
            ->use{$relationName}Query()
            ->filterBy{$relName}($objectName, \$comparison)
            ->endUse();

        return \$this;
    }
";
        }
    }

    /**
     * Adds the prune method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addPrune(string &$script): void
    {
        $table = $this->getTable();
        $class = $this->getObjectClassName();
        $objectName = '$' . $table->getCamelCaseName();

        $script .= "
    /**
     * Exclude object from result
     *
     * @param $class $objectName Object to remove from the list of results
     *
     * @return \$this
     */
    public function prune($objectName = null)
    {
        if ($objectName) {";

        $pks = $table->getPrimaryKey();
        if (count($pks) > 1) {
            $col1 = array_shift($pks);
            $script .= "
            \$pkFilter = \$this->buildFilter(\$this->resolveLocalColumnByName('{$col1->getName()}'), {$objectName}->get{$col1->getPhpName()}(), Criteria::NOT_EQUAL);";
            foreach ($pks as $col) {
                $script .= "
            \$pkFilter->addOr(\$this->buildFilter(\$this->resolveLocalColumnByName('{$col->getName()}'), {$objectName}->get{$col->getPhpName()}(), Criteria::NOT_EQUAL));";
            }
            $script .= "
            \$this->addAnd(\$pkFilter);";
        } elseif ($table->hasPrimaryKey()) {
            $col = $pks[0];
            $columnName = $col->getName();
            $script .= "
            \$resolvedColumn = \$this->resolveLocalColumnByName('$columnName');
            \$this->addUsingOperator(\$resolvedColumn, {$objectName}->get" . $col->getPhpName() . '(), Criteria::NOT_EQUAL);';
        } else {
            $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
            $script .= "
            throw new LogicException('{$this->getObjectName()} object has no primary key');
";
        }
        $script .= "
        }

        return \$this;
    }
";
    }

    /**
     * Adds the basePreSelect hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreSelect(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preSelectQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every SELECT statement
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     */
    protected function basePreSelect(ConnectionInterface \$con): void
    {" . $behaviorCode . "

        \$this->preSelect(\$con);
    }
";
    }

    /**
     * Adds the basePreDelete hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreDelete(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preDeleteQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every DELETE statement
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     *  @return int|null
     */
    protected function basePreDelete(ConnectionInterface \$con): ?int
    {" . $behaviorCode . "

        return \$this->preDelete(\$con);
    }
";
    }

    /**
     * Adds the basePostDelete hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePostDelete(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('postDeleteQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute after every DELETE statement
     *
     * @param int \$affectedRows the number of deleted rows
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     * @return int|null
     */
    protected function basePostDelete(int \$affectedRows, ConnectionInterface \$con): ?int
    {" . $behaviorCode . "

        return \$this->postDelete(\$affectedRows, \$con);
    }
";
    }

    /**
     * Adds the basePreUpdate hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreUpdate(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preUpdateQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every UPDATE statement
     *
     * @param array \$values The associative array of columns and values for the update
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     * @param bool \$forceIndividualSaves If false (default), the resulting call is a Criteria::doUpdate(), otherwise it is a series of save() calls on all the found objects
     *
     * @return int|null
     */
    protected function basePreUpdate(&\$values, ConnectionInterface \$con, \$forceIndividualSaves = false): ?int
    {" . $behaviorCode . "

        return \$this->preUpdate(\$values, \$con, \$forceIndividualSaves);
    }
";
    }

    /**
     * Adds the basePostUpdate hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePostUpdate(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('postUpdateQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute after every UPDATE statement
     *
     * @param int \$affectedRows the number of updated rows
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     * @return int|null
     */
    protected function basePostUpdate(\$affectedRows, ConnectionInterface \$con): ?int
    {" . $behaviorCode . "

        return \$this->postUpdate(\$affectedRows, \$con);
    }
";
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $modifier
     *
     * @return bool
     */
    #[\Override]
    public function hasBehaviorModifier(string $hookName, string $modifier = ''): bool
    {
        return parent::hasBehaviorModifier($hookName, 'QueryBuilderModifier');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $script The script will be modified in this method.
     * @param string $tab
     *
     * @return string
     */
    public function applyBehaviorModifier(string $hookName, string &$script, string $tab = '        '): string
    {
        $this->applyBehaviorModifierBase($hookName, 'QueryBuilderModifier', $script, $tab);

        return $script;
    }

    /**
     * Checks whether any registered behavior content creator on that table exists a contentName
     *
     * @param string $contentName The name of the content as called from one of this class methods, e.g. "parentClassName"
     *
     * @return string|null
     */
    public function getBehaviorContent(string $contentName): ?string
    {
        return $this->getBehaviorContentBase($contentName, 'QueryBuilderModifier');
    }

    /**
     * Adds the doDelete() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDelete(string &$script): void
    {
        $script .= "
    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws \Propel\Runtime\Exception\PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public function delete(?ConnectionInterface \$con = null): int
    {
        if (null === \$con) {
            \$con = Propel::getServiceContainer()->getWriteConnection(" . $this->getTableMapClass() . "::DATABASE_NAME);
        }

        \$criteria = \$this;

        // Set the correct dbName
        \$criteria->setDbName(" . $this->getTableMapClass() . "::DATABASE_NAME);

        // use transaction because \$criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return \$con->transaction(function () use (\$con, \$criteria) {
            \$affectedRows = 0; // initialize var to track total num of affected rows
            ";

        if ($this->isDeleteCascadeEmulationNeeded()) {
            $script .= "
            // cloning the Criteria in case it's modified by doSelect() or doSelectStmt()
            \$c = clone \$criteria;
            \$affectedRows += \$c->doOnDeleteCascade(\$con);
            ";
        }

        if ($this->isDeleteSetNullEmulationNeeded()) {
            $script .= "
            // cloning the Criteria in case it's modified by doSelect() or doSelectStmt()
            \$c = clone \$criteria;
            \$c->doOnDeleteSetNull(\$con);
            ";
        }

        $script .= "
            {$this->getTableMapClassName()}::removeInstanceFromPool(\$criteria);
        ";

        $script .= "
            \$affectedRows += ModelCriteria::delete(\$con);
            {$this->getTableMapClassName()}::clearRelatedInstancePool();

            return \$affectedRows;
        });
    }
";
    }

    /**
     * Adds the doOnDeleteCascade() method, which provides ON DELETE CASCADE emulation.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoOnDeleteCascade(string &$script): void
    {
        $table = $this->getTable();
        $script .= "
    /**
     * This is a method for emulating ON DELETE CASCADE for DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those Query classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    protected function doOnDeleteCascade(ConnectionInterface \$con): int
    {
        // initialize var to track total num of affected rows
        \$affectedRows = 0;

        // first find the objects that are implicated by the \$this
        \$objects = {$this->getQueryClassName()}::create(null, \$this)->find(\$con);
        foreach (\$objects as \$obj) {
";

        foreach ($table->getReferrers() as $fk) {
            // $fk is the foreign key in the other table, so localTableName will
            // actually be the table name of other table
            $tblFK = $fk->getTable();

            $joinedTableTableMapBuilder = $this->getNewTableMapBuilder($tblFK);

            if (!$tblFK->isForReferenceOnly()) {
                // we can't perform operations on tables that are
                // not within the schema (i.e. that we have no map for, etc.)

                $fkClassName = $joinedTableTableMapBuilder->getObjectClassName();

                if ($fk->getOnDelete() === ForeignKey::CASCADE) {
                    // backwards on purpose
                    $columnNamesF = $fk->getLocalColumns();
                    $columnNamesL = $fk->getForeignColumns();

                    $this->declareClassFromBuilder($joinedTableTableMapBuilder->getTableMapBuilder());

                    $script .= "

            // delete related $fkClassName objects
            \$query = new " . $joinedTableTableMapBuilder->getQueryClassName(true) . ";
            ";
                    for ($x = 0, $xlen = count($columnNamesF); $x < $xlen; $x++) {
                        $columnFK = $tblFK->getColumn($columnNamesF[$x]);
                        $columnL = $table->getColumn($columnNamesL[$x]);

                        $script .= "
            \$query->add(" . $joinedTableTableMapBuilder->getColumnConstant($columnFK) . ', $obj->get' . $columnL->getPhpName() . '());';
                    }

                    $script .= "
            \$affectedRows += \$query->delete(\$con);";
                }
            }
        }
        $script .= "
        }

        return \$affectedRows;
    }
";
    }

    // end addDoOnDeleteCascade


    /**
     * Adds the doOnDeleteSetNull() method, which provides ON DELETE SET NULL emulation.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoOnDeleteSetNull(string &$script): void
    {
        $table = $this->getTable();
        $script .= "
    /**
     * This is a method for emulating ON DELETE SET NULL DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those query classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con
     * @return void
     */
    protected function doOnDeleteSetNull(ConnectionInterface \$con): void
    {
        // first find the objects that are implicated by the \$this
        \$objects = {$this->getQueryClassName()}::create(null, \$this)->find(\$con);
        foreach (\$objects as \$obj) {
";

        // This logic is almost exactly the same as that in doOnDeleteCascade()
        // it may make sense to refactor this, provided that things don't
        // get too complicated.
        foreach ($table->getReferrers() as $fk) {
            // $fk is the foreign key in the other table, so localTableName will
            // actually be the table name of other table
            $tblFK = $fk->getTable();
            $refTableTableMapBuilder = $this->getNewTableMapBuilder($tblFK);

            if (!$tblFK->isForReferenceOnly()) {
                // we can't perform operations on tables that are
                // not within the schema (i.e. that we have no map for, etc.)

                $fkClassName = $refTableTableMapBuilder->getObjectClassName();

                if ($fk->getOnDelete() === ForeignKey::SETNULL) {
                    // backwards on purpose
                    $columnNamesF = $fk->getLocalColumns();
                    $columnNamesL = $fk->getForeignColumns(); // should be same num as foreign

                    $this->declareClassFromBuilder($refTableTableMapBuilder);

                    $script .= "
            // set fkey col in related $fkClassName rows to NULL
            \$query = new " . $refTableTableMapBuilder->getQueryClassName(true) . "();
            \$updateValues = new Criteria();";

                    for ($x = 0, $xlen = count($columnNamesF); $x < $xlen; $x++) {
                        $columnFK = $tblFK->getColumn($columnNamesF[$x]);
                        $columnL = $table->getColumn($columnNamesL[$x]);
                        $script .= "
            \$query->add(" . $refTableTableMapBuilder->getColumnConstant($columnFK) . ', $obj->get' . $columnL->getPhpName() . "());
            \$updateValues->add(" . $refTableTableMapBuilder->getColumnConstant($columnFK) . ", null);
";
                    }

                    $script .= "\$query->update(\$updateValues, \$con);
";
                }
            }
        }

        $script .= "
        }
    }
";
    }

    /**
     * Adds the doDeleteAll() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoDeleteAll(string &$script): void
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Deletes all rows from the " . $table->getName() . " table.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(?ConnectionInterface \$con = null): int
    {
        if (null === \$con) {
            \$con = Propel::getServiceContainer()->getWriteConnection(" . $this->getTableMapClass() . "::DATABASE_NAME);
        }

        // use transaction because \$criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return \$con->transaction(function () use (\$con) {
            \$affectedRows = 0; // initialize var to track total num of affected rows
            ";
        if ($this->isDeleteCascadeEmulationNeeded()) {
            $script .= "\$affectedRows += \$this->doOnDeleteCascade(\$con);
            ";
        }
        if ($this->isDeleteSetNullEmulationNeeded()) {
            $script .= "\$this->doOnDeleteSetNull(\$con);
            ";
        }
        $script .= "\$affectedRows += parent::doDeleteAll(\$con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            {$this->getTableMapClassName()}::clearInstancePool();
            {$this->getTableMapClassName()}::clearRelatedInstancePool();

            return \$affectedRows;
        });
    }
";
    }
}
