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
     * @var string
     */
    private $dataBuffer = '';

    /**
     * Wrapper around the stream.
     * Provides async and non async write access required for threads
     * @param ForkableLoopInterface $loop
     * @param resource $connection
     */
    public function __construct(ForkableLoopInterface $loop, $connection)
    {
        $this->loop = $loop;
        $this->connection = $connection;
        $this->buffer = new BinaryBuffer();

        $this->ThrowOnConnectionInvalid();

        if (function_exists('stream_set_read_buffer'))
        {
            stream_set_read_buffer($this->connection, 0);
        }

        $this->attachReadStream();
    }

    /**
     * Encode the data given into binary message.
     * The message is send to the endpoint
     * @param array|mixed $data
     */
    public function writeAsync($data)
    {
        $this->dataBuffer .= $this->buffer->encodeMessage(serialize($data));
        if ($this->writeEvent === false)
        {
            $this->loop->addWriteStream($this->connection, function ($stream, LoopInterface $loop)
            {
                if (strlen($this->dataBuffer) > 0)
                {
                    $dataWritten = fwrite($stream,$this->dataBuffer);
                    $this->dataBuffer = substr($this->dataBuffer,$dataWritten);
                    if(strlen($this->dataBuffer) <= 0)
                    {
                        $loop->removeWriteStream($stream);
                        $this->writeEvent = false;
                    }
                }
            });
            $this->writeEvent = true;
        }
    }

    public function writeSync($data)
    {
        if ($this->writeEvent)
        {
            $this->loop->removeWriteStream($this->connection);
            $this->writeEvent = false;
        }

        $this->dataBuffer .= $this->buffer->encodeMessage(serialize($data));

        while (strlen($this->dataBuffer) > 0) {
            $this->ThrowOnConnectionInvalid();
            $dataWritten = stream_socket_sendto($this->connection, $this->dataBuffer);
            $this->dataBuffer = substr($this->dataBuffer, $dataWritten);
        }
    }

    /**
     * Reads a singe message from the remote stream
     */
    public function readSync()
    {
        $this->ThrowOnConnectionInvalid();
        $this->loop->removeReadStream($this->connection);
        $this->readToBuffer();
        $this->attachReadStream();
    }

    /**
     * Closes any underlying stream and removes events
     */
    public function close()
    {
        if($this->connection !== null)
        {
            if ($this->writeEvent) {
                $this->loop->removeWriteStream($this->connection);
            }
            $this->loop->removeReadStream($this->connection);
            fclose($this->connection);
            $this->emit('close', array($this));
        }
        $this->connection = null;
    }

    /**
     * setup the readStream event
     */
    protected function attachReadStream()
    {
        $this->ThrowOnConnectionInvalid();
        $this->loop->addReadStream($this->connection, function () {
            $this->readToBuffer();
        });
    }

    /**
     * reads a block of data to the buffer
     */
    protected function readToBuffer()
    {
        $this->ThrowOnConnectionInvalid();
        $message = stream_socket_recvfrom($this->connection, 1024, 0, $peer);
        //$message = fread($conn,1024);
        if ($message !== '' && $message !== false) {
            $this->buffer->pushData($message);
            foreach ($this->buffer->getMessages() as $messageData) {
                $messageData = unserialize($messageData);
                $this->emit('message', array($this, $messageData));
            }
        } else {
            $this->close();
        }
    }

    protected function ThrowOnConnectionInvalid()
    {
        if (is_resource($this->connection) === false) {
            throw new \InvalidArgumentException("Connection ist invalid or closed");
        }
    }
}