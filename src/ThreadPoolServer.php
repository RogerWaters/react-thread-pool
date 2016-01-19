<?php
namespace RogerWaters\ReactThreads;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use React\Socket\ServerInterface;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 18.01.2016
 * Time: 17:48
 */
class ThreadPoolServer implements MessageComponentInterface
{
    /**
     * @var Server
     */
    protected $socket;
    /**
     * @var IoServer
     */
    protected $ioServer;

    /**
     * @var ConnectionInterface[]
     */
    protected $threadsWaitingForAssignment;

    /**
     * ThreadPoolServer constructor.
     * @param LoopInterface $loop
     * @param $port
     */
    public function __construct(LoopInterface $loop, $port)
    {
        $this->socket = new Server($loop);
        $this->ioServer = new IoServer(new HttpServer(new WsServer($this)),$this->socket);
        $this->socket->listen($port);
        $this->threadsWaitingForAssignment = new \SplObjectStorage();
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->threadsWaitingForAssignment->attach($conn);
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        if($this->threadsWaitingForAssignment->contains($conn))
        {
            $this->threadsWaitingForAssignment->detach($conn);
        }
        else
        {

        }
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        // TODO: Implement onError() method.
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // TODO: Implement onMessage() method.
    }
}