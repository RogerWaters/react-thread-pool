<?php
/**
 * Example shows how to receive events from threads
 */

use Evenement\EventEmitterTrait;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadCommunicator;
use RogerWaters\ReactThreads\ThreadPool;

include('./../vendor/autoload.php');

/**
 * This class allows you to observe any stream available for read events
 * Class DownloaderThread
 */
class StreamObserverThread extends ThreadBase
{
    /**
     * Include EventEmitter to send custom events
     */
    use EventEmitterTrait;

    /**
     * @var resource
     */
    private $stream;

    /**
     * Passing any type of variable to the thread constructor
     * It will be copied to the child process
     * @param ForkableLoopInterface $loop
     * @param resource $stream
     */
    public function __construct(ForkableLoopInterface $loop, $stream)
    {
        if(is_resource($stream) === false)
        {
            throw new InvalidArgumentException("Stream has to be a valid resource");
        }
        parent::__construct($loop);
        $this->stream = $stream;
    }

    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator)
    {
        $communicator->getLoop()->addReadStream($this->stream, function ($stream)
        {
            $message = fgets($stream, 1024);
            //calling the EventEmiiter::emit() function on parent
            $this->callOnParent('emit', array('stream_message', array($message)));
        });
    }
}

$loop = ForkableFactory::create();

//create the thread instance and pass an stream as last parameter
$thread = new StreamObserverThread($loop, STDIN);

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