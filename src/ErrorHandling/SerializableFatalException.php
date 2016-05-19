<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 06.02.2016
 * Time: 10:16
 */

namespace RogerWaters\ReactThreads\ErrorHandling;

class SerializableFatalException extends SerializableException/*implements \Throwable PHP 7 only*/
{

    /**
     * Create Serializable exception from fatal on thread shutdown
     * @param \Exception $message
     * @param string $file
     * @param int $line
     * @param int $code
     */
    public function __construct($message, $file, $line, $code)
    {
        $this->message = $message;
        $this->traceString = $file . ':' . $line;
        $this->code = $code;
        $this->file = $file;
        $this->line = $line;

        $this->trace = array(array
        (
            'file' => $file,
            'line' => $line,
            'function' => null,
            'class' => null,
            'type' => null,
            'args' => array()
        ));
    }
}