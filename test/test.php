<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 16:15
 */
include ('./../vendor/autoload.php');
include ('./../src/ThreadBase.php');
include ('./../src/ThreadPool.php');
include ('./../src/Thread.php');
include ('./../src/ThreadPoolServer.php');
include ('./../src/Protocol/BinaryBuffer.php');

$loop = new \EventLoop\ForkableLibEventLoop();
$thread = new \RogerWaters\ReactThreads\ThreadBase($loop);