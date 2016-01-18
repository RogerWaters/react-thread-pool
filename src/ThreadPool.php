<?php
namespace RogerWaters\ReactThreads;
use InvalidArgumentException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use React\Socket\ServerInterface;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 18.01.2016
 * Time: 16:44
 */
class ThreadPool
{
    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var Thread[]
     */
    protected $threads = array();

    /**
     * @var callable[]
     */
    protected $cleanupFunctions = array();

    /**
     * @var ThreadPoolServer
     */
    protected $server;

    /**
     * @var int
     */
    protected $createdPid;

    /**
     * ThreadPool constructor.
     * @param LoopInterface $loop
     * @param int $port
     * @throws \React\Socket\ConnectionException
     */
    public function __construct(LoopInterface $loop,$port)
    {
        $this->loop = $loop;
        $this->createdPid = posix_getpid();
        $this->server = new ThreadPoolServer($loop,$port);
    }

    public function OnThreadConnected($id,ConnectionInterface $connection)
    {

    }

    /**
     * @param Thread $thread
     */
    public function StartThread(Thread $thread)
    {
        return $thread->Start($this);
    }

    /**
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @param callable $cleanupFunc
     * @return int
     */
    public function RegisterAfterForkCleanup(callable $cleanupFunc)
    {
        $this->cleanupFunctions[] = $cleanupFunc;
        return count($this->cleanupFunctions)-1;
    }

    /**
     * @param int $id
     * @throws InvalidArgumentException
     */
    public function UnRegisterAfterForkCleanup($id)
    {
        if(isset($this->cleanupFunctions[$id]) === false)
        {
            throw new InvalidArgumentException("The given Cleanup function Id $id was not found. Maybe it was unregistered befor?");
        }
        unset($this->cleanupFunctions[$id]);
    }

    public function AfterFork()
    {
        if($this->createdPid !== posix_getpid())
        {
            foreach ($this->cleanupFunctions as $id => $cleanupFunction)
            {
                // allow all forked Threads to free memory not used by another thread
                $cleanupFunction($id);
            }

            $this->loop = $this->loop->AfterFork();
        }
        return $this->loop;
    }
}