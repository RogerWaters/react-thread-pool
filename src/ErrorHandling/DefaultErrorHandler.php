<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 06.02.2016
 * Time: 10:07
 */

namespace RogerWaters\ReactThreads\ErrorHandling;


use ErrorHandling\PHPTriggerErrorException;

class DefaultErrorHandler implements IThreadErrorHandler
{
    /**
     * Default error handler callback for @see set_error_handler
     * @param int $errSeverity
     * @param string $errMessage
     * @param string $errFile
     * @param int $errLine
     * @param array $errContext
     * @return bool
     * @throws PHPTriggerErrorException
     */
    public function OnUncaughtErrorTriggered($errSeverity, $errMessage, $errFile, $errLine, array $errContext)
    {
        //@call
        if (0 === error_reporting()) {
            return false;
        }
        throw new PHPTriggerErrorException($errMessage, $errLine, $errFile, $errSeverity);
    }

    /**
     * Handles error on top level of thread execution.
     * The return is ether a value or object indicating the error occurred
     * you can return nothing if you e.g. log the error and does not matter on it on the main thread
     * Do not throw any Exception
     * @param \Exception $exception
     * @return mixed|void
     */
    public function OnUncaughtException(\Exception $exception)
    {
        return new SerializableException($exception);
    }

    /**
     * The result from @see IThreadErrorHandler::OnUncaughtException is given as Parameter in the parent thread
     * You can decide how to handle this
     * If you like you could throw an \Exeption to the caller or you can just return a value
     * indicating that function is failed. You can also trigger error here if you like to
     * @param mixed $result
     * @throws \Exception
     * @return mixed
     */
    public function OnErrorMessageReachParent($result)
    {
        if ($result instanceof SerializableException) {
            //best is to log all the exception information here
            //you can also inject the variables into normal exception using reflection
            throw new \Exception($result->getMessage() . PHP_EOL . PHP_EOL . $result->getTraceAsString(), $result->getCode());
        }
        //if it is an error object created from another handler just return it.
        //this only applies to usage of distinct ErrorHandlers
        return $result;
    }
}