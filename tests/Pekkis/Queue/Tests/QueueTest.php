<?php

namespace Pekkis\Queue\Tests;

use Pekkis\Queue\Data\SerializedData;
use Pekkis\Queue\Queue;
use Pekkis\Queue\Message;

class QueueTest extends \Pekkis\Queue\Tests\TestCase
{

    private $adapter;

    /**
     * @var Queue
     */
    private $queue;

    public function setUp()
    {
        $this->adapter = $this->getMock('Pekkis\Queue\Adapter\Adapter');
        $this->queue = new Queue($this->adapter);
    }

    /**
     * @test
     */
    public function enqueueDelegates()
    {
        $this->adapter
            ->expects($this->once())
            ->method('enqueue')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($str) {
                        return $str;
                    }
                )
            );

        $output = $this->queue->enqueue('test-message', array('aybabtu' => 'lussentus'));
        return $output;
    }

    /**
     * @test
     */
    public function dequeueDelegates()
    {
        $serialized = new SerializedData('Pekkis\Queue\Data\BasicDataSerializer', serialize('lussentus'));
        $arr = array(
            'uuid' => 'uuid',
            'type' => 'lus.tus',
            'data' => $serialized->toJson()
        );
        $input = json_encode($arr);

        $this->adapter->
            expects($this->once())
            ->method('dequeue')
            ->will($this->returnValue(array($input, 'aybabtu')));

        $dequeued = $this->queue->dequeue();
        $this->assertInstanceof('Pekkis\Queue\Message', $dequeued);

        $this->assertEquals('aybabtu', $dequeued->getIdentifier());
        $this->assertEquals('lus.tus', $dequeued->getType());
        $this->assertEquals('lussentus', $dequeued->getData());
    }

    /**
     * @test
     */
    public function dequeueReturnsFalseWhenQueueEmpty()
    {
        $this->adapter->
            expects($this->any())
            ->method('dequeue')
            ->will($this->returnValue(false));

        $this->assertFalse($this->queue->dequeue());
    }


    /**
     * @test
     */
    public function ackDelegates()
    {
        $message = Message::create('test-message', array('aybabtu' => 'lussentus'));
        $this->adapter->expects($this->once())->method('ack')->will($this->returnValue('luslus'));

        $this->assertSame('luslus', $this->queue->ack($message));
    }

    /**
     * @test
     */
    public function purgeDelegates()
    {
        $this->adapter->expects($this->once())->method('purge')->will($this->returnValue(true));
        $this->assertTrue($this->queue->purge());
    }

    /**
     * @test
     */
    public function unknownDataThrowsExceptionWhenSerializing()
    {
        $this->setExpectedException('RuntimeException', 'Serializer not found');
        $this->queue->enqueue('lus.tus', new RandomBusinessObject());
    }

    /**
     * @test
     */
    public function unknownDataThrowsExceptionWhenUnserializing()
    {
        $this->setExpectedException('RuntimeException', 'Unserializer not found');

        $serialized = new SerializedData('SomeRandomSerializer', 'xooxoo');

        $arr = array(
            'uuid' => 'uuid',
            'type' => 'lus.tus',
            'data' => $serialized->toJson()
        );
        $json = json_encode($arr);

        $this->adapter->
            expects($this->once())
            ->method('dequeue')
            ->will($this->returnValue(array($json, 'aybabtu')));

        $this->queue->dequeue();
    }

    /**
     * @test
     */
    public function addsOutputFilter()
    {
        $ret = $this->queue->addOutputFilter(
            function () {

            }
        );
        $this->assertSame($this->queue, $ret);
    }

    /**
     * @test
     */
    public function addsInputFilter()
    {
        $ret = $this->queue->addInputFilter(
            function () {

            }
        );
        $this->assertSame($this->queue, $ret);
    }
}
