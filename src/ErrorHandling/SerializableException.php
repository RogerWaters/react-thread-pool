<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 06.02.2016
 * Time: 10:16
 */

namespace RogerWaters\ReactThreads\ErrorHandling;

class SerializableException /*implements \Throwable PHP 7 only*/
{
    /**
     * @var string
     */
    protected $message;
    /**
     * @var string
     */
    protected $traceString;
    /**
     * @var int|mixed
     */
    protected $code;
    /**
     * @var string
     */
    protected $file;
    /**
     * @var int
     */
    protected $line;
    /**
     * @var SerializableException
     */
    protected $previous = null;
    /**
     * @var array
     */
    protected $trace;

    /**
     * Create Serializable exception from real exception
     * @param \Exception $exception
     */
    public function __construct(\Exception $exception)
    {
        $this->message = $exception->getMessage();
        $this->traceString = $exception->getTraceAsString();
        $this->code = $exception->getCode();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();

        if ($exception->getPrevious() instanceof \Exception) {
            $this->previous = new SerializableException($exception->getPrevious());
        }
        $this->trace = $exception->getTrace();

        $this->cleanupTrace();
    }

    protected function cleanupTrace()
    {
        $traceData = $this->trace;

        foreach ($traceData as $id => &$functionCall) {
            if (isset($functionCall['args'])) {
                foreach ($functionCall['args'] as $key => $arg) {
                    if ($arg instanceof \Closure) {
                        $functionCall['args'][$key] = 'object(Closure)';
                    }
                }
            }
        }

        $this->trace = $traceData;
    }

    /***
     * Gets the message
     * @link http://php.net/manual/en/throwable.getmessage.php
     * @return string
     * @since 7.0
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Gets the exception code
     * @link http://php.net/manual/en/throwable.getcode.php
     * @return int <p>
     * Returns the exception code as integer in
     * {@see Exception} but possibly as other type in
     * {@see Exception} descendants (for example as
     * string in {@see PDOException}).
     * </p>
     * @since 7.0
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Gets the file in which the exception occurred
     * @link http://php.net/manual/en/throwable.getfile.php
     * @return string Returns the name of the file from which the object was thrown.
     * @since 7.0
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Gets the line on which the object was instantiated
     * @link http://php.net/manual/en/throwable.getline.php
     * @return int Returns the line number where the thrown object was instantiated.
     * @since 7.0
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * Gets the stack trace
     * @link http://php.net/manual/en/throwable.gettrace.php
     * @return array <p>
     * Returns the stack trace as an array in the same format as
     * {@see debug_backtrace()}.
     * </p>
     * @since 7.0
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * Gets the stack trace as a string
     * @link http://php.net/manual/en/throwable.gettraceasstring.php
     * @return string Returns the stack trace as a string.
     * @since 7.0
     */
    public function getTraceAsString()
    {
        return $this->traceString;
    }

    /**
     * Returns the previous Throwable
     * @link http://php.net/manual/en/throwable.getprevious.php
     * @return Throwable Returns the previous {@see Throwable} if available, or <b>NULL</b> otherwise.
     * @since 7.0
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * Gets a string representation of the thrown object
     * @link http://php.net/manual/en/throwable.tostring.php
     * @return string <p>Returns the string representation of the thrown object.</p>
     * @since 7.0
     */
    public function __toString()
    {
        return $this->message;
    }
}