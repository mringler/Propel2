<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Collection;

use Countable;
use Iterator;

/**
 * @template RowFormat
 * @extends \Iterator<(int|string), RowFormat>
 */
interface IteratorInterface extends Iterator, Countable
{
}
