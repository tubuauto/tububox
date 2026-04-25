<?php

declare(strict_types=1);

namespace App\Domain;

final class DeliveryStatus
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PENDING = 'pending';
    public const DISPATCH_PENDING = 'dispatch_pending';
    public const ASSIGNED = 'assigned';
    public const DRIVER_ARRIVING_PICKUP = 'driver_arriving_pickup';
    public const PICKED_UP = 'picked_up';
    public const IN_TRANSIT = 'in_transit';
    public const ARRIVED = 'arrived';
    public const SIGNED = 'signed';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    /**
     * @var array<string, array<int, string>>
     */
    private const FLOW = [
        self::AWAITING_PAYMENT => [self::PENDING, self::CANCELLED],
        self::PENDING => [self::ASSIGNED, self::CANCELLED],
        self::DISPATCH_PENDING => [self::ASSIGNED, self::FAILED, self::CANCELLED],
        self::ASSIGNED => [self::DRIVER_ARRIVING_PICKUP, self::CANCELLED, self::FAILED],
        self::DRIVER_ARRIVING_PICKUP => [self::PICKED_UP, self::FAILED],
        self::PICKED_UP => [self::IN_TRANSIT, self::FAILED],
        self::IN_TRANSIT => [self::ARRIVED, self::FAILED],
        self::ARRIVED => [self::SIGNED],
        self::SIGNED => [self::COMPLETED],
        self::COMPLETED => [],
        self::FAILED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::FLOW[$from] ?? [], true);
    }
}
