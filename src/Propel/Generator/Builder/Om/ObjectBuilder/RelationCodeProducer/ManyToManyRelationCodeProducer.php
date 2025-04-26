<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;

/**
 * Produces code for cross/many-to-many relations that requires only an element
 * of the opposite table to produce entries in the middle table (in contrast to
 * cross relations where the middle table is a ternary relation or its primary
 * key contains additional non-null columns).
 */
class ManyToManyRelationCodeProducer extends AbstractManyToManyCodeProducer
{
    /**
     * @return \Propel\Generator\Model\ForeignKey
     */
    protected function getFkToTarget(): ForeignKey
    {
        return $this->crossRelation->getCrossForeignKeys()[0]; // fixed shape has only ever one fk.
    }

    /**
     * @param \Propel\Generator\Model\Table|null $table
     *
     * @return array{string, string}
     */
    #[\Override]
    protected function resolveObjectCollectionClassNameAndType(?Table $table = null): array
    {
        return parent::resolveObjectCollectionClassNameAndType($table ?? $this->getFkToTarget()->getForeignTable());
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addScheduledForDeletionAttribute(string &$script): void
    {
        $refFK = $this->crossRelation->getIncomingForeignKey();
        if ($refFK->isLocalPrimaryKey()) {
            return;
        }

        parent::addScheduledForDeletionAttribute($script);
    }

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addInit(string &$script): void
    {
        $fk = $this->getFkToTarget();
        $relatedObjectClassName = $this->referencedClasses->getInternalNameOfBuilderResultClass(
            $this->getNewStubObjectBuilder($fk->getForeignTable()),
            true,
        );

        $foreignTableMapName = $this->resolveClassNameForTable(GeneratorConfig::KEY_TABLEMAP, $fk->getForeignTable());

        $script .= $this->buildInitCode(null, $foreignTableMapName, $relatedObjectClassName);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addCreateQuery(string &$script): void
    {
        // no addCreateQuery.
    }

    /**
     * Reports the names used in getters/setters created for this cross relation.
     *
     * Names should be in singular form. Used for schema validation.
     *
     * @return array<string>
     */
    #[\Override]
    public function reserveNamesForGetters(): array
    {
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);

        return [$targetIdentifierSingular];
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addGetters(string &$script): void
    {
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);

        $targetTable = $this->getFkToTarget()->getForeignTable();
        $targetModelClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $targetTable);
        $targetQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $targetTable);

        $attributeName = $this->names->getAttributeWithCollectionName();
        $attributeIsPartialName = $this->names->getAttributeIsPartialName();

        [$objectCollectionClass, $objectCollectionType] = $this->resolveObjectCollectionClassNameAndType($this->getFkToTarget()->getForeignTable());

        $script .= "
    /**
     * Gets a collection of $targetModelClassName objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this " . $this->ownClassIdentifier() . " is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria Optional query object to filter the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con Optional connection object
     *
     * @return $objectCollectionType
     */
    public function get{$targetIdentifierPlural}(?Criteria \$criteria = null, ?ConnectionInterface \$con = null): $objectCollectionClass
    {
        \$partial = \$this->{$attributeIsPartialName} && !\$this->isNew();
        if (\$this->$attributeName === null || \$criteria !== null || \$partial) {
            if (\$this->isNew()) {
                // return empty collection
                if (\$this->$attributeName === null) {
                    \$this->init{$targetIdentifierPlural}();
                }
            } else {

                \$query = $targetQueryClassName::create(null, \$criteria)
                    ->filterBy{$sourceIdentifierSingular}(\$this);
                \$$attributeName = \$query->find(\$con);
                if (\$criteria !== null) {
                    return \$$attributeName;
                }

                if (\$partial && \$this->{$attributeName}) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach (\$this->{$attributeName} as \$obj) {
                        if (!\${$attributeName}->contains(\$obj)) {
                            \${$attributeName}[] = \$obj;
                        }
                    }
                }

                \$this->$attributeName = \$$attributeName;
                \$this->{$attributeIsPartialName} = false;
            }
        }

        return \$this->$attributeName;
    }
";
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function setterItemIsArray(): bool
    {
        return false;
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $scheduledForDeletionVarName = $this->names->getAttributeScheduledForDeletionName();
        $middleQueryClassName = $this->resolveMiddleQueryClassName();

        $crossPks = $this->crossRelation->getMiddleTable()->getPrimaryKey();

        $script .= "
            if (\$this->$scheduledForDeletionVarName !== null && !\$this->{$scheduledForDeletionVarName}->isEmpty()) {
                \$pks = [];
                foreach (\$this->{$scheduledForDeletionVarName} as \$entry) {
                    \$entryPk = [];\n";

        foreach ($this->crossRelation->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
            $local = $reference['local'];
            $foreign = $reference['foreign'];

            $idx = array_search($local, $crossPks, true);
            $script .= "
                    \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
        }

        $crossFK = $this->crossRelation->getCrossForeignKeys()[0];
        foreach ($crossFK->getColumnObjectsMapping() as $reference) {
            $local = $reference['local'];
            $foreign = $reference['foreign'];

            $idx = array_search($local, $crossPks, true);
            $script .= "
                    \$entryPk[$idx] = \$entry->get{$foreign->getPhpName()}();";
        }

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $targetVar = $this->names->getTargetIdentifier(false, true);

        $script .= "
                    \$pks[] = \$entryPk;
                }

                {$middleQueryClassName}::create()
                    ->filterByPrimaryKeys(\$pks)
                    ->delete(\$con);

                \$this->$scheduledForDeletionVarName = null;
            }

            if (\$this->coll{$targetIdentifierPlural}) {
                foreach (\$this->coll{$targetIdentifierPlural} as \${$targetVar}) {
                    if (!\${$targetVar}->isDeleted() && (\${$targetVar}->isNew() || \${$targetVar}->isModified())) {
                        \${$targetVar}->save(\$con);
                    }
                }
            }\n\n";
    }

    /**
     * @return string
     */
    #[\Override]
    protected function buildAdditionalCountMethods(): string
    {
        return '';
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function buildDoAdd(string &$script): void
    {
        $middleTableModelClass = $this->names->getMiddleTableModelClass();

        $middleTableIdentifierSingular = $this->names->getMiddleTableIdentifier(false);
        $middleTableObject = '$' . $this->crossRelation->getMiddleTable()->getCamelCaseName();

        [$parameterDeclaration, $_, $phpDoc] = $this->collectSignature()->buildFullSignature();
        $targetGetterParameters = $this->collectSignature($this->getFkToTarget())->buildFunctionParameterVariables();

        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);
        $targetObject = '$' . $this->names->getTargetIdentifier(false, true);

        $ownIdentifierSingular = $this->names->getSourceIdentifier(false);
        $ownIdentifierPlural = $this->names->getSourceIdentifier(true);

        $script .= "
    /**{$phpDoc}
     *
     * @return void
     */
    protected function doAdd{$targetIdentifierSingular}($parameterDeclaration): void
    {
        {$middleTableObject} = new $middleTableModelClass();
        {$middleTableObject}->set{$targetIdentifierSingular}($targetObject);
        {$middleTableObject}->set{$ownIdentifierSingular}(\$this);

        \$this->add{$middleTableIdentifierSingular}($middleTableObject);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!{$targetObject}->is{$ownIdentifierPlural}Loaded()) {
            {$targetObject}->init{$ownIdentifierPlural}();
            {$targetObject}->get{$ownIdentifierPlural}($targetGetterParameters)->push(\$this);
        } elseif (!{$targetObject}->get{$ownIdentifierPlural}($targetGetterParameters)->contains(\$this)) {
            {$targetObject}->get{$ownIdentifierPlural}($targetGetterParameters)->push(\$this);
        }
    }
";
    }
}
