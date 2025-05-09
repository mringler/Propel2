<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveRecord;

use Propel\Runtime\Map\TableMap;
use Propel\Tests\Bookstore\Author;
use Propel\Tests\Bookstore\Book;
use Propel\Tests\Bookstore\Publisher;
use Propel\Tests\TestCaseFixtures;

/**
 * Test class for ActiveRecord.
 *
 * @author François Zaninotto
 */
class ActiveRecordConvertTest extends TestCaseFixtures
{
    /**
     * @var \Propel\Tests\Bookstore\Book
     */
    private $book;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $publisher = new Publisher();
        $publisher->setId(1234);
        $publisher->setName('Penguin');
        $author = new Author();
        $author->setId(5678);
        $author->setFirstName('George');
        $author->setLastName('Byron');
        $book = new Book();
        $book->setId(9012);
        $book->setTitle('Don Juan');
        $book->setISBN('0140422161');
        $book->setPrice(12.99);
        $book->setAuthor($author);
        $book->setPublisher($publisher);
        $this->book = $book;
    }

    public function toXmlDataProvider()
    {
        $expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <Id>9012</Id>
  <Title><![CDATA[Don Juan]]></Title>
  <ISBN><![CDATA[0140422161]]></ISBN>
  <Price>12.99</Price>
  <PublisherId>1234</PublisherId>
  <AuthorId>5678</AuthorId>
  <Publisher>
    <Id>1234</Id>
    <Name><![CDATA[Penguin]]></Name>
    <Books>
      <Book>
        <Book><![CDATA[*RECURSION*]]></Book>
      </Book>
    </Books>
  </Publisher>
  <Author>
    <Id>5678</Id>
    <FirstName><![CDATA[George]]></FirstName>
    <LastName><![CDATA[Byron]]></LastName>
    <Email></Email>
    <Age></Age>
    <Books>
      <Book>
        <Book><![CDATA[*RECURSION*]]></Book>
      </Book>
    </Books>
  </Author>
</data>

EOF;

        return [[$expected]];
    }

    /**
     * @dataProvider toXmlDataProvider
     *
     * @return void
     */
    public function testToXML($expected)
    {
        $this->assertEquals($expected, $this->book->toXML());
    }

    /**
     * @dataProvider toXmlDataProvider
     *
     * @return void
     */
    public function testFromXML($expected)
    {
        $book = new Book();
        $book->fromXML($expected);
        // FIXME: fromArray() doesn't take related objects into account
        $book->resetModified();
        $author = $this->book->getAuthor();
        $this->book->setAuthor(null);
        $this->book->setAuthorId($author->getId());
        $publisher = $this->book->getPublisher();
        $this->book->setPublisher(null);
        $this->book->setPublisherId($publisher->getId());
        $this->book->resetModified();

        $this->assertEquals($this->book, $book);
    }

    public function toYamlDataProvider()
    {
        $expected = <<<EOF
Id: 9012
Title: 'Don Juan'
ISBN: '0140422161'
Price: 12.99
PublisherId: 1234
AuthorId: 5678
Publisher:
    Id: 1234
    Name: Penguin
    Books:
        - ['*RECURSION*']
Author:
    Id: 5678
    FirstName: George
    LastName: Byron
    Email: null
    Age: null
    Books:
        - ['*RECURSION*']

EOF;

        return [[$expected]];
    }

    /**
     * @dataProvider toYamlDataProvider
     *
     * @return void
     */
    public function testToYAML($expected)
    {
        $this->assertEquals($expected, $this->book->toYAML());
    }

    /**
     * @dataProvider toYamlDataProvider
     *
     * @return void
     */
    public function testFromYAML($expected)
    {
        $book = new Book();
        $book->fromYAML($expected);
        // FIXME: fromArray() doesn't take related objects into account
        $book->resetModified();
        $author = $this->book->getAuthor();
        $this->book->setAuthor(null);
        $this->book->setAuthorId($author->getId());
        $publisher = $this->book->getPublisher();
        $this->book->setPublisher(null);
        $this->book->setPublisherId($publisher->getId());
        $this->book->resetModified();

        $this->assertEquals($this->book, $book);
    }

    public function toJsonDataProvider()
    {
        $phpName = <<<EOF
{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678,"Publisher":{"Id":1234,"Name":"Penguin","Books":[["*RECURSION*"]]},"Author":{"Id":5678,"FirstName":"George","LastName":"Byron","Email":null,"Age":null,"Books":[["*RECURSION*"]]}}
EOF;
        $camelName = <<<EOF
{"id":9012,"title":"Don Juan","iSBN":"0140422161","price":12.99,"publisherId":1234,"authorId":5678,"publisher":{"id":1234,"name":"Penguin","books":[["*RECURSION*"]]},"author":{"id":5678,"firstName":"George","lastName":"Byron","email":null,"age":null,"books":[["*RECURSION*"]]}}
EOF;

        $colName = <<<EOF
{"book.id":9012,"book.title":"Don Juan","book.isbn":"0140422161","book.price":12.99,"book.publisher_id":1234,"book.author_id":5678,"Publisher":{"publisher.id":1234,"publisher.name":"Penguin","Books":[["*RECURSION*"]]},"Author":{"author.id":5678,"author.first_name":"George","author.last_name":"Byron","author.email":null,"author.age":null,"Books":[["*RECURSION*"]]}}
EOF;

        $fieldName = <<<EOF
{"id":9012,"title":"Don Juan","isbn":"0140422161","price":12.99,"publisher_id":1234,"author_id":5678,"publisher":{"id":1234,"name":"Penguin","books":[["*RECURSION*"]]},"author":{"id":5678,"first_name":"George","last_name":"Byron","email":null,"age":null,"books":[["*RECURSION*"]]}}
EOF;

        return [[$phpName, TableMap::TYPE_PHPNAME],
                [$camelName, TableMap::TYPE_CAMELNAME],
                [$colName, TableMap::TYPE_COLNAME],
                [$fieldName, TableMap::TYPE_FIELDNAME]];
    }

    /**
     * @dataProvider toJsonDataProvider
     *
     * @return void
     */
    public function testToJSON($expected, $type)
    {
        $this->assertEquals($expected, $this->book->toJSON(true, $type));
    }

    /**
     * @dataProvider toJsonDataProvider
     *
     * @return void
     */
    public function testfromJSON($expected, $type)
    {
        $book = new Book();
        $book->fromJSON($expected, $type);
        // FIXME: fromArray() doesn't take related objects into account
        $book->resetModified();
        $author = $this->book->getAuthor();
        $this->book->setAuthor(null);
        $this->book->setAuthorId($author->getId());
        $publisher = $this->book->getPublisher();
        $this->book->setPublisher(null);
        $this->book->setPublisherId($publisher->getId());
        $this->book->resetModified();

        $this->assertEquals($this->book, $book);
    }

    public function toCsvDataProvider()
    {
        $expected = "Id,Title,ISBN,Price,PublisherId,AuthorId,Publisher,Author\r\n9012,Don Juan,0140422161,12.99,1234,5678,\"a:3:{s:2:\\\"Id\\\";i:1234;s:4:\\\"Name\\\";s:7:\\\"Penguin\\\";s:5:\\\"Books\\\";a:1:{i:0;a:1:{i:0;s:11:\\\"*RECURSION*\\\";}}}\",\"a:6:{s:2:\\\"Id\\\";i:5678;s:9:\\\"FirstName\\\";s:6:\\\"George\\\";s:8:\\\"LastName\\\";s:5:\\\"Byron\\\";s:5:\\\"Email\\\";N;s:3:\\\"Age\\\";N;s:5:\\\"Books\\\";a:1:{i:0;a:1:{i:0;s:11:\\\"*RECURSION*\\\";}}}\"\r\n";

        return [[$expected]];
    }

    /**
     * @dataProvider toCsvDataProvider
     *
     * @return void
     */
    public function testToCSV($expected)
    {
        $this->assertEquals($expected, $this->book->toCSV());
    }

    /**
     * @dataProvider toCsvDataProvider
     *
     * @return void
     */
    public function testfromCSV($expected)
    {
        $book = new Book();
        $book->fromCSV($expected);
        // FIXME: fromArray() doesn't take related objects into account
        $book->resetModified();
        $author = $this->book->getAuthor();
        $this->book->setAuthor(null);
        $this->book->setAuthorId($author->getId());
        $publisher = $this->book->getPublisher();
        $this->book->setPublisher(null);
        $this->book->setPublisherId($publisher->getId());
        $this->book->resetModified();

        $this->assertEquals($this->book, $book);
    }
}
