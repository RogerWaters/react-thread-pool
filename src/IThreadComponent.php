<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 31.01.2016
 * Time: 12:55
 */

namespace RogerWaters\ReactThreads;


interface IThreadComponent
{
    /**
     * Each message received call this function
     * @param ThreadCommunicator $communicator
     * @param mixed $messagePayload
     * @return mixed
     */
    public function handleMessage(ThreadCommunicator $communicator, $messagePayload);

    /**
     * Initialize your logic and do whatever you want external
     * @param ThreadCommunicator $communicator
     */
    public function InitializeExternal(ThreadCommunicator $communicator);
}