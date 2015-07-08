<?php

class ConnectedClientTest extends PHPUnit_Framework_TestCase
{

    public function testSetResourceId()
    {
        $client = new \pmill\Chat\ConnectedClient;
        $client->setResourceId(1);

        $this->assertEquals(1, $client->getResourceId());
    }

    public function testSetName()
    {
        $client = new \pmill\Chat\ConnectedClient;
        $client->setName('name');

        $this->assertEquals('name', $client->getName());
    }

    public function testSetConnection()
    {
        $connection = $this->getMock('Ratchet\ConnectionInterface');

        $client = new \pmill\Chat\ConnectedClient;
        $client->setConnection($connection);

        $this->assertEquals($connection, $client->getConnection());
    }

}