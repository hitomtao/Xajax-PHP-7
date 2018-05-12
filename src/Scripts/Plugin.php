<?php
/**
 * PHP version php7
 *
 * @category
 * @package            jybrid-php-7
 * @author             ${JProof}
 * @copyright          ${copyright}
 * @license            ${license}
 * @link
 * @see                ${docu}
 * @since              27.10.2017
 */

declare(strict_types=1);

namespace Jybrid\Scripts;

use Jybrid\Datas\Data;

/**
 * Class Plugin
 * Plugin Script Data Object
 *
 * @package Jybrid\Scripts
 * @property-read string $name
 */
class Plugin extends Data implements Iface
{
	use Base;
}