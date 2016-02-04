<?php
/**
 * Example shows how to control the ThreadBase
 */

use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadCommunicator;

include('./../vendor/autoload.php');

/**
 * class from example_1
 * Class EchoThread
 */
class EchoThread extends ThreadBase
{
    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator)
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
$loop->addPeriodicTimer(3, function () use ($thread)
{
    //check if thread is running
    if ($thread->isRunning())
    {
        echo "Thread is Running... Kill it!".PHP_EOL;
        //cancel everything the thread is doing
        $thread->kill();
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
    if ($thread->isRunning())
    {
        //kill if its running for an clean shutdown
        $thread->kill();
    }
    $timer->getLoop()->stop();
});
$loop->run();