<?php

use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;

class ExampleThread extends ThreadBase
{
    protected function InitializeExternal(ForkableLoopInterface $loop)
    {
        //Do your external logic here
        //you can also use $loop functions but don't forget $loop->run()
        //after this execution the thread is closed automatically
    }
}