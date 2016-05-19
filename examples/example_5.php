<?php
/**
 * Example on how to handle error and fatal on parent
 */

use RogerWaters\ReactThreads\ErrorHandling\ThreadFatalException;
use RogerWaters\ReactThreads\EventLoop\ForkableFactory;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;
use RogerWaters\ReactThreads\ThreadCommunicator;

include('./../vendor/autoload.php');

/**
 * Emulate external fatal and exception
 * Class EchoThread
 */
class FatalThread extends ThreadBase
{
    /**
     * @var ForkableLoopInterface
     */
    protected $loop;

    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator)
    {
        //save the loop to create fatal
        $this->loop = $communicator->getLoop();
        //we will kill the thread by exeptions and fatals
    }

    /**
     * Will create PHP Fatal on remote thread
     */
    public function emulateFatal()
    {
        if ($this->isExternal()) {
            $obj = null;
            $obj->callOnNull();
        } else {
            $this->callOnChild(__FUNCTION__, func_get_args());
        }
    }

    /**
     * Will throw exception on remote thread
     * @throws Exception
     */
    public function emulateException()
    {
        if ($this->isExternal()) {
            throw new Exception("Exception from child");
        } else {
            $this->callOnChild(__FUNCTION__, func_get_args());
        }
    }

    /**
     * Will throw exception on remote thread within loop
     * @throws Exception
     */
    public function emulateExceptionOnRemoteLoop()
    {
        if ($this->isExternal()) {
            $this->loop->nextTick(function () {
                $this->emulateException();
            });
        } else {
            $this->callOnChild(__FUNCTION__, func_get_args());
        }
    }

    /**
     * Will create PGP Fatal on remote thread within loop
     * @throws Exception
     */
    public function emulateFatalOnRemoteLoop()
    {
        if ($this->isExternal()) {
            $this->loop->nextTick(function () {
                $this->emulateFatal();
            });
        } else {
            $this->callOnChild(__FUNCTION__, func_get_args());
        }
    }
}

//Create and Start
$loop = ForkableFactory::create();
$thread = new FatalThread($loop);
$thread->start();

//Exception handling starts here

try {
    //will create the exception in thread
    //the exception is serialized and thrown here
    $thread->emulateException();
} catch (Exception $e) {
    var_dump($e->getMessage());
}

//after exceptions thread is still running

try {
    //create a fatal on remote thread
    //the fatal is casted to an exception and thrown here
    $thread->emulateFatal();
}
//Fatal has special exception types
//you can decide by first catching ThreadFatalException
catch (ThreadFatalException $e) {
    var_dump($e->getMessage());
    //wait for external closed this is only useful if you want
    //to reuse instance by starting the thread again
    //if you throw away the instance just set thread = null
    //everything else is done withing the garbage collection
    $thread->wait();

    //start the thread again
    $thread->start();
} catch (Exception $e) {
    //not reached in our example
    var_dump($e->getMessage());
}

//you have to handle the following cases your own way
//depending on your application
/*
try
{
    //this will kill the loop on parent if you do not handle this
    $thread->emulateExceptionOnRemoteLoop();
}
catch (\Exception $e)
{
    //this is not reached!!!
    //exception is thrown after execution and will
    //be redirected to the loop
    var_dump($e->getMessage());
}

try
{
    //this will kill the entire thread
    //however you can catch this on the parent process
    $thread->emulateFatalOnRemoteLoop();
}
catch (\Exception $e)
{
    //also never reached
    var_dump($e->getMessage());
}
*/


//don't forget the loop to run,
//otherwise the parent process and all threads gets closed immediately
$loop->run();