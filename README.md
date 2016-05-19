# react-thread-pool

This is a multiprocessing library for PHP based on pcntl_fork().<br />
<br />
Status: alpha<br />
Tests: none<br />

## Features
- Running Parallel Processes out of a single Process
- Give the entire environment to the Thread without thinking on serialisation
- Control the thread (Start, Stop, Kill) + Custom messages
- Running tons of threads on an single ThreadPool (be careful with your processor)
- Setting up threads to observe external resources

## Requirements
- Linux/Unix platform
- PHP >= 5.4 (also PHP 7)
- custom functions (pcntl_fork, pcntl_waitpid, posix_getpid)
- <a href="https://github.com/reactphp/event-loop" target="_blank">reactphp/event-loop</a>
- <a href="https://github.com/igorw/evenement" target="_blank">igorw/evenement</a>
- [optional] PHP Extension libevent (pect/libevent-0.1.0)
- [optional] PHP Extension Event <http://php.net/manual/en/book.event.php>

## Basic Usage

Create an EventLoop:<br />
```php
$loop = ForkableFactory::create();
```
*The loop is the same as reactphp/event-loop so you can also use this for your server
<br/>
Creating a default thread to perform heavy work outside your parent process:
```php
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\ThreadBase;

class ExampleThread extends ThreadBase
{
    protected function InitializeExternal(ForkableLoopInterface $loop)
    {
        //Do your external logic here
        //you can also use $loop functions
        //Use $this->kill(); to complete execution from child
        $this->kill();
    }
}
```
<br/>
All together:
```php
//create thread
$thread = new ExampleThread($loop);
//start thread and do external logic
$thread->start();

//wait for the thread to complete
$thread->on('stopped',function() use ($loop)
{
    //thread is done
    //stop the parent process
    $loop->stop();
});

//you can do any other operations here without affecting the thread

//run the loop to wait for completion
$loop->run();
```

For communicable threads see example_3.php and example_4.php

## More examples
See /examples folder

## TODO:
- Documentation
- Tests
- More examples
- https://github.com/reactphp/event-loop/issues/41
- Stability / Error handling

If you have any issues or feature request, feel free to create a ticket
