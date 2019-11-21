<?php

/*
 * This file is part of the FiveLab AmqpBundle package
 *
 * (c) FiveLab
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Connection\Registry;

use FiveLab\Bundle\AmqpBundle\Exception\ConnectionFactoryNotFoundException;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;

/**
 * All connection factories should implement this interface.
 */
interface ConnectionFactoryRegistryInterface
{
    /**
     * Get the connection factory by name
     *
     * @param string $name
     *
     * @return ConnectionFactoryInterface
     *
     * @throws ConnectionFactoryNotFoundException
     */
    public function get(string $name): ConnectionFactoryInterface;
}
