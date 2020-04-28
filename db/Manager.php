<?php
/**
 * Project: mgkh-server
 * File: Manager.php
 * Author: Konstantin Budylov <k.budylov@gmail.com>
 * Date: 09.12.2019 13:44.
 */

namespace app\components\db;

use app\modules\mcrud\models\UkDb;
use yii\base\Component;
use yii\helpers\ArrayHelper;

/**
 * Class Manager.
 */
class Manager extends Component
{
    /**
     * Default driver for connections dsn.
     *
     * @var string
     */
    public $driver = 'pgsql';

    /**
     * Name for default database component.
     *
     * @var string
     */
    public $dbDefaultComponent = 'db';

    /**
     * Name for master database component.
     *
     * @var string
     */
    public $dbMasterComponent = 'db_master';

    /**
     * Name for shard database component.
     *
     * @var string
     */
    public $dbShardComponent = 'db_shard';

    /**
     * Automatically store $this->dbDefaultComponent to $this->dbMasterComponent on init.
     *
     * @var bool
     */
    public $autoStoreDefaultToMasterComponent = true;

    /**
     * Automatically switch $this->dbDefaultComponent to connection which is set as default.
     *
     * @var bool
     */
    public $switchOnChangeDefaultConnection = false;

    /**
     * Default configuration params for shard database connections.
     *
     * @var array
     */
    public $shardDbConnectionDefaults = [];

    /**
     * @var array
     */
    protected $shardDbConnectionsConfigs = [];

    /**
     * @var array
     */
    protected $openedConnections = [];

    /**
     * @var string|null
     */
    protected $defaultConnectionId;

    /**
     * @var int|null
     */
    protected $currentShardId;

    /**
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function init()
    {
        parent::init();
        /** @var \yii\db\Connection $db */
        $db = \Yii::$app->get($this->dbDefaultComponent);
        $this->setDefaultConnection($db);

        if ($this->autoStoreDefaultToMasterComponent)
        {
            $this->storeToComponent($db, $this->dbMasterComponent);
        }
    }

    /**
     * @param string $id
     */
    protected function setDefaultConnectionId(string $id)
    {
        $this->defaultConnectionId = $id;
    }

    /**
     * @param string $connection_id
     *
     * @return bool
     */
    public function isConnectionExists(string $connection_id): bool
    {
        return isset($this->openedConnections[$connection_id]);
    }

    /**
     * @param string $connection_id
     *
     * @return Connection
     *
     * @throws ManagerException
     */
    public function getConnectionById(string $connection_id): Connection
    {
        if ($this->isConnectionExists($connection_id))
        {
            return $this->openedConnections[$connection_id];
        }

        throw new ManagerException("Connection [$connection_id] is not exists");
    }

    /**
     * @return Connection
     *
     * @throws ManagerException
     */
    public function getDefaultConnection()
    {
        $id = $this->getDefaultConnectionId();

        if ($id)
        {
            return $this->getConnectionById($id);
        }
        else
        {
            throw new ManagerException('No default connection registered');
        }
    }

    /**
     * @return string|null
     */
    public function getDefaultConnectionId()
    {
        return $this->defaultConnectionId;
    }

    /**
     * @param bool $throwException
     *
     * @return object|null
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function getDefaultDbConnection($throwException = true)
    {
        $connection = \Yii::$app->get($this->dbDefaultComponent, $throwException);

        if (!$connection instanceof \yii\db\Connection)
        {
            throw new ManagerException("Component [$this->dbDefaultComponent] containing object that does not match required instance type");
        }

        return $connection;
    }

    /**
     * @param bool $throwException
     *
     * @return \yii\db\Connection|null
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function getMasterDbConnection($throwException = true)
    {
        $connection = \Yii::$app->get($this->dbMasterComponent, $throwException);

        if (!$connection instanceof \yii\db\Connection)
        {
            throw new ManagerException("Component [$this->dbMasterComponent] containing object that does not match required instance type");
        }

        return $connection;
    }

    /**
     * @param bool $throwException
     *
     * @return \yii\db\Connection|null
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function getShardDbConnection($throwException = true)
    {
        $connection = \Yii::$app->get($this->dbShardComponent, $throwException);

        if (!$connection instanceof \yii\db\Connection)
        {
            throw new ManagerException("Component [$this->dbShardComponent] containing object that does not match required instance type");
        }

        return $connection;
    }

    /**
     * @return mixed|string|null
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function getCurrentShardId()
    {
        $connection = $this->getShardDbConnection(false);

        if ($connection instanceof Connection)
        {
            return $connection->db_id;
        }

        return null;
    }

    /**
     * @param \yii\db\Connection $connection
     * @param string|null        $storeToComponent
     * @param bool               $setAsDefault
     *
     * @return Connection|\yii\db\Connection
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function addConnection(\yii\db\Connection $connection, string $storeToComponent = null, bool $setAsDefault = false)
    {
        $id = $this->storeConnection($connection);
        $connection = $this->getConnectionById($id);

        if ($storeToComponent)
        {
            $this->storeToComponent($connection, $storeToComponent);
        }

        if ($setAsDefault)
        {
            $this->setDefaultConnection($connection);
        }

        return $connection;
    }

    /**
     * @param int  $db_id
     * @param bool $autoconnect
     *
     * @return Connection
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function setShardConnectionById(int $db_id, bool $autoconnect = true)
    {
        return $this->createConnectionByDbId($db_id, $this->dbShardComponent, $autoconnect);
    }

    /**
     * @param \yii\db\Connection $connection
     *
     * @return \yii\db\Connection
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function setDefaultConnection(\yii\db\Connection $connection)
    {
        $connection_id = $this->storeConnection($connection);
        $this->setDefaultConnectionId($connection_id);
        $this->storeToComponent($connection, $this->dbDefaultComponent);

        return $connection;
    }

    /**
     * @param int         $db_id
     * @param string|null $setToComponent
     * @param bool        $autoconnect
     *
     * @return Connection
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function createConnectionByDbId(int $db_id, string $setToComponent = null, bool $autoconnect = true)
    {
        $this->loadShardList();

        if (!empty($this->shardDbConnectionsConfigs[$db_id]))
        {
            $connection = $this->createConnectionByParams(
                $this->shardDbConnectionsConfigs[$db_id]['dsn'],
                $this->shardDbConnectionsConfigs[$db_id]['username'],
                $this->shardDbConnectionsConfigs[$db_id]['password'],
                $this->shardDbConnectionsConfigs[$db_id]['charset'],
                $setToComponent,
                $autoconnect
            );
            $connection->db_id = $db_id;

            if ($setToComponent === $this->dbShardComponent)
            {
                $this->currentShardId = $db_id;
            }
            elseif ($setToComponent === $this->dbDefaultComponent)
            {
                $this->setDefaultConnection($connection);
            }

            return $connection;
        }
        else
        {
            throw new ManagerException("Database id [$db_id] not found");
        }
    }

    /**
     * @param string      $dsn
     * @param string      $username
     * @param string      $password
     * @param string|null $charset
     * @param string|null $setToComponent
     * @param bool        $autoconnect
     *
     * @return Connection
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function createConnectionByParams(string $dsn, string $username, string $password, string $charset = null, string $setToComponent = null, bool $autoconnect = true)
    {
        $connection = new Connection([
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
        ]);

        if ($autoconnect)
        {
            $connection->open();
        }
        $id = $this->storeConnection($connection);

        if ($setToComponent)
        {
            $this->storeToComponent($connection, $setToComponent);
        }

        return $connection;
    }

    /**
     * @param string $hostname
     * @param int    $port
     * @param string $database
     * @param null   $driver
     *
     * @return string
     */
    public function createDsnForConnectionParams(string $hostname, int $port, string $database, $driver = null): string
    {
        if (!$driver)
        {
            $driver = $this->driver;
        }

        return $driver . ':' . "host=$hostname;port=$port;dbname=$database";
    }

    /**
     * @param \yii\db\Connection $connection
     * @param string|null        $prefix
     *
     * @return string
     */
    public static function generateConnectionId(\yii\db\Connection $connection, string $prefix = null): string
    {
        return $prefix . md5(implode('|', [
                $connection->dsn, $connection->username, $connection->password, $connection->charset,
            ]));
    }

    /**
     * @param \yii\db\Connection $connection
     *
     * @return string
     */
    protected function storeConnection(\yii\db\Connection $connection)
    {
        if ($connection instanceof Connection)
        {
            $id = $connection->getConnectionId();
        }
        else
        {
            $id = static::generateConnectionId($connection, '__unhandled_instance_');
        }
        /** @var Connection $_conn */
        $_conn = $this->openedConnections[$id] ?? null;

        if ($_conn)
        {
            $_conn->close();
        }
        $this->openedConnections[$id] = $connection;

        return $id;
    }

    /**
     * @param \yii\db\Connection $connection
     * @param string             $componentName
     *
     * @throws \yii\base\InvalidConfigException
     */
    protected function storeToComponent(\yii\db\Connection $connection, string $componentName)
    {
        $db = \Yii::$app->get($componentName, false);

        if ($db && $db instanceof \yii\db\Connection)
        {
            $db->close();
        }
        \Yii::$app->set($componentName, $connection);
    }

    /**
     * @param Connection|null $masterDb
     *
     * @throws ManagerException
     * @throws \yii\base\InvalidConfigException
     */
    public function loadShardList(Connection $masterDb = null)
    {
        if (empty($this->shardDbConnectionsConfigs))
        {
            if (empty($masterDb))
            {
                $masterDb = $this->getMasterDbConnection();
            }
            $shardDbConnectionsConfigs = [];
            $uk_dbs = ArrayHelper::index(UkDb::find()->all($masterDb), 'id');
            /** @var UkDb $uk_db */
            foreach ($uk_dbs as $uk_db)
            {
                $shardDbConnectionsConfigs[$uk_db->id] = [
                    'dsn' => $this->createDsnForConnectionParams(
                        $this->shardDbConnectionDefaults['host'] ?? $uk_db->hostname,
                        $this->shardDbConnectionDefaults['port'] ?? $uk_db->port,
                        $uk_db->db_name),
                    'username' => $this->shardDbConnectionDefaults['username'] ?? $uk_db->username,
                    'password' => $this->shardDbConnectionDefaults['password'] ?? $uk_db->password,
                    'charset' => $this->shardDbConnectionDefaults['charset'] ?? $uk_db->charset,
                ];
            }
            $this->shardDbConnectionsConfigs = $shardDbConnectionsConfigs;
            /*
            $this->shardDbConnectionsConfigs = \Yii::$app->userContext->getCache()->getOrSet('db_manager.uk_dbs.list', function () use ($masterDb) {
                $shardDbConnectionsConfigs = [];
                $uk_dbs = ArrayHelper::index(UkDb::find()->all($masterDb), 'id');
                foreach ($uk_dbs as $uk_db)
                {
                    $shardDbConnectionsConfigs[$uk_db->id] = [
                        'dsn' => $this->createDsnForConnectionParams(
                            $this->shardDbConnectionDefaults['host'] ?? $uk_db->hostname,
                            $this->shardDbConnectionDefaults['port'] ?? $uk_db->port,
                            $uk_db->db_name),
                        'username' => $this->shardDbConnectionDefaults['username'] ?? $uk_db->username,
                        'password' => $this->shardDbConnectionDefaults['password'] ?? $uk_db->password,
                        'charset' => $this->shardDbConnectionDefaults['charset'] ?? $uk_db->charset,
                    ];
                }

                return $shardDbConnectionsConfigs;
            });
            */
        }
    }
}
