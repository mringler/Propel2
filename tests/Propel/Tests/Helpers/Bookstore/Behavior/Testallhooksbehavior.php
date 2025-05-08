<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Helpers\Bookstore\Behavior;

use Propel\Generator\Model\Behavior;

class TestAllHooksBehavior extends Behavior
{
    protected $tableModifier, $objectBuilderModifier, $queryBuilderModifier;

    public function getTableModifier()
    {
        if ($this->tableModifier === null) {
            $this->tableModifier = new TestAllHooksTableModifier($this);
        }

        return $this->tableModifier;
    }

    public function getObjectBuilderModifier()
    {
        if ($this->objectBuilderModifier === null) {
            $this->objectBuilderModifier = new TestAllHooksObjectBuilderModifier($this);
        }

        return $this->objectBuilderModifier;
    }

    public function getQueryBuilderModifier()
    {
        if ($this->queryBuilderModifier === null) {
            $this->queryBuilderModifier = new TestAllHooksQueryBuilderModifier($this);
        }

        return $this->queryBuilderModifier;
    }
}

class TestAllHooksTableModifier
{
    protected $behavior, $table;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
        $this->table = $behavior->getTable();
    }

    /**
     * @return void
     */
    public function modifyTable(): void
    {
        $this->table->addColumn([
            'name' => 'test',
            'type' => 'TIMESTAMP',
        ]);
    }
}

class TestAllHooksObjectBuilderModifier
{
    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function objectAttributes($builder)
    {
        return '
        public $customAttribute = 1;
        public $flagDump = [];
        ';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preSave($builder)
    {
        return '
            $this->flagDump["preSave"] = 1;
            $this->flagDump["preSaveIsAfterSave"] = isset($affectedRows);
            $this->flagDump["preSaveBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postSave($builder)
    {
        return '
        $this->flagDump["postSave"] = 1;
        $this->flagDump["postSaveIsAfterSave"] = isset($affectedRows);
        $this->flagDump["postSaveBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preInsert($builder)
    {
        return '
        $this->flagDump["preInsert"] = 1;
        $this->flagDump["preInsertIsAfterSave"] = isset($affectedRows);
        $this->flagDump["preInsertBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postInsert($builder)
    {
        return '
        $this->flagDump["postInsert"] = 1;
        $this->flagDump["postInsertIsAfterSave"] = isset($affectedRows);
        $this->flagDump["postInsertBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preUpdate($builder)
    {
        return '
        $this->flagDump["preUpdate"] = 1;
        $this->flagDump["preUpdateIsAfterSave"] = isset($affectedRows);
        $this->flagDump["preUpdateBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postUpdate($builder)
    {
        return '
        $this->flagDump["postUpdate"] = 1;
        $this->flagDump["postUpdateIsAfterSave"] = isset($affectedRows);
        $this->flagDump["postUpdateBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preDelete($builder)
    {
        return '
        $this->flagDump["preDelete"] = 1;
        $this->flagDump["preDeleteIsBeforeDelete"] = isset(Table3TableMap::$instances[$this->id]);
        $this->flagDump["preDeleteBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postDelete($builder)
    {
        return '
        $this->flagDump["postDelete"] = 1;
        $this->flagDump["postDeleteIsBeforeDelete"] = isset(Table3TableMap::$instances[$this->id]);
        $this->flagDump["postDeleteBuilder"]="' . get_class($builder) . '";';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function objectMethods($builder)
    {
        return 'public function hello() { return "' . get_class($builder) . '"; }';
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function objectCall($builder)
    {
        return 'if ($name == "foo") return "bar";';
    }

    /**
     * @return void
     */
    public function objectFilter(&$string, $builder): void
    {
        $string .= 'class testObjectFilter { const FOO = "' . get_class($builder) . '"; }';
    }
}

class TestAllHooksQueryBuilderModifier
{
    public function staticAttributes($builder)
    {
        return '/**
 * @var int
 */
public static $customStaticAttribute = 1;

/**
 * @var string
 */
public static $staticAttributeBuilder = "' . get_class($builder) . '";
        ';
    }

    public function staticMethods($builder)
    {
        $builderClass = get_class($builder);
        return "/**
 * @return string
 */
public static function hello()
{
    return '$builderClass';
}
";
    }

    /**
     * @return void
     */
    public function queryFilter(&$string, $builder): void
    {
        $builderClass = get_class($builder);
        $string .= "
// queryFilter hook
";
    }

    public function preSelectQuery($builder)
    {
        return '// foo';
    }

    public function preDeleteQuery($builder)
    {
        return '// foo';
    }

    public function postDeleteQuery($builder)
    {
        return '// foo';
    }

    public function preUpdateQuery($builder)
    {
        return '// foo';
    }

    public function postUpdateQuery($builder)
    {
        return '// foo';
    }
}
