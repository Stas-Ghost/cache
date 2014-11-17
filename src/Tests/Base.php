<?php
namespace Endeveit\Cache\Tests;

/**
 * Main class with tests.
 */
abstract class Base extends \PHPUnit_Framework_TestCase
{

    /**
     * Driver object.
     *
     * @var \Endeveit\Cache\Interfaces\Driver
     */
    protected $driver = null;

    /**
     * Entries lifetime.
     *
     * @var integer
     */
    protected $lifetime = 2;

    /**
     * Array with random generated identifiers.
     *
     * @var array
     */
    protected $cacheIdentifiers = array();

    /**
     * Array with cache tags for generated identifiers.
     *
     * @var array
     */
    protected $cacheIdentifiersTags = array();

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testLoad()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));
        $this->assertEquals($this->getDataForIdentifier($identifier), $this->driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testExpiredNoLock()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));

        sleep($this->lifetime + 1);

        $this->assertEquals($this->getDataForIdentifier($identifier), $this->driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testExpiredWithLock()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));

        sleep($this->lifetime + 1);

        $this->assertFalse($this->driver->load($identifier, 10));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::loadMany()
     */
    public function testLoadMany()
    {
        $identifiers = $this->getRandomIdentifiers();

        foreach ($identifiers as $identifier) {
            $this->saveInCache($identifier);
        }

        $this->assertFalse(array_search(false, $this->driver->loadMany($identifiers)));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::loadMany()
     */
    public function testLoadManyWithEmpty()
    {
        $identifiers = $this->getRandomIdentifiers();

        foreach ($identifiers as $identifier) {
            $this->saveInCache($identifier);
        }

        $nbFalseKeys = 0;

        foreach (range(1, rand(2, 4)) as $i) {
            $identifiers[] = 'not_existed_id_' . $i;
            ++$nbFalseKeys;
        }

        $falseKeys = array_keys($this->driver->loadMany($identifiers), false);

        $this->assertNotEmpty($falseKeys);
        $this->assertEquals($nbFalseKeys, count($falseKeys));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::remove()
     */
    public function testRemove()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));
        $this->assertTrue($this->driver->remove($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::removeByTags()
     */
    public function testRemoveByTags()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));
        $this->assertTrue($this->driver->removeByTags(array($this->cacheIdentifiersTags[$identifier])));
        $this->assertFalse($this->driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::touch()
     */
    public function testTouch()
    {
        $now        = time();
        $touchTime  = 300;
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));
        $this->assertTrue($this->driver->touch($identifier, $touchTime));

        $obj     = new \ReflectionObject($this->driver);
        $method0 = $obj->getMethod('doLoadRaw');
        $method1 = $obj->getMethod('getPrefixedIdentifier');
        $method0->setAccessible(true);
        $method1->setAccessible(true);

        $cacheData = $method0->invoke($this->driver, $method1->invoke($this->driver, $identifier));

        if (is_string($cacheData)) {
            $cacheData = unserialize($cacheData);
        }

        $this->assertInternalType('array', $cacheData);
        $this->assertArrayHasKey('expiresAt', $cacheData);
        $this->assertEquals($cacheData['expiresAt'] - $touchTime, $now);
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::increment()
     */
    public function testIncrement()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertEquals(1, $this->driver->increment($identifier));
        $this->assertEquals(3, $this->driver->increment($identifier, 2));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::increment()
     */
    public function testIncrementNotExisted()
    {
        $this->assertEquals(10, $this->driver->increment('not_existed_inc', 10));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::decrement()
     */
    public function testDecrement()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertEquals(5, $this->driver->increment($identifier, 5));
        $this->assertEquals(2, $this->driver->decrement($identifier, 3));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::decrement()
     */
    public function testDecrementNotExisted()
    {
        $this->assertEquals(-10, $this->driver->decrement('not_existed_dec', 10));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::contains()
     */
    public function testContains()
    {
        $identifier = $this->getRandomIdentifier();

        $this->assertTrue($this->saveInCache($identifier));
        $this->assertTrue($this->driver->contains($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::flush()
     */
    public function testFlush()
    {
        $this->assertTrue($this->driver->flush());
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->driver = $this->getDriver();

        if (null === $this->driver) {
            $this->markTestSkipped('Cache driver is empty');
        } else {
            $this->generateIdentifiers();
        }
    }

    /**
     * @param  string $identifier
     * @return string
     */
    protected function getDataForIdentifier($identifier)
    {
        return 'data_' . $identifier;
    }

    /**
     * Returns random cache identifier.
     *
     * @return string
     */
    protected function getRandomIdentifier()
    {
        return $this->cacheIdentifiers[array_rand($this->cacheIdentifiers)];
    }

    /**
     * Returns array with random identifiers.
     *
     * @return array
     */
    protected function getRandomIdentifiers()
    {
        $identifiers   = array();
        $nbIdentifiers = rand(1, floor(count($this->cacheIdentifiers) / 2));

        while ($nbIdentifiers > 0) {
            $identifier = $this->getRandomIdentifier();

            if (!in_array($identifier, $identifiers)) {
                $identifiers[] = $identifier;
                --$nbIdentifiers;
            }
        }

        return $identifiers;
    }

    /**
     * Saves entry in cache.
     *
     * @param  string  $identifier
     * @return boolean
     */
    protected function saveInCache($identifier)
    {
        return $this->driver->save(
            $this->getDataForIdentifier($identifier),
            $identifier,
            array($this->cacheIdentifiersTags[$identifier]),
            $this->lifetime
        );
    }

    /**
     * Generates list of random cache identifiers.
     */
    protected function generateIdentifiers()
    {
        for ($i = 0; $i < rand(10, 20); $i++) {
            $id              = sprintf('%04d', rand(1, 9999));
            $cacheIdentifier = 'cache_' . $id;

            $this->cacheIdentifiers[]                     = $cacheIdentifier;
            $this->cacheIdentifiersTags[$cacheIdentifier] = 'tag_' . $id;
        }
    }

    /**
     * Abstract method that returns driver for testing.
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    abstract protected function getDriver();
}
