<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 15:59
 */

namespace Protocol;


class BinaryBuffer
{
    protected $messages = array();

    protected $bufferData = "";
    protected $headerReceived = false;
    protected $waitingFor = 0;


    public function __construct()
    {
    }

    public function PushData($data)
    {
        $this->bufferData .= $data;
        while($this->ParseBuffer())
        {
            //... waiting while parsing...
        }
    }

    public function HasMessages()
    {
        return count($this->messages) > 0;
    }

    protected function ParseBuffer()
    {
        if($this->headerReceived === false)
        {
            return $this->ReceiveHeader();
        }
        else
        {
            return $this->ReceiveBody();
        }
    }

    protected function ReceiveHeader()
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

    protected function ReceiveBody()
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

    public function GetMessages()
    {
        $messages = $this->messages;
        $this->messages = array();
        return $messages;
    }

    public static function EncodeMessage($message)
    {
        $waitingData = strlen($message);
        $header = str_pad((string)$waitingData,8,'0',STR_PAD_LEFT);
        return $header.$message;
    }
}