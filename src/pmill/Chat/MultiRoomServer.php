<?php
namespace pmill\Chat;

use pmill\Chat\Exception\ConnectedClientNotFoundException;
use pmill\Chat\Exception\InvalidActionException;
use pmill\Chat\Exception\MissingActionException;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class MultiRoomServer implements MessageComponentInterface
{

    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_MESSAGE_RECEIVED = 'message';
    const ACTION_LIST_USERS = 'list-users';

    const PACKET_TYPE_USER_CONNECTED = 'user-connected';
    const PACKET_TYPE_USER_DISCONNECTED = 'user-disconnected';
    const PACKET_TYPE_MESSAGE = 'message';
    const PACKET_TYPE_USER_LIST = 'list-users';

    /**
     * @param int $port
     * @param string $ip
     * @return IoServer
     */
    public static function run($port, $ip='0.0.0.0')
    {
        $thisServer = new MultiRoomServer;
        $wsServer = new WsServer($thisServer);
        $http = new HttpServer($wsServer);
        $server = IoServer::factory($http, $port, $ip);
        $server->run();
        return $server;
    }

    /**
     * @var array
     */
    protected $rooms;

    /**
     * @var array|ConnectedClient[]
     */
    protected $clients;

    /**
     * @var string
     */
    protected $userConnectedMessageTemplate = '%s has connected';

    /**
     * @var string
     */
    protected $userDisconnectedMessageTemplate = '%s has left';

    /**
     * @var string
     */
    protected $userWelcomeMessageTemplate = 'Welcome %s!';

    public function __construct()
    {
        $this->rooms = array();
        $this->clients = array();
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {

    }

    /**
     * @param ConnectionInterface $conn
     * @param string $msg
     * @throws ConnectedClientNotFoundException
     * @throws InvalidActionException
     * @throws MissingActionException
     */
    public function onMessage(ConnectionInterface $conn, $msg)
    {
        echo "Packet received: ".$msg.PHP_EOL;
        $msg = json_decode($msg, true);
        $roomId = $this->makeRoom($msg['roomId']);

        if (!isset($msg['action'])) {
            throw new MissingActionException('No action specified');
        }

        switch ($msg['action']) {
            case self::ACTION_USER_CONNECTED:
                $userName = $msg['userName'];
                $client = $this->createClient($conn, $userName);
                $this->connectUserToRoom($client, $roomId);
                $this->sendUserConnectedMessage($client, $roomId);
                $this->sendUserWelcomeMessage($client, $roomId);
                $this->sendListUsersMessage($client, $roomId);
                break;
            case self::ACTION_LIST_USERS:
                $client = $this->findClient($conn);
                $this->sendListUsersMessage($client, $roomId);
                break;
            case self::ACTION_MESSAGE_RECEIVED:
                $msg['timestamp'] = isset($msg['timestamp']) ? $msg['timestamp'] : time();
                $client = $this->findClient($conn);
                $this->sendMessage($client, $roomId, $msg['message'], $msg['timestamp']);
                break;
            default: throw new InvalidActionException('Invalid action: '.$msg['action']);
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        $client = $this->findClient($conn);

        unset($this->clients[$client->getResourceId()]);
        foreach ($this->rooms AS $roomId=>$connectedClients) {
            if (isset($connectedClients[$client->getResourceId()])) {
                $clientRoomId = $roomId;
                unset($this->rooms[$roomId][$client->getResourceId()]);
            }
        }

        if (isset($clientRoomId)) {
            $this->sendUserDisconnectedMessage($client, $clientRoomId);
        }
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    /**
     * @return array
     */
    public function getRooms()
    {
        return $this->rooms;
    }

    /**
     * @param array $rooms
     */
    public function setRooms($rooms)
    {
        $this->rooms = $rooms;
    }

    /**
     * @return array|ConnectedClient[]
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @param array|ConnectedClient[] $clients
     */
    public function setClients($clients)
    {
        $this->clients = $clients;
    }

    /**
     * @param ConnectionInterface $conn
     * @param $name
     * @return ConnectedClient
     */
    protected function createClient(ConnectionInterface $conn, $name)
    {
        $client = new ConnectedClient;
        $client->setResourceId($conn->resourceId);
        $client->setConnection($conn);
        $client->setName($name);

        return $client;
    }

    /**
     * @param ConnectionInterface $conn
     * @return ConnectedClient
     * @throws ConnectedClientNotFoundException
     */
    protected function findClient(ConnectionInterface $conn)
    {
        if (isset($this->clients[$conn->resourceId])) {
            return $this->clients[$conn->resourceId];
        }

        throw new ConnectedClientNotFoundException($conn->resourceId);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     * @param $message
     * @param $timestamp
     */
    protected function sendMessage(ConnectedClient $client, $roomId, $message, $timestamp)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_MESSAGE,
            'from'=>array(
                'name'=>$client->getName(),
            ),
            'timestamp'=>$timestamp,
            'message'=>$message,
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     */
    protected function sendUserConnectedMessage(ConnectedClient $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'message'=>vsprintf($this->userConnectedMessageTemplate, array($client->getName())),
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     */
    protected function sendUserWelcomeMessage(ConnectedClient $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'message'=>vsprintf($this->userWelcomeMessageTemplate, array($client->getName())),
        );

        $this->sendData($client, $dataPacket);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     */
    protected function sendUserDisconnectedMessage(ConnectedClient $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_DISCONNECTED,
            'timestamp'=>time(),
            'message'=>vsprintf($this->userDisconnectedMessageTemplate, array($client->getName())),
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     */
    protected function sendListUsersMessage(ConnectedClient $client, $roomId)
    {
        $clients = array();
        foreach ($this->findRoomClients($roomId) AS $roomClient) {
            $clients[] = array(
                'name'=>$roomClient->getName(),
            );
        }

        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_LIST,
            'timestamp'=>time(),
            'clients'=>$clients,
        );

        $this->sendData($client, $dataPacket);
    }

    /**
     * @param ConnectedClient $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClient $client, $roomId)
    {
        $this->rooms[$roomId][$client->getResourceId()] = $client;
        $this->clients[$client->getResourceId()] = $client;
    }

    /**
     * @param $roomId
     * @return array|ConnectedClient[]
     */
    protected function findRoomClients($roomId)
    {
        return $this->rooms[$roomId];
    }

    /**
     * @param ConnectedClient $client
     * @param array $packet
     */
    protected function sendData(ConnectedClient $client, array $packet)
    {
        $client->getConnection()->send(json_encode($packet));
    }

    /**
     * @param array|ConnectedClient[] $clients
     * @param array $packet
     */
    protected function sendDataToClients(array $clients, array $packet)
    {
        foreach ($clients AS $client) {
            $this->sendData($client, $packet);
        }
    }

    /**
     * @param $roomId
     * @return mixed
     */
    protected function makeRoom($roomId)
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = array();
        }

        return $roomId;
    }

}