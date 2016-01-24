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

    public function __construct(ForkableLoopInterface $loop, ThreadPool $pool)
    {
        parent::__construct($loop);
        $this->endpoint = $pool->getEndpoint();
        $this->id = spl_object_hash($this);
        $pool->RegisterThread($this,function($action,array $parameters = array())
        {
            call_user_func_array(array($this,$action),$parameters);
        });
        $this->pool = $pool;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    protected function CallOnParent($action,array $parameters = array())
    {
        if($this->isExternal())
        {
            $this->client->Write($this->Encode($action,$parameters));
        }
        else
        {
            throw new \RuntimeException("Calling ClientThread::CallOnParent from Parent context. Did you mean ClientThread::CallOnChild?");
        }
    }

    protected function CallOnChild($action,array $parameters = array())
    {
        if($this->isExternal())
        {
            throw new \RuntimeException("Calling ClientThread::CallOnChild from Child context. Did you mean ClientThread::CallOnParent?");
        }
        else
        {
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->pool->SendToClient($this,$this->Encode($action,$parameters));
        }
    }

    protected function Encode($action, array $parameters = array())
    {
        return array('action' => $action,'parameters' => $parameters);
    }

    protected function InitializeExternal(ForkableLoopInterface $loop)
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
        $this->client->Write($this->Encode('__connect',array($this->id)));

        $loop->run();
    }

    public function Stop()
    {
        if($this->isExternal())
        {
            //close connection
            $this->client->close();
        }
        else
        {
            $this->CallOnChild(__FUNCTION__,func_get_args());
        }
    }
}