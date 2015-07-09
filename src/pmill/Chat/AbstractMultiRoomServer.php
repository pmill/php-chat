<?php
namespace pmill\Chat;

use pmill\Chat\Exception\ConnectedClientNotFoundException;
use pmill\Chat\Exception\InvalidActionException;
use pmill\Chat\Exception\MissingActionException;
use pmill\Chat\Interfaces\ConnectedClientInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

abstract class AbstractMultiRoomServer implements MessageComponentInterface
{

    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_MESSAGE_RECEIVED = 'message';
    const ACTION_LIST_USERS = 'list-users';
    const ACTION_USER_STARTED_TYPING = 'start-typing';
    const ACTION_USER_STOPPED_TYPING = 'stop-typing';

    const PACKET_TYPE_USER_CONNECTED = 'user-connected';
    const PACKET_TYPE_USER_DISCONNECTED = 'user-disconnected';
    const PACKET_TYPE_MESSAGE = 'message';
    const PACKET_TYPE_USER_LIST = 'list-users';
    const PACKET_TYPE_USER_STARTED_TYPING = 'user-started-typing';
    const PACKET_TYPE_USER_STOPPED_TYPING = 'user-stopped-typing';

    /**
     * @param AbstractMultiRoomServer $chatServer
     * @param int $port
     * @param string $ip
     * @return IoServer
     */
    public static function run(AbstractMultiRoomServer $chatServer, $port, $ip='0.0.0.0')
    {
        $wsServer = new WsServer($chatServer);
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
     * @var array|ConnectedClientInterface[]
     */
    protected $clients;

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserWelcomeMessage(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserConnectedMessage(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $client
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeUserDisconnectedMessage(ConnectedClientInterface $client, $timestamp);

    /**
     * @param ConnectedClientInterface $from
     * @param string $message
     * @param int $timestamp
     * @return string
     */
    abstract protected function makeMessageReceivedMessage(ConnectedClientInterface $from, $message, $timestamp);

    /**
     * @param ConnectedClientInterface $from
     * @param string $message
     * @param int $timestamp
     * @return string
     */
    abstract protected function logMessageReceived(ConnectedClientInterface $from, $message, $timestamp);

    /**
     * @param ConnectionInterface $conn
     * @param $name
     * @return ConnectedClientInterface
     */
    abstract protected function createClient(ConnectionInterface $conn, $name);

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

        if (!isset($msg['action'])) {
            throw new MissingActionException('No action specified');
        }

        if ($msg['action'] != self::ACTION_USER_CONNECTED) {
            $client = $this->findClient($conn);
            $roomId = $this->findClientRoom($client);
        }

        switch ($msg['action']) {
            case self::ACTION_USER_CONNECTED:
                $roomId = $this->makeRoom($msg['roomId']);
                $client = $this->createClient($conn, $msg['userName']);
                $this->connectUserToRoom($client, $roomId);
                $this->sendUserConnectedMessage($client, $roomId);
                $this->sendUserWelcomeMessage($client, $roomId);
                $this->sendListUsersMessage($client, $roomId);
                break;
            case self::ACTION_LIST_USERS:
                $this->sendListUsersMessage($client, $roomId);
                break;
            case self::ACTION_MESSAGE_RECEIVED:
                $msg['timestamp'] = isset($msg['timestamp']) ? $msg['timestamp'] : time();
                $this->logMessageReceived($client, $roomId, $msg['message'], $msg['timestamp']);
                $this->sendMessage($client, $roomId, $msg['message'], $msg['timestamp']);
                $this->sendUserStoppedTypingMessage($client, $roomId);
                break;
            case self::ACTION_USER_STARTED_TYPING:
                $this->sendUserStartedTypingMessage($client, $roomId);
                break;
            case self::ACTION_USER_STOPPED_TYPING:
                $this->sendUserStoppedTypingMessage($client, $roomId);
                break;
            default: throw new InvalidActionException('Invalid action: '.$msg['action']);
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->closeClientConnection($conn);
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->closeClientConnection($conn);
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
     * @return array|ConnectedClientInterface[]
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @param array|ConnectedClientInterface[] $clients
     */
    public function setClients($clients)
    {
        $this->clients = $clients;
    }

    /**
     * @param ConnectionInterface $conn
     * @throws ConnectedClientNotFoundException
     */
    protected function closeClientConnection(ConnectionInterface $conn)
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
     * @return ConnectedClientInterface
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
     * @param ConnectedClientInterface $client
     * @param $roomId
     * @param $message
     * @param $timestamp
     */
    protected function sendMessage(ConnectedClientInterface $client, $roomId, $message, $timestamp)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_MESSAGE,
            'from'=>$client->asArray(),
            'timestamp'=>$timestamp,
            'message'=>$this->makeMessageReceivedMessage($client, $message, $timestamp),
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserConnectedMessage(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'message'=>$this->makeUserConnectedMessage($client, time()),
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserWelcomeMessage(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_CONNECTED,
            'timestamp'=>time(),
            'message'=>$this->makeUserWelcomeMessage($client, time()),
        );

        $this->sendData($client, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserDisconnectedMessage(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_DISCONNECTED,
            'timestamp'=>time(),
            'message'=>$this->makeUserDisconnectedMessage($client, time()),
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserStartedTypingMessage(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_STARTED_TYPING,
            'from'=>$client->asArray(),
            'timestamp'=>time(),
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserStoppedTypingMessage(ConnectedClientInterface $client, $roomId)
    {
        $dataPacket = array(
            'type'=>self::PACKET_TYPE_USER_STOPPED_TYPING,
            'from'=>$client->asArray(),
            'timestamp'=>time(),
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendListUsersMessage(ConnectedClientInterface $client, $roomId)
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
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $this->rooms[$roomId][$client->getResourceId()] = $client;
        $this->clients[$client->getResourceId()] = $client;
    }

    /**
     * @param $roomId
     * @return array|ConnectedClientInterface[]
     */
    protected function findRoomClients($roomId)
    {
        return $this->rooms[$roomId];
    }

    /**
     * @param ConnectedClientInterface $client
     * @return int|string
     * @throws ConnectedClientNotFoundException
     */
    protected function findClientRoom(ConnectedClientInterface $client)
    {
        foreach ($this->rooms AS $roomId=>$roomClients) {
            if (isset($roomClients[$client->getResourceId()])) {
                return $roomId;
            }
        }

        throw new ConnectedClientNotFoundException($client->getResourceId());
    }

    /**
     * @param ConnectedClientInterface $client
     * @param array $packet
     */
    protected function sendData(ConnectedClientInterface $client, array $packet)
    {
        $client->getConnection()->send(json_encode($packet));
    }

    /**
     * @param array|ConnectedClientInterface[] $clients
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