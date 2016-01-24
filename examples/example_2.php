<?php
/**
 * Example shows how to control the ThreadBase
 */

use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;

include('./../vendor/autoload.php');

/**
 * class from example_1
 * Class EchoThread
 */
class EchoThread extends ThreadBase
{
    protected function InitializeExternal(ForkableLoopInterface $loop)
    {
        //your complicated work goes here
        for($i = 0; $i < 10; $i++)
        {
            echo "Doing complicated work $i / 10".PHP_EOL;
            sleep(1);
        }
        echo "After complete the Thread is closed automatically".PHP_EOL;
    }
}

$loop = ForkableFactory::create();
$thread = new EchoThread($loop);

//Let the thread work for some seconds then kill and start again
$loop->addPeriodicTimer(3,function(TimerInterface $timer) use ($thread)
{
    //check if thread is running
    if($thread->IsRunning())
    {
        echo "Thread is Running... Kill it!".PHP_EOL;
        //cancel everything the thread is doing
        $thread->Kill();
    }
    else
    {
        echo "Thread is not running... Start Working!".PHP_EOL;
        //cancel everything the thread is doing
        $thread->start();
    }
});

//stop process after 30 seconds, otherwise it runs forever
$loop->addTimer(30,function(TimerInterface $timer) use ($thread)
{
    //check if thread is running
    if($thread->IsRunning())
    {
        //kill if its running for an clean shutdown
        $thread->Kill();
    }
    $timer->getLoop()->stop();
});
$loop->run();