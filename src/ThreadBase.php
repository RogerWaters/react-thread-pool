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

class ThreadBase
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
     * @var \SplQueue
     */
    protected $workList;

    public function __construct(ForkableLoopInterface $loop)
    {
        $this->workList = new \SplQueue();
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
                $this->InitializeExternal();
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

    protected function InitializeExternal()
    {
        $this->loop = $this->loop->afterForkChild();
        /** @var ThreadWork $work */
        while(($work = $this->workList->dequeue()) instanceof ThreadWork)
        {
            $work->DoWork($this,$this->loop);
        }
    }

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
                    echo "Done".PHP_EOL;
                    $this->running = false;
                    $timer->cancel();
                    $this->emit('stopped',array($this->childPid,$status,$this));
                    break;
                case 0: //donePid is empty
                    //everything fine.
                    //process still running
                    echo "Running".PHP_EOL;
                    break;
                case -1://donePid is unknown
                default:
                    echo "Error".PHP_EOL;
                    $this->emit('error',array(new \RuntimeException("$this->childPid PID returned unexpected status. Maybe its not a child of this.")));
                    break;
            }
        });
        //free worklist internal as works will be performed external
        $this->workList = new \SplQueue();
    }

    /**
     * @return bool
     */
    public function IsRunning()
    {
        return $this->running;
    }

    /**
     * @param ThreadWork $work
     * @return $this
     */
    public function EnQueueWork(ThreadWork $work)
    {
        if($this->IsRunning())
        {
            throw new \InvalidArgumentException("Cannot attach work while thread is running. Ether wait for thread to complete enqueue and start again or create an new thread.");
        }
        $this->workList->enqueue($work);
        return $this;
    }
}