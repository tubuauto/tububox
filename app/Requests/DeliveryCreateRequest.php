<?php

declare(strict_types=1);

namespace App\Requests;

final class DeliveryCreateRequest
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_SOURCE_TYPES = ['marketplace', 'merchant_dashboard', 'merchant_api'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $pickup = is_array($input['pickup'] ?? null) ? $input['pickup'] : [];
        $dropoff = is_array($input['dropoff'] ?? null) ? $input['dropoff'] : [];

        if (trim((string) ($pickup['name'] ?? '')) === '') {
            $errors['pickup.name'] = 'Pickup name is required.';
        }
        if (trim((string) ($pickup['address'] ?? '')) === '') {
            $errors['pickup.address'] = 'Pickup address is required.';
        }
        if (trim((string) ($dropoff['name'] ?? '')) === '') {
            $errors['dropoff.name'] = 'Dropoff name is required.';
        }
        if (trim((string) ($dropoff['address'] ?? '')) === '') {
            $errors['dropoff.address'] = 'Dropoff address is required.';
        }

        $sourceType = strtolower(trim((string) ($input['source_type'] ?? '')));
        if ($sourceType === '') {
            $errors['source_type'] = 'source_type is required.';
        } elseif (!in_array($sourceType, self::ALLOWED_SOURCE_TYPES, true)) {
            $errors['source_type'] = 'source_type must be one of: marketplace, merchant_dashboard, merchant_api.';
        }

        if (array_key_exists('store_id', $input) && trim((string) $input['store_id']) !== '') {
            if ((int) $input['store_id'] <= 0) {
                $errors['store_id'] = 'store_id must be a positive integer.';
            }
        }

        $cod = is_array($input['cod'] ?? null) ? $input['cod'] : [];
        if (($cod['required'] ?? false) && (int) ($cod['amount_cents'] ?? 0) <= 0) {
            $errors['cod.amount_cents'] = 'COD amount_cents must be greater than 0 when COD is required.';
        }

        return $errors;
    }
}
