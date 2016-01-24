<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 16:15
 */
use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableLibEventLoop;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadWork;

include ('./../vendor/autoload.php');
/*include ('./../src/ThreadBase.php');
include ('./../src/ThreadPool.php');
include ('./../src/Thread.php');
include ('./../src/ThreadPoolServer.php');
include ('./../src/Protocol/BinaryBuffer.php');
include ('./../src/EventLoop/ForkableLoopInterface.php');
include ('./../src/EventLoop/ForkableLibEventLoop.php');
include ('./../src/ThreadWork.php');
*/
class TestWork extends ThreadWork
{
    protected $workBefore;
    protected $result = 0;
    public function __construct(TestWork $workBefore = null)
    {
        $this->workBefore = $workBefore;
    }

    public function DoWork(ThreadBase $base, \RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface $loop)
    {
        if($this->workBefore instanceof TestWork)
        {
            $this->result = $this->workBefore->result;
        }
        $counter = 0;
        for($i = 0; $i < 10; $i++)
        {
            echo "Work Run: $i".PHP_EOL;
            sleep(1);
            $counter++;
        }
        $this->result += $counter;
        echo "Done calculation with result: $this->result".PHP_EOL;
    }
}

$loop = new ForkableLibEventLoop();
$thread = new ThreadBase($loop);



$work = new TestWork();

$thread->on('stopped',function($threadPid, $status, ThreadBase $base) use ($work)
{
    echo "Thread ".get_class($base)." is done. Restarting...".PHP_EOL;
    $base->EnQueueWork($work);
    $base->start();
});


$thread->EnQueueWork($work)
    ->EnQueueWork(($work = new TestWork($work)))
    ->EnQueueWork(($work = new TestWork($work)));
$thread->start();

$loop->run();