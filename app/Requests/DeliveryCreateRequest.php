<?php

declare(strict_types=1);

namespace App\Requests;

final class DeliveryCreateRequest
{
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

        $cod = is_array($input['cod'] ?? null) ? $input['cod'] : [];
        if (($cod['required'] ?? false) && (int) ($cod['amount_cents'] ?? 0) <= 0) {
            $errors['cod.amount_cents'] = 'COD amount_cents must be greater than 0 when COD is required.';
        }

        return $errors;
    }
}

