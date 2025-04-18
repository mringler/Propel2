<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\ObjectBuilder\CrossRelationMultiModelCodeProducer;

/**
 */
class CrossRelationBuilderNamedTernary extends AbstractCrossRelationBuilderTest
{
    /**
     * @return void
     */
    public function testType(): void
    {
        $this->assertInstanceOf(CrossRelationMultiModelCodeProducer::class, $this->getCodeProducer());
    }

    /**
     * @return void
     */
    public function testRegisterClasses()
    {
        $producer = $this->getCodeProducer();
        $producer->registerTargetClasses();
        $referencedClasses = $this->getObjectPropertyValue($producer, 'referencedClasses');

        $aliasedClasses = $referencedClasses->getDeclaredClasses('');
        $expectedAliasedClasses = ['ChildEvent', 'ChildEventQuery', 'ChildTeam', 'ChildTeamQuery'];
        $this->assertEqualsCanonicalizing($expectedAliasedClasses, $aliasedClasses);

        $collectionClasses = $referencedClasses->getDeclaredClasses('Propel\Runtime\Collection');
        $expectedCollections = ['ObjectCombinationCollection'];
        $this->assertEqualsCanonicalizing($expectedCollections, $collectionClasses);
    }

    /**
     * @return void
     */
    public function testAttributes()
    {
        $expected = '
    /**
     * @var \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent, string}> Objects in LeTeamLeEventDate relation.
     */
    protected $combinationLeTeamLeEventDates;

    /**
     * @var bool
     */
    protected $combinationLeTeamLeEventDatesIsPartial;
';
        $this->assertProducedCodeMatches('addAttributes', $expected);
    }

    /**
     * @return void
     */
    public function testDeletionAttributes()
    {
        $expected = '
    /**
     * Items of LeTeamLeEventDate relation marked for deletion.
     *
     * @var \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent, string}>
     */
    protected $leTeamLeEventDatesScheduledForDeletion = null;
';
        $this->assertProducedCodeMatches('addScheduledForDeletionAttribute', $expected);
    }


    /**
     * @return void
     */
    public function testInit()
    {
        $expected = '
    /**
     * Initializes the combinationLeTeamLeEventDates crossRef collection.
     *
     * By default this just sets the combinationLeTeamLeEventDates collection to an empty collection (like clearLeTeamLeEventDates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initLeTeamLeEventDates(): void
    {
        $this->combinationLeTeamLeEventDates = new ObjectCombinationCollection();
        $this->combinationLeTeamLeEventDatesIsPartial = true;
    }
';
        $this->assertProducedCodeMatches('addInit', $expected);
    }


    /**
     * @return void
     */
    public function testIsLoaded()
    {
        $expected = '
    /**
     * Checks if the combinationLeTeamLeEventDates collection is loaded.
     *
     * @return bool
     */
    public function isLeTeamLeEventDatesLoaded(): bool
    {
        return $this->combinationLeTeamLeEventDates !== null;
    }
';
        $this->assertProducedCodeMatches('addisLoaded', $expected);
    }


    /**
     * @return void
     */
    public function testClear()
    {
        $expected = '
    /**
     * Clears out the combinationLeTeamLeEventDates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @see static::addLeTeamLeEventDates()
     *
     * @return void
     */
    public function clearLeTeamLeEventDates(): void
    {
        $this->combinationLeTeamLeEventDates = null; // important to set this to NULL since that means it is uninitialized
    }
';
        $this->assertProducedCodeMatches('addClear', $expected);
    }

    /**
     * @return void
     */
    public function testOnReloadCode()
    {
        $expected = '
        $this->combinationLeTeamLeEventDates = null;';

        $this->assertProducedCodeMatches('addOnReloadCode', $expected);
    }

    /**
     * @return void
     */
    public function testDeleteScheduledItemsCode()
    {
        $expected = '
            if ($this->leTeamLeEventDatesScheduledForDeletion !== null && !$this->leTeamLeEventDatesScheduledForDeletion->isEmpty()) {
                $pks = [];
                foreach ($this->leTeamLeEventDatesScheduledForDeletion as $combination) {
                    $entryPk = [];

                    $entryPk[0] = $this->getId();
                    $entryPk[1] = $combination[0]->getId();
                    $entryPk[2] = $combination[1]->getId();
                    $entryPk[3] = $combination[2];

                    $pks[] = $entryPk;
                }

                ChildTeamUserQuery::create()
                    ->filterByPrimaryKeys($pks)
                    ->delete($con);

                $this->leTeamLeEventDatesScheduledForDeletion = null;
            }

            if ($this->combinationLeTeamLeEventDates !== null) {
                foreach ($this->combinationLeTeamLeEventDates as $combination) {
                    $model = $combination[0];
                    if (!$model->isDeleted() && ($model->isNew() || $model->isModified())) {
                        $model->save($con);
                    }

                    $model = $combination[1];
                    if (!$model->isDeleted() && ($model->isNew() || $model->isModified())) {
                        $model->save($con);
                    }

                }
            }

';
        $this->assertProducedCodeMatches('addDeleteScheduledItemsCode', $expected);
    }

    /**
     * @return void
     */
    public function testCreateQuery()
    {
        $expected = '
    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     *
     * @param ChildEvent $leEvent
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return ChildTeamQuery
     */
    public function createLeTeamsQuery(ChildEvent $leEvent, ?string $date = null, ?Criteria $criteria = null): ChildTeamQuery
    {
        $query = ChildTeamQuery::create($criteria)
            ->filterByLeUser($this);

        $teamUserQuery = $query->useTeamUserQuery();

        if ($leEvent !== null) {
            $teamUserQuery->filterByLeEvent($leEvent);
        }

        if ($date !== null) {
            $teamUserQuery->filterByDate($date);
        }

        $teamUserQuery->endUse();

        return $query;
    }

    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     *
     * @param ChildTeam $leTeam
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return ChildEventQuery
     */
    public function createLeEventsQuery(ChildTeam $leTeam, ?string $date = null, ?Criteria $criteria = null): ChildEventQuery
    {
        $query = ChildEventQuery::create($criteria)
            ->filterByLeUser($this);

        $teamUserQuery = $query->useTeamUserQuery();

        if ($leTeam !== null) {
            $teamUserQuery->filterByLeTeam($leTeam);
        }

        if ($date !== null) {
            $teamUserQuery->filterByDate($date);
        }

        $teamUserQuery->endUse();

        return $query;
    }
';
        $this->assertProducedCodeMatches('addCreateQuery', $expected);
    }

    /**
     * @return void
     */
    public function testReserveNamesForGetters()
    {
        $reservedNames = $this->getCodeProducer()->reserveNamesForGetters();
        $expected = ['LeTeamLeEventDate', 'LeTeam', 'LeEvent'];
        $this->assertEqualsCanonicalizing($expected, $reservedNames);
    }

    /**
     * @return void
     */
    public function testGet()
    {
        $expected = '
    /**
     * Gets a combined collection of ChildTeam, ChildEvent objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildUser is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent, string}>
     */
    public function getLeTeamLeEventDates(?Criteria $criteria = null, ?ConnectionInterface $con = null): ObjectCombinationCollection
    {
        $partial = $this->combinationLeTeamLeEventDatesIsPartial && !$this->isNew();
        if ($this->combinationLeTeamLeEventDates !== null && !$partial && !$criteria) {
            return $this->combinationLeTeamLeEventDates;
        }

        if ($this->isNew()) {
            // return empty collection
            if ($this->combinationLeTeamLeEventDates === null) {
                $this->initLeTeamLeEventDates();
            }

            return $this->combinationLeTeamLeEventDates;
        }

        $query = ChildTeamUserQuery::create(null, $criteria)
            ->filterByLeUser($this)
            ->joinLeTeam()
            ->joinLeEvent()
            ;

        $items = $query->find($con);
        $combinationLeTeamLeEventDates = new ObjectCombinationCollection();
        foreach ($items as $item) {
            $combination = [];

            $combination[] = $item->getLeTeam();
            $combination[] = $item->getLeEvent();
            $combination[] = $item->getDate();

            $combinationLeTeamLeEventDates[] = $combination;
        }

        if ($criteria) {
            return $combinationLeTeamLeEventDates;
        }

        if ($partial && $this->combinationLeTeamLeEventDates) {
            //make sure that already added objects gets added to the list of the database.
            foreach ($this->combinationLeTeamLeEventDates as $obj) {
                if (!$combinationLeTeamLeEventDates->contains($obj)) {
                    $combinationLeTeamLeEventDates[] = $obj;
                }
            }
        }

        $this->combinationLeTeamLeEventDates = $combinationLeTeamLeEventDates;
        $this->combinationLeTeamLeEventDatesIsPartial = false;

        return $this->combinationLeTeamLeEventDates;
    }

    /**
     * Returns a not cached ObjectCollection of ChildTeam objects. This will hit always the databases.
     * If you have attached new ChildTeam object to this object you need to call `save` first to get
     * the correct return value. Use getLeTeamLeEventDates() to get the current internal state.
     *
     * @param ChildEvent $leEvent
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<ChildTeam>
     */
    public function getLeTeams(ChildEvent $leEvent, ?string $date = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null)
    {
        return $this->createLeTeamsQuery($leEvent, $date, $criteria)->find($con);
    }

    /**
     * Returns a not cached ObjectCollection of ChildEvent objects. This will hit always the databases.
     * If you have attached new ChildEvent object to this object you need to call `save` first to get
     * the correct return value. Use getLeTeamLeEventDates() to get the current internal state.
     *
     * @param ChildTeam $leTeam
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<ChildEvent>
     */
    public function getLeEvents(ChildTeam $leTeam, ?string $date = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null)
    {
        return $this->createLeEventsQuery($leTeam, $date, $criteria)->find($con);
    }
';
        $this->assertProducedCodeMatches('addGetters', $expected);
    }

    /**
     * @return void
     */
    public function testSet()
    {
        $expected = '
    /**
     * Sets a collection of LeTeamLeEventDate objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<array{ChildTeam, ChildEvent, string}> $leTeamLeEventDates A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return $this
     */
    public function setLeTeamLeEventDates(Collection $leTeamLeEventDates, ?ConnectionInterface $con = null): static
    {
        $this->clearLeTeamLeEventDates();
        $currentLeTeamLeEventDates = $this->getLeTeamLeEventDates();

        $leTeamLeEventDatesScheduledForDeletion = $currentLeTeamLeEventDates->diff($leTeamLeEventDates);

        foreach ($leTeamLeEventDatesScheduledForDeletion as $toDelete) {
            $this->removeLeTeamLeEventDate(...$toDelete);
        }

        foreach ($leTeamLeEventDates as $leTeamLeEventDate) {
            if (!$currentLeTeamLeEventDates->contains($leTeamLeEventDate)) {
                $this->doAddLeTeamLeEventDate(...$leTeamLeEventDate);
            }
        }

        $this->combinationLeTeamLeEventDatesIsPartial = false;
        $this->combinationLeTeamLeEventDates = $leTeamLeEventDates;

        return $this;
    }
';
        $this->assertProducedCodeMatches('addSetters', $expected);
    }

    /**
     * @return void
     */
    public function testCount()
    {
        $expected = '
    /**
     * Gets the number of LeTeamLeEventDate objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param bool $distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return int The number of related LeTeamLeEventDate objects
     */
    public function countLeTeamLeEventDates(?Criteria $criteria = null, bool $distinct = false, ?ConnectionInterface $con = null): int
    {
        $partial = $this->combinationLeTeamLeEventDatesIsPartial && !$this->isNew();
        if ($this->combinationLeTeamLeEventDates && !$criteria && !$partial) {
            return count($this->combinationLeTeamLeEventDates);
        }

        if ($this->isNew() && $this->combinationLeTeamLeEventDates === null) {
            return 0;
        }

        if ($partial && !$criteria) {
            return count($this->getLeTeamLeEventDates());
        }

        $query = ChildTeamQuery::create(null, $criteria);
        if ($distinct) {
            $query->distinct();
        }

        return $query
            ->filterByLeUser($this)
            ->count($con);
    }

    /**
     * Returns the not cached count of ChildTeam objects. This will hit always the databases.
     * If you have attached new ChildTeam object to this object you need to call `save` first to get
     * the correct return value. Use getLeTeamLeEventDates() to get the current internal state.
     *
     * @param ChildEvent $leEvent
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public function countLeTeams(ChildEvent $leEvent, ?string $date = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null): int
    {
        return $this->createLeTeamsQuery($leEvent, $date, $criteria)->count($con);
    }

    /**
     * Returns the not cached count of ChildEvent objects. This will hit always the databases.
     * If you have attached new ChildEvent object to this object you need to call `save` first to get
     * the correct return value. Use getLeTeamLeEventDates() to get the current internal state.
     *
     * @param ChildTeam $leTeam
     * @param string|null $date
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public function countLeEvents(ChildTeam $leTeam, ?string $date = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null): int
    {
        return $this->createLeEventsQuery($leTeam, $date, $criteria)->count($con);
    }
';
        $this->assertProducedCodeMatches('addCount', $expected);
    }

    /**
     * @return void
     */
    public function testAdd()
    {
        $expected = '
    /**
     * Associate a LeTeam with this object through the team_user cross reference table.
     *
     * @param ChildTeam $leTeam
     * @param ChildEvent $leEvent
     * @param string $date
     *
     * @return static
     */
    public function addLeTeam(ChildTeam $leTeam, ChildEvent $leEvent, string $date): static
    {
        if ($this->combinationLeTeamLeEventDates === null) {
            $this->initLeTeamLeEventDates();
        }

        if (!$this->getLeTeamLeEventDates()->contains([$leTeam, $leEvent, $date])) {
            // only add it if the **same** object is not already associated
            $this->combinationLeTeamLeEventDates->push([$leTeam, $leEvent, $date]);
            $this->doAddLeTeamLeEventDate($leTeam, $leEvent, $date);
        }

        return $this;
    }

    /**
     * Associate a LeEvent with this object through the team_user cross reference table.
     *
     * @param ChildEvent $leEvent
     * @param ChildTeam $leTeam
     * @param string $date
     *
     * @return static
     */
    public function addLeEvent(ChildEvent $leEvent, ChildTeam $leTeam, string $date): static
    {
        if ($this->combinationLeTeamLeEventDates === null) {
            $this->initLeTeamLeEventDates();
        }

        if (!$this->getLeTeamLeEventDates()->contains([$leTeam, $leEvent, $date])) {
            // only add it if the **same** object is not already associated
            $this->combinationLeTeamLeEventDates->push([$leTeam, $leEvent, $date]);
            $this->doAddLeTeamLeEventDate($leTeam, $leEvent, $date);
        }

        return $this;
    }
';
        $this->assertProducedCodeMatches('addAdders', $expected);
    }

    /**
     * @return void
     */
    public function testDoAdd()
    {
        $expected = '
    /**
     * @param ChildTeam $leTeam
     * @param ChildEvent $leEvent
     * @param string $date
     *
     * return void
     */
    protected function doAddLeTeamLeEventDate(ChildTeam $leTeam, ChildEvent $leEvent, string $date): void
    {
        $teamUser = new ChildTeamUser();
        $teamUser->setLeTeam($leTeam);
        $teamUser->setLeEvent($leEvent);
        $teamUser->setDate($date);
        $teamUser->setLeUser($this);

        $this->addTeamUser($teamUser);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        $leUserLeEventDatesEntry = [$this, $leEvent, $date];
        if ($leTeam->isLeUserLeEventDatesLoaded()) {
            $leTeam->getLeUserLeEventDates()->push($leUserLeEventDatesEntry);
        } elseif (!$leTeam->getLeUserLeEventDates()->contains($leUserLeEventDatesEntry)) {
            $leTeam->initLeUserLeEventDates();
            $leTeam->getLeUserLeEventDates()->push($leUserLeEventDatesEntry);
        }
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        $leUserLeTeamDatesEntry = [$this, $leTeam, $date];
        if ($leEvent->isLeUserLeTeamDatesLoaded()) {
            $leEvent->getLeUserLeTeamDates()->push($leUserLeTeamDatesEntry);
        } elseif (!$leEvent->getLeUserLeTeamDates()->contains($leUserLeTeamDatesEntry)) {
            $leEvent->initLeUserLeTeamDates();
            $leEvent->getLeUserLeTeamDates()->push($leUserLeTeamDatesEntry);
        }
    }
';
        $this->assertProducedCodeMatches('buildDoAdd', $expected);
    }

    /**
     * @return void
     */
    public function testRemove()
    {
        $expected = '
    /**
     * Remove leTeam, leEvent, date of this object through the team_user cross reference table.
     *
     * @param ChildTeam $leTeam
     * @param ChildEvent $leEvent
     * @param string $date
     *
     * @return static
     */
    public function removeLeTeamLeEventDate(ChildTeam $leTeam, ChildEvent $leEvent, string $date): static
    {
        if (!$this->getLeTeamLeEventDates()->contains([$leTeam, $leEvent, $date])) {
            return $this;
        }

        $teamUser = new ChildTeamUser();
        $teamUser->setLeTeam($leTeam);
        if ($leTeam->isLeUserLeEventDatesLoaded()) {
            //remove the back reference if available
            $leTeam->getLeUserLeEventDates()->removeObject([$this, $leEvent, $date]);
        }

        $teamUser->setLeEvent($leEvent);
        if ($leEvent->isLeUserLeTeamDatesLoaded()) {
            //remove the back reference if available
            $leEvent->getLeUserLeTeamDates()->removeObject([$this, $leTeam, $date]);
        }

        $teamUser->setDate($date);
        $teamUser->setLeUser($this);
        $this->removeTeamUser(clone $teamUser);
        $teamUser->clear();

        $this->combinationLeTeamLeEventDates->remove($this->combinationLeTeamLeEventDates->search([$leTeam, $leEvent, $date]));

        if ($this->leTeamLeEventDatesScheduledForDeletion === null) {
            $this->leTeamLeEventDatesScheduledForDeletion = clone $this->combinationLeTeamLeEventDates;
            $this->leTeamLeEventDatesScheduledForDeletion->clear();
        }

        $this->leTeamLeEventDatesScheduledForDeletion->push([$leTeam, $leEvent, $date]);

        return $this;
    }
';
        $this->assertProducedCodeMatches('addRemove', $expected);
    }

    /**
     * @return void
     */
    public function testClearReferencesCode()
    {
        $expected = '
            if ($this->combinationLeTeamLeEventDates) {
                foreach ($this->combinationLeTeamLeEventDates as $o) {
                    $o[0]->clearAllReferences($deep);
                }
            }';

        $this->assertProducedCodeMatches('addClearReferencesCode', $expected);
    }

    /**
     * @return string
     */
    protected function getSchema(): string
    {  
        return <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>

    </table>

    <table name="team_user" isCrossRef="true">
        <column name="user_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="team_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="event_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="date" type="datetime" primaryKey="true" required="true" />

        <foreign-key foreignTable="user" phpName="LeUser">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team" phpName="LeTeam">
            <reference local="team_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="event" phpName="LeEvent">
            <reference local="event_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>

    <table name="event">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;
    }

}
