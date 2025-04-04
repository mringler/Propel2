<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Tests\TestCaseFixtures;

/**
 * @group database
 */
class UpdateColumnTest extends TestCaseFixtures
{
    public function testMissingPdoTypeCreatesNotice(): void
    {
        set_error_handler(static function (int $errno, string $errstr) {
            throw new \Exception($errstr, $errno);
        }, E_USER_NOTICE);

        $c = new Criteria();

        $this->expectExceptionMessage("Could not resolve column 'title', assuming PDO type is string. Consider setting PDO type yourself.");
        $c->setUpdateValue('title', 'Updated Title');
    }
}
