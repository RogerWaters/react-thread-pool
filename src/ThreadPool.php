<?php
namespace RogerWaters\ReactThreads;
use InvalidArgumentException;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
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
     * @var ForkableLoopInterface
     */
    protected $loop;

    /**
     * @var Thread[]
     */
    protected $threads = array();

    /**
     * @var ThreadPoolServer
     */
    protected $server;

    /**
     * @var int
     */
    private $port;

    /**
     * ThreadPool constructor.
     * @param ForkableLoopInterface $loop
     * @param int $port
     * @throws \React\Socket\ConnectionException
     */
    public function __construct(ForkableLoopInterface $loop,$port = 53535)
    {
        $this->loop = $loop;
        $this->createdPid = posix_getpid();
        $this->server = new ThreadPoolServer($loop,$port);
        $this->port = $port;
    }

    public function OnThreadConnected($id,ConnectionInterface $connection)
    {

    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    public function AddThread(ThreadBase $thread)
    {
        if($thread->IsRunning())
        {
            throw new InvalidArgumentException("The given Thread is already running! Add the thread before starting it.");
        }
        $thread->on('starting',function(ThreadBase $base)
        {
            $this->ThreadStarting($base);
        });
    }

    protected function ThreadStarting(ThreadBase $base)
    {

    }
}