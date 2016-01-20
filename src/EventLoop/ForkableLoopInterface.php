<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 20.01.2016
 * Time: 08:18
 */

namespace RogerWaters\ReactThreads\EventLoop;


use React\EventLoop\LoopInterface;

interface ForkableLoopInterface extends LoopInterface
{
    /**
     * called after each fork in child instance.
     * should clean up ever resource bound to the parent process
     * and should return an new stable instance or itself if reusable
     * @return ForkableLoopInterface
     */
    public function afterForkChild();

    /**
     * called after fork in the parent instance.
     * This gives the ability to continue the loop
     */
    public function afterForkParent();
}