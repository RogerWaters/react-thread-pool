<?php
/**
 * Example shows the basic usage of ThreadBase class
 */

use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadCommunicator;

include('./../vendor/autoload.php');

/**
 * Simple Thread that simulates long running echoes ;-)
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
        echo "After complete you can close the thread" . PHP_EOL;
        $this->kill();
    }
}

//Extends the default loop from React\EventLoop
$loop = ForkableFactory::create();

//This is the thread instance. You can have as many instances as you like
$thread = new EchoThread($loop);

//Starting the work now
$thread->start();

//you can do something in parent without affecting performance
$loop->addPeriodicTimer(1,function(TimerInterface $timer) use ($thread)
{
    if ($thread->isRunning())
    {
        echo "Thread is still running".PHP_EOL;
    }
    else
    {
        //also the thread is done
        //it can take some seconds for the parent to determine
        echo "Thread is done".PHP_EOL;
        $timer->cancel();
        //we end the parent here
        $timer->getLoop()->stop();
    }
});

//don't forget the loop to run,
//otherwise the parent process and all threads gets closed immediately
$loop->run();