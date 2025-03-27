<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\InvalidArgumentException;
use Propel\Runtime\Formatter\AbstractFormatter;
use Propel\Runtime\Formatter\ArrayFormatter;
use Propel\Runtime\Formatter\StatementFormatter;
use Propel\Tests\Bookstore\Book;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * Test class for ModelCriteria.
 *
 * @group database
 */
class ModelCriteriaFormatterTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    public function testFormatter()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $this->assertTrue($c->getFormatter() instanceof AbstractFormatter, 'getFormatter() returns a PropelFormatter instance');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_STATEMENT);
        $this->assertTrue($c->getFormatter() instanceof StatementFormatter, 'setFormatter() accepts the name of a AbstractFormatter class');

        try {
            $c->setFormatter('Propel\Tests\Bookstore\Book');
            $this->fail('setFormatter() throws an exception when passed the name of a class not extending AbstractFormatter');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true, 'setFormatter() throws an exception when passed the name of a class not extending AbstractFormatter');
        }
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $formatter = new StatementFormatter();
        $c->setFormatter($formatter);
        $this->assertTrue($c->getFormatter() instanceof StatementFormatter, 'setFormatter() accepts a AbstractFormatter instance');

        try {
            $formatter = new Book();
            $c->setFormatter($formatter);
            $this->fail('setFormatter() throws an exception when passed an object not extending AbstractFormatter');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true, 'setFormatter() throws an exception when passed an object not extending AbstractFormatter');
        }
    }

    /**
     * @return void
     */
    public function testFindOneOrCreateNotExistsFormatter()
    {
        BookQuery::create()->deleteAll();
        $book = BookQuery::create('b')
            ->where('b.Title = ?', 'foo')
            ->filterByPrice(125)
            ->setFormatter(ModelCriteria::FORMAT_ARRAY)
            ->findOneOrCreate();
        $this->assertTrue(is_array($book), 'findOneOrCreate() uses the query formatter even when the request has no result');
        $this->assertEquals('foo', $book['Title'], 'findOneOrCreate() returns a populated array based on the conditions');
        $this->assertEquals(125, $book['Price'], 'findOneOrCreate() returns a populated array based on the conditions');
    }

    /**
     * @return void
     */
    public function testGetIteratorReturnsATraversableWithArrayFormatter()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_ARRAY);
        $this->assertInstanceOf('Traversable', $c->getIterator());
    }

    /**
     * @return void
     */
    public function testGetIteratorAllowsTraversingQueryObjectsWithArrayFormatter()
    {
        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_ARRAY);
        $nbResults = 0;
        foreach ($c as $book) {
            $nbResults++;
        }
        $this->assertEquals(4, $nbResults);
    }

    /**
     * @return void
     */
    public function testGetIteratorReturnsATraversableWithOnDemandFormatter()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
        $it = $c->getIterator();
        $this->assertInstanceOf('Traversable', $it);
        $it->closeCursor();
    }

    /**
     * @return void
     */
    public function testGetIteratorAllowsTraversingQueryObjectsWithOnDemandFormatter()
    {
        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
        $nbResults = 0;
        foreach ($c as $book) {
            $nbResults++;
        }
        $this->assertEquals(4, $nbResults);
    }

    /**
     * @return void
     */
    public function testGetIteratorReturnsATraversableWithStatementFormatter()
    {
        if ($this->runningOnSQLite()){
            $this->markTestIncomplete('not closing the iterator leads to a locked table on sqlite');
        }
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_STATEMENT);
        $this->assertInstanceOf('Traversable', $c->getIterator());
    }

    /**
     * @return void
     */
    public function testGetIteratorAllowsTraversingQueryObjectsWithStatementFormatter()
    {
        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->setFormatter(ModelCriteria::FORMAT_STATEMENT);
        $nbResults = 0;
        foreach ($c as $book) {
            $nbResults++;
        }
        $this->assertEquals(4, $nbResults);
    }

    /**
     * @return void
     */
    public function testGetIteratorReturnsATraversableWithSimpleArrayFormatter()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Id');
        $this->assertInstanceOf('Traversable', $c->getIterator());
    }

    /**
     * @return void
     */
    public function testGetIteratorAllowsTraversingQueryObjectsWithSimpleArrayFormatter()
    {
        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Id');
        $nbResults = 0;
        foreach ($c as $book) {
            $nbResults++;
        }
        $this->assertEquals(4, $nbResults);
    }

    /**
     * @return void
     */
    public function testCloneCopiesFormatter()
    {
        $formatter1 = new ArrayFormatter();
        $formatter1->test = false;
        $bookQuery1 = BookQuery::create();
        $bookQuery1->setFormatter($formatter1);
        $bookQuery2 = clone $bookQuery1;
        $formatter2 = $bookQuery2->getFormatter();
        $this->assertFalse($formatter2->test);
        $formatter2->test = true;
        $this->assertFalse($formatter1->test);
    }
}
