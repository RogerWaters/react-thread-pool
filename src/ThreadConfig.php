<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 06.02.2016
 * Time: 08:49
 */

namespace RogerWaters\ReactThreads;


use RogerWaters\ReactThreads\ErrorHandling\DefaultErrorHandler;
use RogerWaters\ReactThreads\ErrorHandling\IThreadErrorHandler;

class ThreadConfig
{
    /**
     * @var callable
     */
    protected static $serializer = 'serialize';

    /**
     * @var callable
     */
    protected static $unSerializer = 'unserialize';

    /**
     * @var IThreadErrorHandler
     */
    protected static $errorHandler = null;

    private function __construct()
    {
    }

    /**
     * @param callable $serializer
     */
    public static function SetSerializer(callable $serializer)
    {
        self::$serializer = $serializer;
    }

    /**
     * @param callable $unSerializer
     */
    public static function SetUnSerializer(callable $unSerializer)
    {
        self::$unSerializer = $unSerializer;
    }

    public static function Serialize($object)
    {
        return call_user_func(self::$serializer, $object);
    }

    public static function UnSerialize($serialized)
    {
        return call_user_func(self::$unSerializer, $serialized);
    }

    /**
     * @return IThreadErrorHandler
     */
    public static function GetErrorHandler()
    {
        if (self::$errorHandler === null) {
            return new DefaultErrorHandler();
        }
        return self::$errorHandler;
    }

    /**
     * @param IThreadErrorHandler $errorHandler
     */
    public static function SetErrorHandler(IThreadErrorHandler $errorHandler)
    {
        self::$errorHandler = $errorHandler;
    }
}