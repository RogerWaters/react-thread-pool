<?php
/**
 * Created by PhpStorm.
 * User: RogerWaters
 * Date: 19.01.2016
 * Time: 16:15
 */
include ('./../src/Protocol/BinaryBuffer.php');

$text = "Hallo Weltäöß$%&/";
var_dump(\Protocol\BinaryBuffer::EncodeMessage($text));

$buffer = new \Protocol\BinaryBuffer();
$buffer->PushData(\Protocol\BinaryBuffer::EncodeMessage($text).\Protocol\BinaryBuffer::EncodeMessage($text).\Protocol\BinaryBuffer::EncodeMessage($text));

var_dump($buffer->HasMessages(),$buffer->GetMessages(),$buffer);