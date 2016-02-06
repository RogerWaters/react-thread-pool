<?php
namespace RogerWaters\ReactThreads;
use InvalidArgumentException;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\Protocol\AsyncMessage;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 18.01.2016
 * Time: 16:44
 */
class LoadBalancer
{
    /**
     * @var ForkableLoopInterface
     */
    private $loop;

    /**
     * @var string
     */
    private $threadClass;

    /**
     * @var ThreadBase[]
     */
    private $threadsLazy = array();

    /**
     * @var ThreadBase[]
     */
    private $threadsWorking = array();

    /**
     * @var int
     */
    private $minimumNumberOfThreads;

    /**
     * @var int
     */
    private $maximumNumberOfThreads;
    /**
     * @var int
     */
    private $lazyThreadTimeoutSec;

    /**
     * Initialize the pool and create
     * @param ForkableLoopInterface $loop
     * @param string $threadClass
     * @param $minimumNumberOfThreads
     * @param $maximumNumberOfThreads
     * @param int $lazyThreadTimeoutSec
     */
    public function __construct(ForkableLoopInterface $loop, $threadClass, $minimumNumberOfThreads, $maximumNumberOfThreads = 32, $lazyThreadTimeoutSec = 60)
    {
        $this->loop = $loop;
        if (in_array(ThreadBase::class, class_parents($threadClass)) === false)
        {
            throw new InvalidArgumentException("Given thread class '$threadClass' must extend " . ThreadBase::class);
        }

        $this->threadClass = $threadClass;
        $this->setMinimumNumberOfThreads($minimumNumberOfThreads);
        $this->setMaximumNumberOfThreads($maximumNumberOfThreads);
        $this->setLazyThreadTimeoutSec($lazyThreadTimeoutSec);
    }

    /**
     * @return string
     */
    public function getThreadClass()
    {
        return $this->threadClass;
    }

    /**
     * @return int
     */
    public function getMinimumNumberOfThreads()
    {
        return $this->minimumNumberOfThreads;
    }

    /**
     * @return int
     */
    public function getMaximumNumberOfThreads()
    {
        return $this->maximumNumberOfThreads;
    }

    /**
     * @param int $minimumNumberOfThreads
     */
    public function setMinimumNumberOfThreads($minimumNumberOfThreads)
    {
        if ($minimumNumberOfThreads < 1)
        {
            throw new InvalidArgumentException("minimumNumberOfThreads has to be greater than 0");
        }

        $this->minimumNumberOfThreads = $minimumNumberOfThreads;

        $this->CheckThreadLimits();
    }

    /**
     * @param int $maximumNumberOfThreads
     */
    public function setMaximumNumberOfThreads($maximumNumberOfThreads)
    {
        if ($maximumNumberOfThreads < $this->minimumNumberOfThreads)
        {
            throw new InvalidArgumentException("maximumNumberOfThreads has to be greater or equal than minimumNumberOfThreads");
        }
        $this->maximumNumberOfThreads = $maximumNumberOfThreads;

        $this->CheckThreadLimits();
    }

    /**
     * @return int
     */
    public function getLazyThreadTimeoutSec()
    {
        return $this->lazyThreadTimeoutSec;
    }

    /**
     * @param int $lazyThreadTimeoutSec
     */
    public function setLazyThreadTimeoutSec($lazyThreadTimeoutSec)
    {
        if ($this->lazyThreadTimeoutSec < 0)
        {
            throw new InvalidArgumentException("lazyThreadTimeoutSec must be at least 0");
        }
        $this->lazyThreadTimeoutSec = $lazyThreadTimeoutSec;

        $this->CheckThreadLimits();
    }

    protected function CheckThreadLimits()
    {
        while ($this->getNumberOfThreads() > $this->maximumNumberOfThreads && count($this->threadsLazy) > 0) {
            /** @var ThreadBase $thread */
            $thread = array_pop($this->threadsLazy);
            $thread->kill();
        }

        $threadsTimedOut = $this->threadsLazy;

        //remove unused threads
        foreach ($threadsTimedOut as $id => $thread) {
            if ($this->getNumberOfThreads() > $this->minimumNumberOfThreads) {
                if ($thread->getSecondsSinceLastMessage() > $this->lazyThreadTimeoutSec) {
                    $thread->kill();
                    unset($this->threadsLazy[$id]);
                }
            } else {
                //minimum reached
                break;
            }
        }

        //create threads 2 threads are preloaded
        while ($this->getNumberOfThreads() < $this->minimumNumberOfThreads || ($this->getNumberOfThreads() < $this->maximumNumberOfThreads && $this->getNumberOfThreadsLazy() < 2)) {
            $thread = $this->createThreadRunning();
            $this->threadsLazy[spl_object_hash($thread)] = $thread;
        }
    }

    public function getNumberOfThreads()
    {
        return count($this->threadsLazy) + count($this->threadsWorking);
    }

    public function getNumberOfThreadsLazy()
    {
        return count($this->threadsLazy);
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->threadClass, $name)) {
            $threadToCall = array_pop($this->threadsLazy);
            if ($threadToCall === null) {
                if ($this->getNumberOfThreads() >= $this->maximumNumberOfThreads) {
                    throw new \RuntimeException("Maximum number of threads reached. Increase the maximum or limit your calls");
                } else {
                    $threadToCall = $this->createThreadRunning();
                }
            }
            $result = call_user_func_array(array($threadToCall, $name), $arguments);
            //is async?
            if ($result instanceof AsyncMessage && false === $result->isIsResolved()) {
                $originalCallback = $result->getResolvedCallback();
                //wrap message callback to handle multiple messages at once
                $newCallback = function (AsyncMessage $message) use ($originalCallback, $threadToCall) {
                    if ($message->isIsResolved()) {
                        unset($this->threadsWorking[spl_object_hash($threadToCall)]);
                        $this->threadsLazy[spl_object_hash($threadToCall)] = $threadToCall;

                        $this->CheckThreadLimits();

                        if (count($this->threadsWorking) <= 0 && $this->lazyThreadTimeoutSec > 0) {
                            $this->loop->addTimer($this->lazyThreadTimeoutSec, function () {
                                $this->CheckThreadLimits();
                            });
                        }
                    }

                    if (is_callable($originalCallback)) {
                        $originalCallback($message);
                    }
                };

                $result->setResolvedCallback($newCallback);

                $this->threadsWorking[spl_object_hash($threadToCall)] = $threadToCall;

                $this->CheckThreadLimits();
            } else {
                $this->threadsLazy[spl_object_hash($threadToCall)] = $threadToCall;
            }
        }
        else
        {
            throw new InvalidArgumentException("Method $this->threadClass::$name does not exists or is not accessable");
        }
    }

    /**
     * @return ThreadBase
     */
    protected function createThreadRunning()
    {
        $class = $this->threadClass;
        /** @var ThreadBase $thread */
        $thread = new $class($this->loop);
        $thread->start();
        return $thread;
    }
}