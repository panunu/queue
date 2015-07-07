<?php

/**
 * This file is part of the pekkis-queue package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pekkis\Queue\Processor;

use Pekkis\Queue\SymfonyBridge\EventDispatchingQueue;
use Pekkis\Queue\SymfonyBridge\MessageEvent;
use Pekkis\Queue\Queue;
use Pekkis\Queue\Message;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Closure;

/**
 * Processes messages from queue
 */
class Processor
{
    /**
     *
     * @var Queue
     */
    protected $queue;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var MessageHandler[]
     */
    protected $handlers = array();

    /**
     * @param Queue $queue
     */
    public function __construct(EventDispatchingQueue $queue)
    {
        $this->queue = $queue;
        $this->eventDispatcher = $queue->getEventDispatcher();
    }

    /**
     * @return EventDispatchingQueue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param MessageHandler $handler
     */
    public function registerHandler(MessageHandler $handler)
    {
        array_unshift($this->handlers, $handler);
    }

    /**
     * @param callable $callback
     */
    public function processWhile(Closure $callback)
    {
        do {
            $ret = $this->process();
        } while ($callback($ret));
    }

    /**
     * Processes a single message from the queue
     *
     * @return boolean True if processed, false if queue is empty.
     */
    public function process()
    {
        try {
            $message = $this->queue->dequeue();
        } catch (\Exception $e) {
            return false;
        }

        if (!$message) {
            $this->eventDispatcher->dispatch(Events::QUEUE_EMPTY);
            return false;
        }

        $this->eventDispatcher->dispatch(Events::MESSAGE_RECEIVE, new MessageEvent($message));

        $result = $this->handleMessage($message);
        if (!$result) {

            $this->eventDispatcher->dispatch(Events::MESSAGE_NOT_HANDLABLE, new MessageEvent($message));
            throw new \RuntimeException(sprintf("No handler will handle a message of type '%s'", $message->getType()));
        }

        if ($result->isSuccess()) {
            $this->queue->ack($message);
        }
        return true;
    }

    /**
     * @param Message $message
     * @return Result
     */
    private function handleMessage(Message $message)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->willHandle($message)) {
                $this->eventDispatcher->dispatch(Events::MESSAGE_BEFORE_HANDLE, new MessageEvent($message));

                $ret = $handler->handle($message, $this->queue);

                $this->eventDispatcher->dispatch(
                    Events::MESSAGE_AFTER_HANDLE,
                    new ResultEvent($ret, $message)
                );

                return $ret;
            }
        }
        return false;
    }
}
