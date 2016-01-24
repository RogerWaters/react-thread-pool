<?php
/**
 * Example shows how to receive events from threads
 */

use RogerWaters\ReactThreads\ClientThread;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadPool;

include('./../vendor/autoload.php');

/**
 * This class allows you to observe any stream available for read events
 * Class DownloaderThread
 */
class StreamObserverThread extends ClientThread
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * Passing any type of variable to the thread constructor
     * It will be copied to the child process
     * @param ForkableLoopInterface $loop
     * @param ThreadPool $pool
     * @param resource $stream
     */
    public function __construct(ForkableLoopInterface $loop, ThreadPool $pool, $stream)
    {
        if(is_resource($stream) === false)
        {
            throw new InvalidArgumentException("Stream has to be a valid resource");
        }
        parent::__construct($loop, $pool);
        $this->stream = $stream;
    }

    protected function initializeExternal(ForkableLoopInterface $loop)
    {
        //stream_set_blocking($this->stream,0);
        $loop->addReadStream($this->stream,function($stream)
        {
            $message = fread($stream,1024);
            //stdin takes the \n in the message so trim it
            $message = strtolower(rtrim($message));
            //calling the EventEmiiter::emit() function on parent
            $this->callOnParent('emit', array('stream_message', array($message)));
        });
        //don't forget the parent logic
        parent::initializeExternal($loop);
    }
}

$loop = ForkableFactory::create();
$pool = new ThreadPool($loop);

//create the thread instance and pass an stream as last parameter
$thread = new StreamObserverThread($loop,$pool,STDIN);

//register on the custom event
//do not use reserved events: message, starting, stopped, error
$thread->on('stream_message',function($message) use ($loop,$thread)
{
    echo "Got: ".$message.PHP_EOL;
    if(strtolower(rtrim($message)) === 'close')
    {
        echo "Stop process".PHP_EOL;
        //hold on listener
        $thread->kill();
        //stop the parent process
        $loop->stop();
    }
});

$thread->start();

echo "Type 'close' to close the process.".PHP_EOL;
echo "Type something...".PHP_EOL;


$loop->run();