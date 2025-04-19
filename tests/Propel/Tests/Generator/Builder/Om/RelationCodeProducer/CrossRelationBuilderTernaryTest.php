<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\CrossRelationPartialCodeProducer;

/**
 */
class CrossRelationBuilderTernaryTest extends AbstractCrossRelationBuilderTest
{
    /**
     * @return void
     */
    public function testType(): void
    {
        $this->assertInstanceOf(CrossRelationPartialCodeProducer::class, $this->getCodeProducer());
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
     * @var \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent}> Objects in TeamEvent relation.
     */
    protected $combinationTeamEvents;

    /**
     * @var bool
     */
    protected $combinationTeamEventsIsPartial;
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
     * Items of TeamEvent relation marked for deletion.
     *
     * @var \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent}>
     */
    protected $teamEventsScheduledForDeletion = null;
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
     * Initializes the combinationTeamEvents crossRef collection.
     *
     * By default this just sets the combinationTeamEvents collection to an empty collection (like clearTeamEvents());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initTeamEvents(): void
    {
        $this->combinationTeamEvents = new ObjectCombinationCollection();
        $this->combinationTeamEventsIsPartial = true;
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
     * Checks if the combinationTeamEvents collection is loaded.
     *
     * @return bool
     */
    public function isTeamEventsLoaded(): bool
    {
        return $this->combinationTeamEvents !== null;
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
     * Clears out the combinationTeamEvents collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @see static::addTeamEvents()
     *
     * @return void
     */
    public function clearTeamEvents(): void
    {
        $this->combinationTeamEvents = null; // important to set this to NULL since that means it is uninitialized
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
        $this->combinationTeamEvents = null;';

        $this->assertProducedCodeMatches('addOnReloadCode', $expected);
    }

    /**
     * @return void
     */
    public function testDeleteScheduledItemsCode()
    {
        $expected = '
            if ($this->teamEventsScheduledForDeletion !== null && !$this->teamEventsScheduledForDeletion->isEmpty()) {
                $pks = [];
                foreach ($this->teamEventsScheduledForDeletion as $combination) {
                    $entryPk = [];

                    $entryPk[0] = $this->getId();
                    $entryPk[1] = $combination[0]->getId();
                    $entryPk[2] = $combination[1]->getId();

                    $pks[] = $entryPk;
                }

                ChildTeamUserQuery::create()
                    ->filterByPrimaryKeys($pks)
                    ->delete($con);

                $this->teamEventsScheduledForDeletion = null;
            }

            if ($this->combinationTeamEvents !== null) {
                foreach ($this->combinationTeamEvents as $combination) {
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
     * @param ChildEvent $event
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return ChildTeamQuery
     */
    public function createTeamsQuery(ChildEvent $event, ?Criteria $criteria = null): ChildTeamQuery
    {
        $query = ChildTeamQuery::create($criteria)
            ->filterByUser($this);

        $teamUserQuery = $query->useTeamUserQuery();

        if ($event !== null) {
            $teamUserQuery->filterByEvent($event);
        }

        $teamUserQuery->endUse();

        return $query;
    }

    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     *
     * @param ChildTeam $team
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return ChildEventQuery
     */
    public function createEventsQuery(ChildTeam $team, ?Criteria $criteria = null): ChildEventQuery
    {
        $query = ChildEventQuery::create($criteria)
            ->filterByUser($this);

        $teamUserQuery = $query->useTeamUserQuery();

        if ($team !== null) {
            $teamUserQuery->filterByTeam($team);
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
        $expected = ['TeamEvent', 'Team', 'Event'];
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
     * @return \Propel\Runtime\Collection\ObjectCombinationCollection<array{ChildTeam, ChildEvent}>
     */
    public function getTeamEvents(?Criteria $criteria = null, ?ConnectionInterface $con = null): ObjectCombinationCollection
    {
        $partial = $this->combinationTeamEventsIsPartial && !$this->isNew();
        if ($this->combinationTeamEvents !== null && !$partial && !$criteria) {
            return $this->combinationTeamEvents;
        }

        if ($this->isNew()) {
            // return empty collection
            if ($this->combinationTeamEvents === null) {
                $this->initTeamEvents();
            }

            return $this->combinationTeamEvents;
        }

        $query = ChildTeamUserQuery::create(null, $criteria)
            ->filterByUser($this)
            ->joinTeam()
            ->joinEvent()
            ;

        $items = $query->find($con);
        $combinationTeamEvents = new ObjectCombinationCollection();
        foreach ($items as $item) {
            $combination = [];

            $combination[] = $item->getTeam();
            $combination[] = $item->getEvent();

            $combinationTeamEvents[] = $combination;
        }

        if ($criteria) {
            return $combinationTeamEvents;
        }

        if ($partial && $this->combinationTeamEvents) {
            //make sure that already added objects gets added to the list of the database.
            foreach ($this->combinationTeamEvents as $obj) {
                if (!$combinationTeamEvents->contains($obj)) {
                    $combinationTeamEvents[] = $obj;
                }
            }
        }

        $this->combinationTeamEvents = $combinationTeamEvents;
        $this->combinationTeamEventsIsPartial = false;

        return $this->combinationTeamEvents;
    }

    /**
     * Returns a not cached ObjectCollection of ChildTeam objects. This will hit always the databases.
     * If you have attached new ChildTeam object to this object you need to call `save` first to get
     * the correct return value. Use getTeamEvents() to get the current internal state.
     *
     * @param ChildEvent $event
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<ChildTeam>
     */
    public function getTeams(ChildEvent $event, ?Criteria $criteria = null, ?ConnectionInterface $con = null)
    {
        return $this->createTeamsQuery($event, $criteria)->find($con);
    }

    /**
     * Returns a not cached ObjectCollection of ChildEvent objects. This will hit always the databases.
     * If you have attached new ChildEvent object to this object you need to call `save` first to get
     * the correct return value. Use getTeamEvents() to get the current internal state.
     *
     * @param ChildTeam $team
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<ChildEvent>
     */
    public function getEvents(ChildTeam $team, ?Criteria $criteria = null, ?ConnectionInterface $con = null)
    {
        return $this->createEventsQuery($team, $criteria)->find($con);
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
     * Sets a collection of TeamEvent objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<array{ChildTeam, ChildEvent}> $teamEvents A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return $this
     */
    public function setTeamEvents(Collection $teamEvents, ?ConnectionInterface $con = null): static
    {
        $this->clearTeamEvents();
        $currentTeamEvents = $this->getTeamEvents();

        $teamEventsScheduledForDeletion = $currentTeamEvents->diff($teamEvents);

        foreach ($teamEventsScheduledForDeletion as $toDelete) {
            $this->removeTeamEvent(...$toDelete);
        }

        foreach ($teamEvents as $teamEvent) {
            if (!$currentTeamEvents->contains($teamEvent)) {
                $this->doAddTeamEvent(...$teamEvent);
            }
        }

        $this->combinationTeamEventsIsPartial = false;
        $this->combinationTeamEvents = $teamEvents;

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
     * Gets the number of TeamEvent objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param bool $distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return int The number of related TeamEvent objects
     */
    public function countTeamEvents(?Criteria $criteria = null, bool $distinct = false, ?ConnectionInterface $con = null): int
    {
        $partial = $this->combinationTeamEventsIsPartial && !$this->isNew();
        if ($this->combinationTeamEvents && !$criteria && !$partial) {
            return count($this->combinationTeamEvents);
        }

        if ($this->isNew() && $this->combinationTeamEvents === null) {
            return 0;
        }

        if ($partial && !$criteria) {
            return count($this->getTeamEvents());
        }

        $query = ChildTeamQuery::create(null, $criteria);
        if ($distinct) {
            $query->distinct();
        }

        return $query
            ->filterByUser($this)
            ->count($con);
    }

    /**
     * Returns the not cached count of ChildTeam objects. This will hit always the databases.
     * If you have attached new ChildTeam object to this object you need to call `save` first to get
     * the correct return value. Use getTeamEvents() to get the current internal state.
     *
     * @param ChildEvent $event
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public function countTeams(ChildEvent $event, ?Criteria $criteria = null, ?ConnectionInterface $con = null): int
    {
        return $this->createTeamsQuery($event, $criteria)->count($con);
    }

    /**
     * Returns the not cached count of ChildEvent objects. This will hit always the databases.
     * If you have attached new ChildEvent object to this object you need to call `save` first to get
     * the correct return value. Use getTeamEvents() to get the current internal state.
     *
     * @param ChildTeam $team
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public function countEvents(ChildTeam $team, ?Criteria $criteria = null, ?ConnectionInterface $con = null): int
    {
        return $this->createEventsQuery($team, $criteria)->count($con);
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
     * Associate a Team with this object through the team_user cross reference table.
     *
     * @param ChildTeam $team
     * @param ChildEvent $event
     *
     * @return static
     */
    public function addTeam(ChildTeam $team, ChildEvent $event): static
    {
        if ($this->combinationTeamEvents === null) {
            $this->initTeamEvents();
        }

        if (!$this->getTeamEvents()->contains([$team, $event])) {
            // only add it if the **same** object is not already associated
            $this->combinationTeamEvents->push([$team, $event]);
            $this->doAddTeamEvent($team, $event);
        }

        return $this;
    }

    /**
     * Associate a Event with this object through the team_user cross reference table.
     *
     * @param ChildEvent $event
     * @param ChildTeam $team
     *
     * @return static
     */
    public function addEvent(ChildEvent $event, ChildTeam $team): static
    {
        if ($this->combinationTeamEvents === null) {
            $this->initTeamEvents();
        }

        if (!$this->getTeamEvents()->contains([$team, $event])) {
            // only add it if the **same** object is not already associated
            $this->combinationTeamEvents->push([$team, $event]);
            $this->doAddTeamEvent($team, $event);
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
     * @param ChildTeam $team
     * @param ChildEvent $event
     *
     * return void
     */
    protected function doAddTeamEvent(ChildTeam $team, ChildEvent $event): void
    {
        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        $teamUser->setEvent($event);
        $teamUser->setUser($this);

        $this->addTeamUser($teamUser);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        $userEventsEntry = [$this, $event];
        if ($team->isUserEventsLoaded()) {
            $team->getUserEvents()->push($userEventsEntry);
        } elseif (!$team->getUserEvents()->contains($userEventsEntry)) {
            $team->initUserEvents();
            $team->getUserEvents()->push($userEventsEntry);
        }
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        $userTeamsEntry = [$this, $team];
        if ($event->isUserTeamsLoaded()) {
            $event->getUserTeams()->push($userTeamsEntry);
        } elseif (!$event->getUserTeams()->contains($userTeamsEntry)) {
            $event->initUserTeams();
            $event->getUserTeams()->push($userTeamsEntry);
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
     * Remove team, event of this object through the team_user cross reference table.
     *
     * @param ChildTeam $team
     * @param ChildEvent $event
     *
     * @return static
     */
    public function removeTeamEvent(ChildTeam $team, ChildEvent $event): static
    {
        if (!$this->getTeamEvents()->contains([$team, $event])) {
            return $this;
        }

        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        if ($team->isUserEventsLoaded()) {
            //remove the back reference if available
            $team->getUserEvents()->removeObject([$this, $event]);
        }

        $teamUser->setEvent($event);
        if ($event->isUserTeamsLoaded()) {
            //remove the back reference if available
            $event->getUserTeams()->removeObject([$this, $team]);
        }

        $teamUser->setUser($this);
        $this->removeTeamUser(clone $teamUser);
        $teamUser->clear();

        $this->combinationTeamEvents->remove($this->combinationTeamEvents->search([$team, $event]));

        if ($this->teamEventsScheduledForDeletion === null) {
            $this->teamEventsScheduledForDeletion = clone $this->combinationTeamEvents;
            $this->teamEventsScheduledForDeletion->clear();
        }

        $this->teamEventsScheduledForDeletion->push([$team, $event]);

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
            if ($this->combinationTeamEvents) {
                foreach ($this->combinationTeamEvents as $o) {
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

        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="event">
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
