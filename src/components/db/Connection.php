<?php

namespace app\components\db;

/**
 * Class Connection.
 */
class Connection extends \yii\db\Connection
{
    /**
     * @var string|null
     */
    public $db_id;

    /**
     * @var string
     */
    protected $connection_id;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        $this->connection_id = Manager::generateConnectionId($this);
    }

    /**
     * @return string
     */
    public function getConnectionId(): string
    {
        return $this->connection_id;
    }
}
