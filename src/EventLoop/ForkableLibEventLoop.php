<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 20.01.2016
 * Time: 08:23
 * original by react/event-loop
 * copied until some changes are applied to the react project
 * TODO: https://github.com/reactphp/event-loop/issues/41
 */

namespace RogerWaters\ReactThreads\EventLoop;


use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Tick\NextTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use SplObjectStorage;

class ForkableLibEventLoop implements ForkableLoopInterface
{
    const MICROSECONDS_PER_SECOND = 1000000;

    private $eventBase;
    private $nextTickQueue;
    private $futureTickQueue;
    private $timerCallback = null;
    private $timerEvents;
    private $streamCallback = null;
    private $streamEvents = [];
    private $streamFlags = [];
    private $readListeners = [];
    private $writeListeners = [];
    private $running;

    public function __construct()
    {
        $this->eventBase = event_base_new();
        $this->nextTickQueue = new NextTickQueue($this);
        $this->futureTickQueue = new FutureTickQueue($this);
        $this->timerEvents = new SplObjectStorage();
        if($this->timerCallback === null)
        {
            $this->createTimerCallback();
        }
        if($this->streamCallback === null)
        {
            $this->createStreamCallback();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readListeners[$key])) {
            $this->readListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, EV_READ);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->writeListeners[$key])) {
            $this->writeListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, EV_WRITE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readListeners[$key])) {
            unset($this->readListeners[$key]);
            $this->unsubscribeStreamEvent($stream, EV_READ);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeListeners[$key])) {
            unset($this->writeListeners[$key]);
            $this->unsubscribeStreamEvent($stream, EV_WRITE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $event = $this->streamEvents[$key];

            event_del($event);
            event_free($event);

            unset(
                $this->streamFlags[$key],
                $this->streamEvents[$key],
                $this->readListeners[$key],
                $this->writeListeners[$key]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);

        $this->scheduleTimer($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);

        $this->scheduleTimer($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if ($this->isTimerActive($timer)) {
            $event = $this->timerEvents[$timer];

            event_del($event);
            event_free($event);

            $this->timerEvents->detach($timer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timerEvents->contains($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function nextTick(callable $listener)
    {
        $this->nextTickQueue->add($listener);
    }

    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        $this->nextTickQueue->tick();

        $this->futureTickQueue->tick();

        event_base_loop($this->eventBase, EVLOOP_ONCE | EVLOOP_NONBLOCK);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->nextTickQueue->tick();

            $this->futureTickQueue->tick();

            $flags = EVLOOP_ONCE;
            if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
                $flags |= EVLOOP_NONBLOCK;
            } elseif (!$this->streamEvents && !$this->timerEvents->count()) {
                break;
            }

            event_base_loop($this->eventBase, $flags);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }

    /**
     * Schedule a timer for execution.
     *
     * @param TimerInterface $timer
     */
    private function scheduleTimer(TimerInterface $timer)
    {
        $this->timerEvents[$timer] = $event = event_timer_new();

        event_timer_set($event, $this->timerCallback, $timer);
        event_base_set($event, $this->eventBase);
        event_add($event, $timer->getInterval() * self::MICROSECONDS_PER_SECOND);
    }

    /**
     * Create a new ext-libevent event resource, or update the existing one.
     *
     * @param stream  $stream
     * @param integer $flag   EV_READ or EV_WRITE
     */
    private function subscribeStreamEvent($stream, $flag)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $event = $this->streamEvents[$key];
            $flags = $this->streamFlags[$key] |= $flag;

            event_del($event);
            event_set($event, $stream, EV_PERSIST | $flags, $this->streamCallback);
        } else {
            $event = event_new();

            event_set($event, $stream, EV_PERSIST | $flag, $this->streamCallback);
            event_base_set($event, $this->eventBase);

            $this->streamEvents[$key] = $event;
            $this->streamFlags[$key] = $flag;
        }

        event_add($event);
    }

    /**
     * Update the ext-libevent event resource for this stream to stop listening to
     * the given event type, or remove it entirely if it's no longer needed.
     *
     * @param stream  $stream
     * @param integer $flag   EV_READ or EV_WRITE
     */
    private function unsubscribeStreamEvent($stream, $flag)
    {
        $key = (int) $stream;

        $flags = $this->streamFlags[$key] &= ~$flag;

        if (0 === $flags) {
            $this->removeStream($stream);

            return;
        }

        $event = $this->streamEvents[$key];

        event_del($event);
        event_set($event, $stream, EV_PERSIST | $flags, $this->streamCallback);
        event_add($event);
    }

    /**
     * Create a callback used as the target of timer events.
     *
     * A reference is kept to the callback for the lifetime of the loop
     * to prevent "Cannot destroy active lambda function" fatal error from
     * the event extension.
     */
    private function createTimerCallback()
    {
        $this->timerCallback = function ($_, $_, $timer) {
            call_user_func($timer->getCallback(), $timer);

            // Timer already cancelled ...
            if (!$this->isTimerActive($timer)) {
                return;

                // Reschedule periodic timers ...
            } elseif ($timer->isPeriodic()) {
                event_add(
                    $this->timerEvents[$timer],
                    $timer->getInterval() * self::MICROSECONDS_PER_SECOND
                );

                // Clean-up one shot timers ...
            } else {
                $this->cancelTimer($timer);
            }
        };
    }

    /**
     * Create a callback used as the target of stream events.
     *
     * A reference is kept to the callback for the lifetime of the loop
     * to prevent "Cannot destroy active lambda function" fatal error from
     * the event extension.
     */
    private function createStreamCallback()
    {
        $this->streamCallback = function ($stream, $flags) {
            $key = (int) $stream;

            if (EV_READ === (EV_READ & $flags) && isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream, $this);
            }

            if (EV_WRITE === (EV_WRITE & $flags) && isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream, $this);
            }
        };
    }

    /**
     * called after each fork in child instance.
     * should clean up ever resource bound to the parent process
     * and should return an new stable instance or itself if reusable
     * @return ForkableLoopInterface
     */
    public function afterForkChild()
    {
        //possible event base free?
        unset($this->eventBase);
        unset($this->nextTickQueue);
        unset($this->futureTickQueue);
        //bugfix lambda function destructed while executing -.-
        //unset($this->timerCallback);
        //unset($this->streamCallback);
        unset($this->timerEvents);
        unset($this->running);
        $this->streamEvents = array();
        $this->streamFlags = array();
        $this->readListeners = array();
        $this->writeListeners = array();
        //call constructor
        $this->__construct();
        return $this;
    }

    /**
     * called after fork in the parent instance.
     * This gives the ability to continue the loop
     */
    public function afterForkParent()
    {
        event_base_reinit($this->eventBase);
    }
}