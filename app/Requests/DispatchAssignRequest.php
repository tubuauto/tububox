<?php

declare(strict_types=1);

namespace App\Requests;

final class DispatchAssignRequest
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validate(array $input): array
    {
        $errors = [];

        if ((int) ($input['delivery_id'] ?? 0) <= 0) {
            $errors['delivery_id'] = 'delivery_id is required.';
        }

        if ((int) ($input['driver_id'] ?? 0) <= 0) {
            $errors['driver_id'] = 'driver_id is required.';
        }

        return $errors;
    }
}

