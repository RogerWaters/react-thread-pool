<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 16:15
 */
use RogerWaters\ReactThreads\ClientThread;
use RogerWaters\ReactThreads\EventLoop\ForkableLibEventLoop;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadPool;

include ('./../vendor/autoload.php');

class echoWork extends \RogerWaters\ReactThreads\ThreadWork
{
    public function DoWork(\RogerWaters\ReactThreads\ThreadBase $base, ForkableLoopInterface $loop)
    {
        sleep(1);
        echo "Working".PHP_EOL;
        sleep(1);
    }
}

class TalkingThread extends ClientThread
{
    public function Talk($message)
    {
        if($this->isExternal)
        {
            echo "Got message from Parent: $message".PHP_EOL;
            $this->TalkBack('Thanks for message ('.posix_getpid().'): '.$message);
        }
        else
        {
            $this->CallOnChild(__FUNCTION__,func_get_args());
        }
    }


    public function TalkBack($message)
    {
        if($this->isExternal === false)
        {
            echo "Got message from Child: $message".PHP_EOL;
        }
        else
        {
            $this->CallOnParent(__FUNCTION__,func_get_args());
        }
    }
}

$loop = new ForkableLibEventLoop();
$pool = new ThreadPool($loop);

for($i = 0; $i < 10; $i++)
{

    $thread = new TalkingThread($loop,$pool);

    $thread->start();

    $timer = $loop->addPeriodicTimer(1,function() use($thread)
    {
        $thread->Talk('Hallo World '.posix_getpid());
    });

    $loop->addTimer(10,function() use ($thread,$timer)
    {
        $thread->Stop();
        $timer->cancel();
    });
}

$loop->run();

die();