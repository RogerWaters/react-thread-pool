<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 24.01.2016
 * Time: 20:53
 */

namespace RogerWaters\ReactThreads\EventLoop;

class ForkableFactory
{
    /**
     * @return ForkableLoopInterface
     */
    public static function create()
    {
        // @codeCoverageIgnoreStart
        if (function_exists('event_base_new')) {
            return new ForkableLibEventLoop();
        } else if (class_exists('EventBase')) {
            return new ForkableExtEventLoop();
        }

        return new ForkableStreamSelectEventLoop();
        // @codeCoverageIgnoreEnd
    }
}