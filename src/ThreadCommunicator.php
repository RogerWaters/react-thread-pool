<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 31.01.2016
 * Time: 11:02
 */

namespace RogerWaters\ReactThreads;


use Evenement\EventEmitterTrait;
use React\EventLoop\Timer\TimerInterface;
use RogerWaters\ReactThreads\EventLoop\ForkableLoopInterface;
use RogerWaters\ReactThreads\Protocol\MessageFormat;

class ThreadCommunicator
{
    use EventEmitterTrait;

    /**
     * @var ForkableLoopInterface
     */
    private $loop;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var bool
     */
    private $isExternal = false;

    /**
     * @var bool
     */
    private $isRunning = false;

    /**
     * @var int
     */
    private $parentPid;

    /**
     * @var int
     */
    private $childPid;

    /**
     * @var ThreadConnection
     */
    private $connection;

    /**
     * @var MessageFormat[]
     */
    private $messagesToWaitFor = array();
    /**
     * @var IThreadComponent
     */
    private $messageHandler;

    /**
     * @var float
     */
    private $lastConnectionPing;

    /**
     * ThreadCommunicator constructor.
     * @param ForkableLoopInterface $loop
     * @param IThreadComponent $messageHandler
     */
    public function __construct(ForkableLoopInterface $loop, IThreadComponent $messageHandler)
    {
        $this->loop = $loop;
        $this->parentPid = posix_getpid();
        $this->messageHandler = $messageHandler;
        $this->lastConnectionPing = microtime(true);
    }

    public function fork()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        $this->isRunning = true;
        if ($pid <= -1) {
            $this->isRunning = false;
            throw new \RuntimeException("Unable to fork child: " . pcntl_strerror(pcntl_get_last_error()));
        } elseif ($pid === 0) {
            //child proc
            try {
                $this->isExternal = true;
                $this->childPid = posix_getpid();
                $this->loop = $this->loop->afterForkChild();

                fclose($sockets[1]);
                $this->socket = $sockets[0];
                $this->connection = new ThreadConnection($this->loop, $this->socket);
                $this->connection->on('message', function ($connection, $message) {
                    $this->AttachMessage($message);
                });
                $this->loop->nextTick(function () {
                    $this->messageHandler->InitializeExternal($this);
                });
                $this->loop->run();
            } catch (\Exception $e) {
                $this->emit('child_error', array($this, $e));
            }
            exit();
        } else {
            // parent
            fclose($sockets[0]);
            $this->socket = $sockets[1];

            $this->getLoop()->afterForkParent();

            $this->connection = new ThreadConnection($this->loop, $this->socket);
            $this->connection->on('message', function ($connection, $message) {
                $this->AttachMessage($message);
            });
            //keep an eye on the thread
            $this->getLoop()->addPeriodicTimer(5, function (TimerInterface $timer) {
                $donePid = pcntl_waitpid($this->childPid, $status, WNOHANG | WUNTRACED);
                switch ($donePid) {
                    case $this->childPid: //donePid is child pid
                        //child done
                        $this->isRunning = false;
                        $timer->cancel();
                        $this->emit('stopped', array($this->childPid, $status, $this));
                        break;
                    case 0: //donePid is empty
                        //everything fine.
                        //process still running
                        break;
                    case -1://donePid is unknown
                    default:
                        $this->emit('error', array(new \RuntimeException("$this->childPid PID returned unexpected status. Maybe its not a child of this.")));
                        break;
                }
            });
            $this->childPid = $pid;
        }
    }

    protected function AttachMessage(MessageFormat $message)
    {
        $this->lastConnectionPing = microtime(true);
        //resolved an previous call
        if (isset($this->messagesToWaitFor[$message->getId()]) && $message->isIsResolved()) {
            $this->messagesToWaitFor[$message->getId()]->Resolve($message->GetResult());
            unset($this->messagesToWaitFor[$message->getId()]);
        } else {
            $result = $this->messageHandler->handleMessage($this, $message->GetPayload());
            $message->Resolve($result);
            //process answer
            if ($message->isIsSync()) {
                //the calling thread is waiting for an answer so send as fast as possible
                $this->connection->writeSync($message);
            } else {
                //the calling thread dies not really care on time critical operation
                //send if we have time to do. Will at least be submitted with the next sync message
                $this->connection->writeAsync($message);
            }
        }
        //no answer has to be processed :-)
    }

    public function SendMessageAsync($message, callable $resolveCallback = null)
    {
        $message = new MessageFormat($message, false, $resolveCallback);
        $this->messagesToWaitFor[$message->getId()] = $message;

        $this->connection->writeAsync($message);
        return $message;
    }


    public function SendMessageSync($message)
    {
        $message = new MessageFormat($message, true);
        $this->messagesToWaitFor[$message->getId()] = $message;
        $this->connection->writeSync($message);

        //read until message is resolved
        while ($message->isIsResolved() === false) {
            $this->connection->readSync();
        }
        return $message->GetResult();
    }

    /**
     * @return ForkableLoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @return boolean
     */
    public function isIsExternal()
    {
        return $this->isExternal;
    }

    /**
     * @return boolean
     */
    public function isIsRunning()
    {
        return $this->isRunning;
    }

    /**
     * @return int
     */
    public function getParentPid()
    {
        return $this->parentPid;
    }

    /**
     * @return int
     */
    public function getChildPid()
    {
        return $this->childPid;
    }

    public function kill()
    {
        if ($this->isIsExternal()) {
            exit();
        } else {
            if ($this->isIsRunning()) {
                posix_kill($this->getChildPid(), SIGTERM);
            }
        }
    }


    /**
     * @return float
     */
    public function getSecondsSinceLastMessage()
    {
        return microtime(true) - $this->lastConnectionPing;
    }
}