<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers\Pdo;

use Cache\Exception;
use Cache\Drivers\Pdo;

/**
 * PDO SQLite cache driver.
 */
class SQLite extends Pdo
{

    /**
     * {@inheritdoc}
     *
     * @param \PDO $dbh
     * @param string $prefix
     */
    public function __construct(\PDO $dbh, $prefix = 'cache_')
    {
        parent::__construct($dbh, $prefix);

        // Speed up SQLite transactions
        $this->dbh->exec('PRAGMA count_changes=false');
        $this->dbh->exec('PRAGMA journal_mode=OFF');
        $this->dbh->exec('PRAGMA synchronous=OFF');
        $this->dbh->exec('PRAGMA temp_store=MEMORY');
    }

    /**
     * {@inheritdoc}
     */
    public function buildStructure()
    {
        $this->transactionsEnabled = false;

        $this->dbh->beginTransaction();

        try {
            $this->executeQuery('DROP INDEX IF EXISTS \'' . $this->prefix .
                'tag_id_index\'');
            $this->executeQuery('DROP INDEX IF EXISTS \'' . $this->prefix .
                'tag_name_index\'');
            $this->executeQuery('DROP INDEX IF EXISTS \'' . $this->prefix .
                'cache_id_expire_index\'');
            $this->executeQuery('DROP TABLE IF EXISTS \'' . $this->prefix .
                'cache\'');
            $this->executeQuery('DROP TABLE IF EXISTS \'' . $this->prefix .
                'tag\'');

            $this->executeQuery(
                'CREATE TABLE \'' . $this->prefix . 'cache\' (' .
                    '\'id\' TEXT PRIMARY KEY, ' .
                    '\'data\' BLOB, '.
                    '\'mktime\' INTEGER,' .
                    '\'expire\' INTEGER)');
            $this->executeQuery(
                'CREATE TABLE \'' . $this->prefix .
                    'tag\' (\'name\' TEXT, \'id\' TEXT)');
            $this->executeQuery(
                'CREATE INDEX \'' . $this->prefix .
                    'tag_id_index\' ON \'' . $this->prefix . 'tag\'(\'id\')');
            $this->executeQuery(
                'CREATE INDEX \'' . $this->prefix .
                    'tag_name_index\' ON \'' . $this->prefix .
                        'tag\'(\'name\')');
            $this->executeQuery(
                'CREATE INDEX \'' . $this->prefix .
                    'cache_id_expire_index\' ON \'' . $this->prefix .
                        'cache\'(\'id\', \'expire\')');

            $this->dbh->commit();
        } catch (Exception $e) {
            $this->dbh->rollBack();
            $this->transactionsEnabled = true;

            throw $e;
        }

        $this->transactionsEnabled = true;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareIdentifiers()
    {
        foreach ($this->rawTables as $name) {
            $this->tables[$name] = $this->getQuotedIdentifier(
                $this->prefix . $name
            );
        }

        $this->fields = array_combine($this->rawFields, $this->rawFields);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $identifier
     * @return string
     */
    protected function getQuotedIdentifier($identifier)
    {
        return "'" . $identifier . "'";
    }

}
