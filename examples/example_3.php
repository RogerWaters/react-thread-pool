<?php
/**
 * Example shows how to communicate with Threads
 */

use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\ClientThread;
use RogerWaters\ReactThreads\EventLoop\ForkableLibEventLoop;
use RogerWaters\ReactThreads\EventLoop\ForkableStreamSelectEventLoop;
use RogerWaters\ReactThreads\ThreadPool;

include('./../vendor/autoload.php');

/**
 * This class allows you to download multiple pages in an separate process
 * Class DownloaderThread
 */
class DownloaderThread extends ClientThread
{
    /**
     * Functin for downloading urls external
     * @param string $url
     */
    public function Download($url)
    {
        //first check if the function is called external
        if($this->isExternal())
        {
            $data = file_get_contents($url);
            //hold the process to simulate more to do ;-)
            sleep(3);
            echo "Downloaded: ".strlen($data).' bytes from url: '.$url.PHP_EOL;
        }
        else
        {
            //we are in the parent context
            //just redirect to the thread
            $this->CallOnChild(__FUNCTION__,func_get_args());
        }
    }
}

//$loop = new ForkableLibEventLoop();
$loop = new ForkableStreamSelectEventLoop();

//we require am ThreadPool here
//the pool creates an endpoint to communicate with all threads attached to it
//you can have multiple ThreadPool instances, but its best to use only one
$pool = new ThreadPool($loop);

//create an instance of the class above
//the thread is directly attached to the ThreadPool
$thread = new DownloaderThread($loop,$pool);

//lets start the thread.
//as you maybe mention this does nothing else than creating an process
//we did not tell the thread to do anything for now...
$thread->start();

//lets register some urls to process
$urls = array
(
    'https://github.com/',
    'https://google.com/',
    'https://bing.com/',
    'http://php.net/'
);


//Let the thread work for some seconds then kill and start again
$loop->addPeriodicTimer(1,function(TimerInterface $timer) use ($thread,&$urls)
{
    //take an url from the stack
    $url = array_pop($urls);

    if($url !== null)
    {
        echo "Enqueue url $url for download".PHP_EOL;
        //download the url external
        //each url will take 3 seconds
        //but we enqueue url every second;
        $thread->Download($url);
    }
    else
    {
        //all urls submitted
        //we can tell the thread to close after completion
        //use Stop instead of Kill wil wait for all works to complete before closing
        $thread->Stop();
        echo "Tell Thread to stop after current works".PHP_EOL;
        //waiting for the thread to stop
        //this can take some tome...
        $thread->on('stopped',function() use ($timer)
        {
            echo "Thread is done.. Stop parent loop".PHP_EOL;
            //stop the parent process
            $timer->getLoop()->stop();
        });

        //cancel the timer as we have no urls to download left
        $timer->cancel();
    }
});

$loop->run();