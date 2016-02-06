<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 06.02.2016
 * Time: 10:08
 */

namespace ErrorHandling;


class PHPTriggerErrorException extends \Exception
{
    /**
     * @var
     */
    private $severity;

    /**
     * PHPTriggerErrorException constructor.
     * @param string $message
     * @param int $line
     * @param string $file
     * @param int $severity
     */
    public function __construct($message, $line, $file, $severity)
    {
        parent::__construct($message);
        $this->line = $line;
        $this->file = $file;
        $this->severity = $severity;
    }

    /**
     * One of the E_* constants
     * @return int
     */
    public function getSeverity()
    {
        return $this->severity;
    }
}