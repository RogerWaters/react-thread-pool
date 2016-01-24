<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 24.01.2016
 * Time: 15:22
 */

namespace RogerWaters\ReactThreads;


use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\Protocol\BinaryBuffer;

class ThreadConnection
{
    use EventEmitterTrait;
    /**
     * @var string
     */
    public $id;

    /**
     * @var BinaryBuffer
     */
    private $buffer;

    /**
     * @var ForkableLoopInterface
     */
    private $loop;

    /**
     * @var resource
     */
    private $connection;

    /**
     * @var bool
     */
    private $writeEvent = false;
    /**
     * @var bool
     */
    private $writeAsync;

    /**
     * @var string
     */
    private $dataBuffer = '';

    public function __construct(ForkableLoopInterface $loop, $connection, $writeAsync = false)
    {
        $this->loop = $loop;
        $this->connection = $connection;
        $this->buffer = new BinaryBuffer();

        if($writeAsync)
        {
            //non blocking only for async
            stream_set_blocking($connection,0);
        }

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($connection, 0);
        }

        $loop->addReadStream($connection,function($conn, ForkableLoopInterface $loop)
        {
            $message = stream_socket_recvfrom($conn, 1024, 0, $peer);
            //$message = fread($conn,1024);
            if($message !== '' && $message !== false)
            {
                $this->buffer->PushData($message);
                foreach ($this->buffer->GetMessages() as $messageData)
                {
                    $messageData = unserialize($messageData);
                    $this->emit('message',array($this,$messageData));
                }
            }
            else
            {
                fclose($conn);
                $loop->removeReadStream($conn);
                $this->emit('close',array($this));
            }
        });
        $this->writeAsync = $writeAsync;
    }

    public function Write($data)
    {
        $this->dataBuffer .= $this->buffer->EncodeMessage(serialize($data));
        if($this->writeAsync)
        {
            if($this->writeEvent === false)
            {
                $this->loop->addWriteStream($this->connection,function($stream,LoopInterface $loop)
                {
                    $dataWritten = fwrite($stream,$this->dataBuffer);
                    $this->dataBuffer = substr($this->dataBuffer,$dataWritten);
                    if(strlen($this->dataBuffer) <= 0)
                    {
                        $loop->removeWriteStream($stream);
                        $this->writeEvent = false;
                    }
                });
                $this->writeEvent = true;
            }
        }
        else
        {
            while(strlen($this->dataBuffer) > 0 && feof($this->connection) === false)
            {
                $dataWritten = stream_socket_sendto($this->connection,$this->dataBuffer);
                $this->dataBuffer = substr($this->dataBuffer,$dataWritten);
            }
        }
    }

    public function close()
    {
        if($this->connection !== null)
        {
            fclose($this->connection);
            $this->emit('close');
        }
        $this->connection = null;
    }

    public function SetId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function GetId()
    {
        return $this->id;
    }
}