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

    /**
     * @var bool
     */
    private $isExternal = false;

    /**
     * ThreadBase constructor.
     * @param ForkableLoopInterface $loop
     */
    public function __construct(ForkableLoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Create the external process and run the thread
     */
    public function start()
    {
        if($this->running === false)
        {
            $this->emit('starting',array($this));
            $this->childPid = $this->fork();
            $this->running = true;
        }
    }

    /**
     * will split the current process into to separated copies
     * @return int
     */
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
                $this->initializeExternal($this->loop);
            }
            catch (\Exception $e)
            {
                $this->emit('child_error',array($this,$e));
            }
            exit();
        }
        else
        {
            $this->initializeInternal();
            return $pid;
        }
    }

    /**
     * Implement your entire thread logic starting at this point
     * Function will be called with an working stable loop
     * @param ForkableLoopInterface $loop
     */
    protected abstract function initializeExternal(ForkableLoopInterface $loop);

    /**
     * Logic required to observe the process
     * Make sure no zombies on the floor
     * Checks if the process completed and requests the status code
     */
    protected function initializeInternal()
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
     * Check if the external process is running
     * @return bool
     */
    public function isRunning()
    {
        return $this->running;
    }

    /**
     * Force the external process to complete
     * The process gets immediately terminated
     * However it can take some time for the parent to consume the status
     */
    public function kill()
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
     * Check if current environment is on the child context or not
     * @return boolean
     */
    public function isExternal()
    {
        return $this->isExternal;
    }
}