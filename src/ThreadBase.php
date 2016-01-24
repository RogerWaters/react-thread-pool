<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 20.01.2016
 * Time: 08:47
 */

namespace RogerWaters\ReactThreads;

use Evenement\EventEmitterTrait;
use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;

abstract class ThreadBase
{
    use EventEmitterTrait;

    /**
     * @var ForkableLoopInterface
     */
    private $loop;

    /**
     * @var bool
     */
    private $running = false;

    /**
     * @var int|null
     */
    private $childPid = null;

    private $isExternal = false;

    public function __construct(ForkableLoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function start()
    {
        if($this->running === false)
        {
            $this->emit('starting',array($this));
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
            try
            {
                $this->isExternal = true;
                $this->loop = $this->loop->afterForkChild();
                $this->InitializeExternal($this->loop);
            }
            catch (\Exception $e)
            {
                $this->emit('child_error',array($this,$e));
            }
            exit();
        }
        else
        {
            $this->InitializeInternal();
            return $pid;
        }
    }

    protected abstract function InitializeExternal(ForkableLoopInterface $loop);

    protected function InitializeInternal()
    {
        $this->loop->afterForkParent();
        $this->loop->addPeriodicTimer(5,function(TimerInterface $timer)
        {
            $donePid = pcntl_waitpid($this->childPid, $status, WNOHANG | WUNTRACED);
            switch($donePid)
            {
                case $this->childPid: //donePid is child pid
                    //child done
                    $this->running = false;
                    $timer->cancel();
                    $this->emit('stopped',array($this->childPid,$status,$this));
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

    /**
     * @return bool
     */
    public function IsRunning()
    {
        return $this->running;
    }

    public function Kill()
    {
        if($this->isExternal())
        {
            //stop process
            exit();
        }
        else
        {
            if($this->running)
            {
                posix_kill($this->childPid,SIGTERM);
            }
            //ignore if thread is not running
        }
    }

    /**
     * @return boolean
     */
    public function isExternal()
    {
        return $this->isExternal;
    }
}