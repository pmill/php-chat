<?php
namespace pmill\Chat;

use pmill\Chat\Interfaces\ConnectedClientInterface;
use Ratchet\ConnectionInterface;

class ConnectedClient implements ConnectedClientInterface
{

    /**
     * @var mixed
     */
    protected $resourceId;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var string
     */
    protected $name;

    /**
     * @return mixed
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * @param mixed $resourceId
     */
    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function asArray()
    {
        return array(
            'name'=>$this->name,
        );
    }

}