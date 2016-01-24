# react-thread-pool

This is a multiprocessing library for PHP based on pcntl_fork().<br />
<br />
Status: alpha<br />
Tests: none<br />

## Basic Usage

Create an EventLoop:
If you have pect/libevent installed:
<pre><code class="highlight highlight-text-html-php">
$loop = new ForkableLibEventLoop();
</code></pre>
<br />
Else use:
<pre><code class="highlight highlight-text-html-php">
$loop = new ForkableStreamSelectEventLoop();
</code></pre>
<br/>
<br/>
Creating an default thread to do heavy work outside your parent process
<pre><code class="highlight highlight-text-html-php">
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
</code></pre>
<br/>

## Requirements
- Linux/Unix platform
- PHP >= 5.4
- <a href="https://github.com/reactphp/event-loop" target="_blank">reactphp/event-loop</a>
- <a href="https://github.com/igorw/evenement" target="_blank">igorw/evenement</a>
- [optional] Libevent (pect/libevent-0.1.0)

## Examples
Examples are within the example folder.

## TODO:
- Documentation
- Tests
- More examples
- Ticket on react/event-loop how to consolidate the EventLoop Interfaces

If you have any issues or feature request, feel free to create an ticket
