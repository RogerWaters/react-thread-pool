<?php

use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;

/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 24.01.2016
 * Time: 11:09
 */
class ExternalTestThread extends ThreadBase
{
    /**
     * @param ForkableLoopInterface $loop
     */
    protected function ConstructExternal(ForkableLoopInterface $loop)
    {
        $counter = 0;
        $loop->addPeriodicTimer(2,function() use (&$counter,$loop)
        {
            echo $counter++." Message from: ".posix_getpid().PHP_EOL;
            if($counter >= 10)
            {
                echo "This was the last message from: ".posix_getpid().' stopping now'.PHP_EOL;
                $loop->stop();
            }
        });

        $loop->run();
    }
}