<?php
namespace pmill\Chat\Interfaces;

use Ratchet\ConnectionInterface;

interface ConnectedClientInterface
{

    /**
     * @return mixed
     */
    public function getResourceId();

    /**
     * @param mixed $resourceId
     */
    public function setResourceId($resourceId);

    /**
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection($connection);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     */
    public function setName($name);

    /**
     * @return array
     */
    public function asArray();

}