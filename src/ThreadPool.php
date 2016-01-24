<?php
namespace RogerWaters\ReactThreads;
use InvalidArgumentException;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 18.01.2016
 * Time: 16:44
 */
class ThreadPool
{
    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var \SplObjectStorage|ClientThread[]
     */
    protected $threads;

    /**
     * @var callable[]
     */
    protected $callbacks = array();

    /**
     * @var ThreadConnection[]
     */
    private $connections = array();

    /**
     * Initialize the pool and create an endpoint + server to communicate with threads
     * @param ForkableLoopInterface $loop
     */
    public function __construct(ForkableLoopInterface $loop)
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($socket,0);
        $loop->addReadStream($socket,function($socket, ForkableLoopInterface $loop)
        {
            $conn = stream_socket_accept($socket);
            //server will write async messages
            $threadConnection = new ThreadConnection($loop,$conn,true);
            $threadConnection->once('message',function(ThreadConnection $threadConnection,array $message)
            {
                //expected header received
                if(isset($message['action'],$message['parameters']) && $message['action'] === '__connect')
                {
                    $this->onThreadConnected($message['parameters'][0], $threadConnection);
                }
                else
                {
                    //invalid header received close connection!
                    $threadConnection->close();
                }
            });
            $threadConnection->once('close',function() use ($threadConnection)
            {
                if ($threadConnection->getId() !== null)
                {
                    foreach ($this->threads as $thread)
                    {
                        if($thread->getId() === $thread->getId())
                        {
                            //make sure process going down
                            $thread->kill();
                            $this->threads->detach($thread);

                            unset($this->connections[$threadConnection->getId()]);
                            unset($this->callbacks[$thread->getId()]);
                            $threadConnection->removeAllListeners('message');
                            break;
                        }
                    }
                }
            });
        });

        $this->endpoint = 'tcp://'.stream_socket_get_name($socket,false);
        $this->threads = new \SplObjectStorage();
    }

    /**
     * Called when a thread first registers to the pool
     * This can be seen as a simple handshake between thread and pool
     * @param string $id
     * @param ThreadConnection $connection
     */
    protected function onThreadConnected($id, ThreadConnection $connection)
    {
        foreach ($this->threads as $thread)
        {
            if($thread->getId() === $id)
            {
                $connection->setId($id);
                $this->connections[$connection->getId()] = $connection;
                $connection->on('message',function(ThreadConnection $connectino, $messageData) use ($id)
                {
                    $callback = $this->callbacks[$id];
                    $callback($messageData['action'],$messageData['parameters']);
                });
            }
        }
    }

    /**
     * The endpoint created by system
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Called by the thread after created against this pool
     * This function should not be called anywhere else
     * @internal
     * @param ClientThread $thread
     * @param callable $messageCallback
     */
    public function registerThread(ClientThread $thread, callable $messageCallback)
    {
        if($this->endpoint !== $thread->getEndpoint())
        {
            throw new InvalidArgumentException("The given Thread is assigned to another ThreadPool already.");
        }
        if($this->threads->contains($thread))
        {
            throw new InvalidArgumentException("The Thread is already registered in this pool");
        }
        $this->threads->attach($thread);
        $this->callbacks[$thread->getId()] = $messageCallback;
    }

    /**
     * @internal
     * @param ClientThread $thread
     * @param array $message
     */
    public function sendToClient(ClientThread $thread, array $message)
    {
        if ($this->isConnected($thread))
        {
            $this->connections[$thread->getId()]->write($message);
        }
        else
        {
            throw new InvalidArgumentException("The given Thread is not connected at this moment.");
        }
    }

    /**
     * Checks if the thread is connected. Sometimes it can take some seconds
     * for the thread to connect. Also its possible that the thread stopped working
     * @param ClientThread $thread
     * @return bool
     */
    public function isConnected(ClientThread $thread)
    {
        return isset($this->connections[$thread->getId()]);
    }
}