<?php

namespace Kyqo\Broadcasting;

/**
 * Implement this interface on any event class to mark it as broadcastable.
 *
 * Usage:
 *   class OrderShipped implements ShouldBroadcast
 *   {
 *       public function __construct(public Order $order) {}
 *
 *       public function broadcastOn(): array
 *       {
 *           return [new Channel('orders.' . $this->order->id)];
 *       }
 *
 *       public function broadcastWith(): array
 *       {
 *           return ['order_id' => $this->order->id, 'status' => $this->order->status];
 *       }
 *
 *       public function broadcastAs(): string
 *       {
 *           return 'order.shipped'; // event name on the JS side
 *       }
 *   }
 */
interface ShouldBroadcast
{
    /**
     * Return the channels to broadcast on.
     * Each item may be a Channel instance or a plain string.
     */
    public function broadcastOn(): array|Channel;
}
