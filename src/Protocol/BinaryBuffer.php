<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 15:59
 */

namespace RogerWaters\ReactThreads\Protocol;


class BinaryBuffer
{
    /**
     * @var string[]
     */
    protected $messages = array();

    /**
     * @var string
     */
    protected $bufferData = "";
    /**
     * @var bool
     */
    protected $headerReceived = false;
    /**
     * @var int
     */
    protected $waitingFor = 0;

    /**
     * BinaryBuffer constructor.
     */
    public function __construct()
    {
    }

    /**
     * Send data to the internal buffer and starts the parser
     * @param string $data
     */
    public function pushData($data)
    {
        $this->bufferData .= $data;
        while ($this->parseBuffer())
        {
            //... waiting while parsing...
        }
    }

    /**
     * Check if there are unprocessed messages
     * @return bool
     */
    public function hasMessages()
    {
        return count($this->messages) > 0;
    }

    /**
     * Entry point for parsing messages
     * @return bool
     */
    protected function parseBuffer()
    {
        if($this->headerReceived === false)
        {
            return $this->receiveHeader();
        }
        else
        {
            return $this->receiveBody();
        }
    }

    /**
     * Try to receive header from buffer
     * on success the next operation is receiveBody
     * @return bool
     */
    protected function receiveHeader()
    {
        if(strlen($this->bufferData) > 8)
        {
            $header = substr($this->bufferData,0,8);
            $this->waitingFor = intval($header);
            $this->bufferData = substr($this->bufferData,8);
            $this->headerReceived = true;
            return strlen($this->bufferData) > 0;
        }
        return false;
    }

    /**
     * Try to read the number of bytes given from header into message
     * On success the next operation is receiveHeader
     * @return bool
     */
    protected function receiveBody()
    {
        if(strlen($this->bufferData) >= $this->waitingFor)
        {
            $this->messages[] = substr($this->bufferData,0,$this->waitingFor);
            $this->bufferData = substr($this->bufferData,$this->waitingFor);
            $this->headerReceived = false;
            return strlen($this->bufferData) > 0;
        }
        return false;
    }

    /**
     * Get the messages from buffer and reset the message data internal
     * @return string[]
     */
    public function getMessages()
    {
        $messages = $this->messages;
        $this->messages = array();
        return $messages;
    }

    /**
     * Encode the message with header and body to directly write to any socket
     * @param string $message
     * @return string
     */
    public static function encodeMessage($message)
    {
        $waitingData = strlen($message);
        $header = str_pad((string)$waitingData,8,'0',STR_PAD_LEFT);
        return $header.$message;
    }
}