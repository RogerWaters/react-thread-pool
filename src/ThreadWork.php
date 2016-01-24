<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 24.01.2016
 * Time: 11:38
 */

namespace RogerWaters\ReactThreads;


use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;

abstract class ThreadWork
{
    public abstract function DoWork(ThreadBase $base,ForkableLoopInterface $loop);
}