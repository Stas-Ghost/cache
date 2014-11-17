<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Memcached as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Memcached.
 */
class MemcachedTest extends MemcacheTest
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $memcached = new \Memcached();
        $memcached->addServer('127.0.0.1', 11211);

        return new Driver(array('client' => $memcached, 'prefix_id' => 'PHPUnit_'));
    }
}
