<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 31.01.2016
 * Time: 12:10
 */

namespace RogerWaters\ReactThreads\Protocol;


class AsyncMessage implements \Serializable
{
    /**
     * @var bool
     */
    protected $isResolved;
    /**
     * @var mixed
     */
    protected $result;
    /**
     * @var bool
     */
    protected $resultIsError = false;

    /**
     * @var string
     */
    private $id;
    /**
     * @var mixed
     */
    private $payload;

    /**
     * @var callable
     */
    private $resolvedCallback;

    /**
     * @var callable
     */
    private $errorCallback;

    /**
     * @var bool
     */
    private $isSync;

    /**
     * MessageFormat constructor.
     * @param $payload
     * @param $isSync
     */
    public function __construct($payload, $isSync)
    {
        $this->payload = $payload;
        $this->id = posix_getpid() . '-' . spl_object_hash($this);
        $this->isResolved = false;
        $this->isSync = $isSync;
    }

    /**
     * @return mixed
     */
    public function GetPayload()
    {
        return $this->payload;
    }

    /**
     * @param $result
     */
    public function Resolve($result)
    {
        $this->result = $result;
        $this->isResolved = true;
        if (is_callable($this->resolvedCallback)) {
            call_user_func($this->resolvedCallback, $this);
        }
    }

    /**
     * @return mixed
     */
    public function GetResult()
    {
        return $this->result;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return boolean
     */
    public function isIsResolved()
    {
        return $this->isResolved;
    }

    /**
     * @return boolean
     */
    public function isIsError()
    {
        return $this->resultIsError;
    }

    /**
     * @return boolean
     */
    public function isIsSync()
    {
        return $this->isSync;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        $data = get_object_vars($this);
        //exclude closure
        unset($data['resolvedCallback']);
        return serialize($data);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        foreach (unserialize($serialized) as $property => $value) {
            $this->{$property} = $value;
        }
    }

    /**
     * @return callable
     */
    public function getResolvedCallback()
    {
        return $this->resolvedCallback;
    }

    /**
     * @param callable $resolvedCallback
     */
    public function setResolvedCallback(callable $resolvedCallback = null)
    {
        $this->resolvedCallback = $resolvedCallback;
    }

    /**
     * @return callable
     */
    public function getErrorCallback()
    {
        return $this->errorCallback;
    }

    /**
     * @param callable $errorCallback
     */
    public function setErrorCallback(callable $errorCallback = null)
    {
        $this->errorCallback = $errorCallback;
    }

    public function Error($result)
    {
        $this->result = $result;
        $this->resultIsError = true;
        if (is_callable($this->errorCallback)) {
            call_user_func($this->errorCallback, $this);
        }
    }
}