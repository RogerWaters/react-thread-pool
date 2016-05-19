<?php
/**
 * Example how to use load balancer
 */

use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\LoadBalancer;
use RogerWaters\ReactThreads\Protocol\AsyncMessage;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadCommunicator;

include('./../vendor/autoload.php');

/**
 * Do some work on thread
 * Class BalancedThread
 */
class BalancedThread extends ThreadBase
{
    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator)
    {
        //leave empty
    }

    /**
     * Function to do some async work on thread class
     * @param int $timeToWorkOnSomething
     * @param callable $onComplete
     * @return AsyncMessage
     */
    public function SomeUndefinedLongRunningWork($timeToWorkOnSomething, callable $onComplete = null)
    {
        if ($this->isExternal()) {
            $timeToWorkOnSomething *= 100;
            echo "Start work $timeToWorkOnSomething msec on thread: " . posix_getpid() . PHP_EOL;
            //just a simulation on different time running process
            usleep($timeToWorkOnSomething);
            //not required but return everything you like
            //lets return a message to display on parent
            echo "Completed work $timeToWorkOnSomething msec on thread: " . posix_getpid();
            return "Completed work $timeToWorkOnSomething msec on thread: " . posix_getpid();
        } else {
            return $this->asyncCallOnChild(__FUNCTION__, array($timeToWorkOnSomething), $onComplete, function (AsyncMessage $messga) {
                var_dump($messga->GetResult()->getMessage());
            });
        }
    }
}

//Extends the default loop from React\EventLoop
$loop = ForkableFactory::create();

//configuration for the load balancer
//[optional] this tells how long a thread is kept in memory until it is garbage
$lazyThreadTimeOut = 30;

//[optional] the maximum number of threads used to prevent fork bombs
$maxNumberOfThreads = 10;

//how many threads at least are active
//those threads are kept in memory also if lazy timout is reached
$minNumberOfThreads = 3;

//initialize the balancer
$balancer = BalancedThread::CreateLoadBalancer($loop, $minNumberOfThreads, $maxNumberOfThreads, $lazyThreadTimeOut);

//now everything is done to use it like the thread class

//test with some random msec
$randomWorkTime = range(0, 100);
shuffle($randomWorkTime);

//now the important part
//only enqueue work if threads are available

$loop->addPeriodicTimer(1, function (TimerInterface $timer) use (&$randomWorkTime, $balancer) {
    //just a type hint for ide
    /** @var LoadBalancer|BalancedThread $balancer */

    //stop script if everything is completed
    if (count($randomWorkTime) < 1 && $balancer->getNumberOfThreadsLazy() === $balancer->getNumberOfThreads()) {
        $timer->cancel();
        $timer->getLoop()->stop();
    }

    //fill all operations until complete
    foreach ($randomWorkTime as $key => $timeToWork) {
        //limit to prevent reaching the maximum number of threads
        if ($balancer->getNumberOfThreads() < $balancer->getMaximumNumberOfThreads() || $balancer->getNumberOfThreadsLazy() > 0) {
            unset($randomWorkTime[$key]);
            //this will do the work with as many threads as required
            $balancer->SomeUndefinedLongRunningWork($timeToWork, function (AsyncMessage $message) use ($timeToWork) {
                echo $message->GetResult() . PHP_EOL;
            });
        } else {
            //echo "Could not add any more requests as balancer is overloaded. Waiting some time...".PHP_EOL;
            return;
        }
    }
});

//don't forget the loop to run,
//otherwise the parent process and all threads gets closed immediately
$loop->run();