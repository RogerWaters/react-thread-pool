<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 20.01.2016
 * Time: 08:47
 */

namespace RogerWaters\ReactThreads;

use React\EventLoop\LoopInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\Protocol\MessageFormat;

abstract class ThreadBase implements IThreadComponent
{
    /**
     * @var ThreadCommunicator
     */
    private $communicator;

    /**
     * ThreadBase constructor.
     * @param ForkableLoopInterface $loop
     */
    public function __construct(ForkableLoopInterface $loop)
    {
        $this->communicator = new ThreadCommunicator($loop, $this);
    }

    /**
     * Create the external process and run the thread
     */
    public function start()
    {
        if ($this->communicator->isIsRunning() === false) {
            $this->communicator->fork();
        }
    }

    /**
     * Each message received call this function
     * @param ThreadCommunicator $communicator
     * @param mixed $messagePayload
     * @return mixed
     */
    public function handleMessage(ThreadCommunicator $communicator, $messagePayload)
    {
        $action = $messagePayload['action'];
        $parameters = $messagePayload['parameters'];

        if (method_exists($this, $action)) {
            return call_user_func_array(array($this, $action), $parameters);
        }

        return false;
    }

    /**
     * Check if the external process is running
     * @return bool
     */
    public function isRunning()
    {
        return $this->communicator->isIsRunning();
    }

    /**
     * Force the external process to complete
     * The process gets immediately terminated
     * However it can take some time for the parent to consume the status
     */
    public function kill()
    {
        if ($this->isExternal())
        {
            //stop process
            exit();
        } else {
            $this->communicator->kill();
            //ignore if thread is not running
        }
    }

    /**
     * Check if current environment is on the child context or not
     * @return boolean
     */
    public function isExternal()
    {
        return $this->communicator->isIsExternal();
    }


    /**
     * Stop the external process after all current operations completed
     */
    public function stop()
    {
        if ($this->isExternal())
        {
            $this->communicator->getLoop()->stop();
        }
        else
        {
            $this->asyncCallOnChild(__FUNCTION__, func_get_args());
        }
    }

    /**
     * Encode and send the message to the parent
     * will call method $action on this instance in the parent context
     * @param string $action
     * @param array $parameters
     * @return mixed
     */
    protected function callOnParent($action, array $parameters = array())
    {
        if ($this->isExternal()) {
            return $this->communicator->SendMessageSync($this->encode($action, $parameters));
        } else {
            throw new \RuntimeException("Calling ClientThread::CallOnParent from Parent context. Did you mean ClientThread::CallOnChild?");
        }
    }

    /**
     * Asynchronous variation of @see ThreadBase::callOnParent
     * this method returns instantly
     * You can ether use the @see MessageFormat::isIsResolved to check the result
     * Or you can provide a callback executed if the message gets resolved
     * If you don't matter on what the call returns just throw away the result
     * @param string $action
     * @param array $parameters
     * @param callable $onResult
     * @return MessageFormat
     */
    protected function asyncCallOnParent($action, array $parameters = array(), callable $onResult = null)
    {
        if ($this->isExternal())
        {
            return $this->communicator->SendMessageAsync($this->encode($action, $parameters), $onResult);
        } else {
            throw new \RuntimeException("Calling ClientThread::CallOnParent from Parent context. Did you mean ClientThread::CallOnChild?");
        }
    }

    /**
     * Encode and send the message to the external process
     * will call method $action on this instance in the child context
     * @param string $action
     * @param array $parameters
     * @return mixed
     */
    protected function callOnChild($action, array $parameters = array())
    {
        if ($this->isExternal()) {
            throw new \RuntimeException("Calling ClientThread::CallOnChild from Child context. Did you mean ClientThread::CallOnParent?");
        } else {
            return $this->communicator->SendMessageSync($this->encode($action, $parameters));
        }
    }

    /**
     * Asynchronous variation of @see ThreadBase::callOnChild
     * this method returns instantly
     * You can ether use the @see MessageFormat::isIsResolved to check the result
     * Or you can provide a callback executed if the message gets resolved
     * If you don't matter on what the call returns just throw away the result
     * @param string $action
     * @param array $parameters
     * @param callable $onResult
     * @return MessageFormat
     */
    protected function asyncCallOnChild($action, array $parameters = array(), callable $onResult = null)
    {
        if($this->isExternal())
        {
            throw new \RuntimeException("Calling ClientThread::CallOnChild from Child context. Did you mean ClientThread::CallOnParent?");
        }
        else
        {
            return $this->communicator->SendMessageAsync($this->encode($action, $parameters), $onResult);
        }
    }

    /**
     * Format message to send over connection
     * @param string $action
     * @param array $parameters
     * @return array
     */
    protected function encode($action, array $parameters = array())
    {
        return array('action' => $action, 'parameters' => $parameters);
    }

    /**
     * @return float
     */
    public function getSecondsSinceLastMessage()
    {
        return $this->communicator->getSecondsSinceLastMessage();
    }

    /**
     * @param ForkableLoopInterface $loop
     * @param $minimumNumberOfThreads
     * @param int $maximumNumberOfThreads
     * @param int $lazyThreadTimeoutSec
     * @return self|ThreadPool
     */
    public static function CreatePooled(ForkableLoopInterface $loop, $minimumNumberOfThreads, $maximumNumberOfThreads = 32, $lazyThreadTimeoutSec = 60)
    {
        $pool = new ThreadPool($loop, get_called_class(), $minimumNumberOfThreads, $maximumNumberOfThreads, $lazyThreadTimeoutSec);
        return $pool;
    }
}