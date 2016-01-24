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
     * ThreadPool constructor.
     * @param ForkableLoopInterface $loop
     * @throws \React\Socket\ConnectionException
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
                    $this->OnThreadConnected($message['parameters'][0],$threadConnection);
                }
                else
                {
                    //invalid header received close connection!
                    $threadConnection->close();
                }
            });
            $threadConnection->once('close',function() use ($threadConnection)
            {
                if($threadConnection->GetId() !== null)
                {
                    foreach ($this->threads as $thread)
                    {
                        if($thread->getId() === $thread->getId())
                        {
                            //make sure process going down
                            $thread->Kill();
                            $this->threads->detach($thread);

                            unset($this->connections[$threadConnection->GetId()]);
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

    protected function OnThreadConnected($id,ThreadConnection $connection)
    {
        foreach ($this->threads as $thread)
        {
            if($thread->getId() === $id)
            {
                $connection->SetId($id);
                $this->connections[$connection->GetId()] = $connection;
                $connection->on('message',function(ThreadConnection $connectino, $messageData) use ($id)
                {
                    $callback = $this->callbacks[$id];
                    $callback($messageData['action'],$messageData['parameters']);
                });
            }
        }
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function RegisterThread(ClientThread $thread,$messageCallback)
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
    public function SendToClient(ClientThread $thread, array $message)
    {
        if($this->IsConnected($thread))
        {
            $this->connections[$thread->getId()]->Write($message);
        }
        else
        {
            throw new InvalidArgumentException("The given Thread is not connected at this moment.");
        }
    }

    /**
     * @param ClientThread $thread
     * @return bool
     */
    public function IsConnected(ClientThread $thread)
    {
        return isset($this->connections[$thread->getId()]);
    }
}