<?php

namespace PhpAmqpLib\Tests\Functional;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/**
 * Class TimeoutTest
 * @package PhpAmqpLib\Tests\Functional
 *
 * This testing is setting the server memory watermark to 0 so the server blocks publishers.
 * It needs sudo rabbitmqctl permissions in order to do so.
 * If anything goes well the test cleans up after itself.
 * If it fails you may need to run this by hand in bash: sudo rabbitmqctl set_vm_memory_high_watermark 0.4
 */
class TimeoutTest extends TestCase
{
    private static $queue_name = "test_timeout_queue";
    private static $message = "Test payload.";

    public function testWriteTimeoutNonblocking()
    {
        define('AMQP_WITHOUT_SIGNALS', false);
        $this->_testWriteTimeout();
    }

    public function testWriteTimeoutBlocking()
    {
        define('AMQP_WITHOUT_SIGNALS', true);
        $this->_testWriteTimeout();
    }

    public function testReadTimeoutNonblocking()
    {
        define('AMQP_WITHOUT_SIGNALS', false);
        $this->_testReadTimeout();
    }

    public function testReadTimeoutBlocking()
    {
        define('AMQP_WITHOUT_SIGNALS', true);
        $this->_testReadTimeout();
    }

    private function _testWriteTimeout()
    {
        /** @var AMQPStreamConnection $connection */
        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        /** @var AMQPChannel $channel */
        $channel = $connection->channel();
        $channel->queue_declare(self::$queue_name, false, false, false, false);

        exec("sudo rabbitmqctl set_vm_memory_high_watermark 0");

        /** @var AMQPMessage $msg */
        $msg = new AMQPMessage(self::$message);
        $raisedException = false;
        try {
            for ($i = 0; $i < 100000; $i++) {
                $channel->basic_publish($msg, '', self::$queue_name);
            }
        } catch (AMQPTimeoutException $ex) {
            $raisedException = true;
        }
        $this->assertTrue($raisedException);

        exec("sudo rabbitmqctl set_vm_memory_high_watermark 0.4");
    }

    private function _testReadTimeout()
    {
        /** @var AMQPStreamConnection $connection */
        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        /** @var AMQPChannel $channel */
        $channel = $connection->channel();
        $channel->queue_declare(self::$queue_name, false, false, false, false);

        exec("sudo rabbitmqctl set_vm_memory_high_watermark 0");

        /** @var AMQPMessage $msg */
        $msg = new AMQPMessage(self::$message);
        $raisedException = false;
        $channel->tx_select();
        $channel->basic_publish($msg, '', self::$queue_name);
        try {
            $channel->tx_commit();
        } catch (AMQPTimeoutException $ex) {
            $raisedException = true;
        }
        $this->assertTrue($raisedException);

        exec("sudo rabbitmqctl set_vm_memory_high_watermark 0.4");

    }

    public function tearDown()
    {
        $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $channel = $connection->channel();
        if ($channel) {
            $channel->queue_delete(self::$queue_name);
            $channel->close();
        }

        if ($connection) {
            $connection->close();
        }
    }
}