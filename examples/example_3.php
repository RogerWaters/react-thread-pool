<?php
/**
 * Example shows how to communicate with Threads
 */

use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\Protocol\AsyncMessage;
use RogerWaters\ReactThreads\ThreadCommunicator;

include('./../vendor/autoload.php');

/**
 * This class allows you to download multiple pages in an separate process
 * Class DownloaderThread
 */
class DownloaderThread extends \RogerWaters\ReactThreads\ThreadBase
{
    /**
     * Function for downloading urls external
     * @param string $url
     * @param callable $onComplete
     * @return AsyncMessage|string
     */
    public function download($url, callable $onComplete = null)
    {
        //first check if the function is called external
        if($this->isExternal())
        {
            $data = file_get_contents($url);
            //hold the process to simulate more to do ;-)
            sleep(3);
            echo "Downloaded: ".strlen($data).' bytes from url: '.$url.PHP_EOL;
            //return the data
            //parent thread can handle this if needed
            return $data;
        }
        else
        {
            //we are in the parent context
            //just redirect to the thread
            //we use async as we dont want to wait for the result
            //return the handle allow the caller to check result
            return $this->asyncCallOnChild(__FUNCTION__, array($url), $onComplete);
        }
    }

    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator)
    {
        //nothing to do here as we get our messages from parent
    }
}

$loop = ForkableFactory::create();


//create an instance of the class above
//the thread is directly attached to the ThreadPool
$thread = new DownloaderThread($loop);

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

foreach ($urls as $url)
{
    //tell the thread to download the url
    //you can provide a callback to get directly informed on complete
    $thread->download($url, function (AsyncMessage $message) use ($url)
    {
        //check if the message is resolved.
        //this is for future compatibility to provide progress
        if ($message->isIsResolved())
        {
            //you can save the result to disc
            //file_put_contents('./result.html',$message->GetResult());
            //echo the bytes here to be comparable with echo from remote thread
            echo "Parent got $url content with: ", strlen($message->GetResult()), ' bytes', PHP_EOL;
        }
    });
}

$loop->run();