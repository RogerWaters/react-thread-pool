<?php

namespace RogerWaters\ReactThreads;
use React\EventLoop\LoopInterface;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 18.01.2016
 * Time: 16:44
 */
class Thread
{
    /**
     * @var ThreadPool
     */
    protected $pool;

    /**
     * @var int
     */
    protected $createdPid;

    /**
     * @var int
     */
    protected $cleanupFunctionId;

    /**
     * Setting up the Thread to be executable by the ThreadPool
     * @param ThreadPool $pool
     * @return int
     */
    public function Start(ThreadPool $pool)
    {
        $this->createdPid = posix_getpid();
        $this->pool = $pool;
        $this->cleanupFunctionId = $this->pool->RegisterAfterForkCleanup(function($id)
        {
            $this->pool = null;
            if($id === $this->cleanupFunctionId)
            {
                $this->FreeMemoryForAnotherThread();
            }
        });

        $pid = pcntl_fork();
        if($pid <= -1 )
        {
            throw new \RuntimeException("Unable to fork child: ".pcntl_strerror(pcntl_get_last_error()));
        }
        elseif($pid === 0)
        {
            //child proc
            $this->InitializeExternal();
            $loop = $this->pool->AfterFork();
            $this->DoWork($loop);
            exit();
        }
        else
        {
            $this->pool->AfterFork();
            return $pid;
        }
    }

    protected function FreeMemoryForAnotherThread()
    {

    }

    protected function InitializeExternal()
    {

    }

    protected function DoWork(LoopInterface $loop)
    {

    }

    /**
     * Destructor for the parent instance
     */
    function __destruct()
    {
        if($this->createdPid === posix_getpid())
        {
            $this->pool->UnRegisterAfterForkCleanup($this->cleanupFunctionId);
        }
    }
}