<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 24.01.2016
 * Time: 14:38
 */

namespace RogerWaters\ReactThreads;


use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;

class ClientThread extends ThreadBase
{
    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var ThreadConnection
     */
    private $client;

    /**
     * @var string
     */
    private $id;

    /**
     * @var ThreadPool
     */
    private $pool;

    /**
     * ClientThread constructor.
     * @param ForkableLoopInterface $loop
     * @param ThreadPool $pool
     */
    public function __construct(ForkableLoopInterface $loop, ThreadPool $pool)
    {
        parent::__construct($loop);
        $this->endpoint = $pool->getEndpoint();
        $this->id = spl_object_hash($this);
        $pool->registerThread($this, function ($action, array $parameters = array())
        {
            call_user_func_array(array($this,$action),$parameters);
        });
        $this->pool = $pool;
    }

    /**
     * The endpoint the thread connects to
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * The object_hash created for this object
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Encode and send the message to the parent
     * will call method $action on this instance in the parent context
     * @param string $action
     * @param array $parameters
     */
    protected function callOnParent($action, array $parameters = array())
    {
        if($this->isExternal())
        {
            $this->client->write($this->encode($action, $parameters));
        }
        else
        {
            throw new \RuntimeException("Calling ClientThread::CallOnParent from Parent context. Did you mean ClientThread::CallOnChild?");
        }
    }

    /**
     * Encode and send the message to the external process
     * will call method $action on this instance in the child context
     * @param string $action
     * @param array $parameters
     */
    protected function callOnChild($action, array $parameters = array())
    {
        if($this->isExternal())
        {
            throw new \RuntimeException("Calling ClientThread::CallOnChild from Child context. Did you mean ClientThread::CallOnParent?");
        }
        else
        {
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->pool->sendToClient($this, $this->encode($action, $parameters));
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
        return array('action' => $action,'parameters' => $parameters);
    }

    /**
     * Creates every logic required for the thread to work.
     * Will connect to the pool endpoint and provide two way communication
     * @param ForkableLoopInterface $loop
     */
    protected function initializeExternal(ForkableLoopInterface $loop)
    {
        $this->pool = null;

        $stream = stream_socket_client($this->endpoint);
        //non async write
        $this->client = new ThreadConnection($loop,$stream,false);
        $this->client->once('close',function() use ($loop)
        {
            $loop->stop();
        });

        $this->client->on('message',function(ThreadConnection $connection,array $messageData)
        {
            call_user_func_array(array($this,$messageData['action']),$messageData['parameters']);
        });

        //write connect message directly as we require to attach to pool
        $this->client->write($this->encode('__connect', array($this->id)));

        $loop->run();
    }

    /**
     * Stop the external process after all current operations completed
     */
    public function stop()
    {
        if($this->isExternal())
        {
            //close connection
            $this->client->close();
        }
        else
        {
            $this->callOnChild(__FUNCTION__, func_get_args());
        }
    }
}