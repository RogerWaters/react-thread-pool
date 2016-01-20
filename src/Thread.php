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
            if($id !== $this->cleanupFunctionId)
            {
                //if another thread gets forked destroy every ressource bound to this thread
                $this->FreeMemoryForAnotherThread();
            }
            else
            {

            }
        });

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