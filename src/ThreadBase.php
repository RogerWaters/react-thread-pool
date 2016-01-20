<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 20.01.2016
 * Time: 08:47
 */

namespace RogerWaters\ReactThreads;

use Evenement\EventEmitterTrait;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;

class ThreadBase
{
    use EventEmitterTrait;

    /**
     * @var ForkableLoopInterface
     */
    protected $loop;

    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @var int|null
     */
    protected $childPid = null;

    public function __construct(ForkableLoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function start()
    {
        if($this->running === false)
        {
            $this->childPid = $this->fork();
            $this->running = true;
        }
    }

    protected function fork()
    {
        $pid = pcntl_fork();
        if($pid <= -1 )
        {
            throw new \RuntimeException("Unable to fork child: ".pcntl_strerror(pcntl_get_last_error()));
        }
        elseif($pid === 0)
        {
            //child proc
            $this->InitializeExternal();
            exit();
        }
        else
        {
            $this->InitializeInternal();
            return $pid;
        }
    }

    protected function InitializeExternal()
    {
        $this->loop = $this->loop->afterForkChild();
        $this->ConstructExternal();
    }

    protected function InitializeInternal()
    {
        $this->loop->afterForkParent();
        $this->loop->addPeriodicTimer(5,function()
        {
            $donePid = pcntl_waitpid($this->childPid, $status, WNOHANG | WUNTRACED);
            switch($donePid)
            {
                case $this->childPid: //donePid is child pid
                    //child done
                    $this->running = false;
                    $this->emit('stopped',array($this->childPid,$status));
                    break;
                case 0: //donePid is empty
                    //everything fine.
                    //process still running
                    break;
                case -1://donePid is unknown
                default:
                    $this->emit('error',array(new \RuntimeException("$this->childPid PID returned unexpected status. Maybe its not a child of this.")));
                    break;
            }
        });
    }

    protected function ConstructExternal()
    {
        $counter = 0;
        $this->loop->addPeriodicTimer(5,function() use (&$counter)
        {
            echo $counter++." Message from: ".posix_getpid().PHP_EOL;
            if($counter >= 10)
            {
                echo "This was the last message from: ".posix_getpid().' stopping now'.PHP_EOL;
                $this->loop->stop();
            }
        });

        $this->loop->run();
    }
}