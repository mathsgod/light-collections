<?php

declare(strict_types=1);

namespace Light\Collections\Generic;

/** @template T @implements EqualityComparer<T> */
final class DefaultEqualityComparer implements EqualityComparer
{
    public function equals(mixed $left, mixed $right): bool
    {
        if (is_float($left) && is_nan($left) && is_float($right) && is_nan($right)) {
            return true;
        }

        return $left === $right;
    }

    public function hash(mixed $value): string
    {
        return $this->hashValue($value, 0);
    }

    private function hashValue(mixed $value, int $depth): string
    {
        return match (true) {
            $value === null => 'null',
            is_object($value) => 'object:'.spl_object_id($value),
            is_resource($value) => 'resource:'.get_resource_id($value),
            is_float($value) && is_nan($value) => 'float:nan',
            is_float($value) && $value === 0.0 => 'float:0',
            is_float($value) => 'float:'.serialize($value),
            is_int($value) => 'int:'.$value,
            is_string($value) => 'string:'.$value,
            is_bool($value) => 'bool:'.($value ? '1' : '0'),
            is_array($value) => $this->hashArray($value, $depth),
            default => throw new \InvalidArgumentException('Unsupported value type.'),
        };
    }

    private function hashArray(array $value, int $depth): string
    {
        // Bound traversal for deeply nested or recursive arrays. Hash collisions
        // are resolved by equals(), so truncating a hash does not imply equality.
        if ($depth >= 32) {
            return 'array:depth-limit';
        }
        $context = hash_init('xxh128');
        foreach ($value as $key => $item) {
            foreach ([$this->hashValue($key, $depth + 1), $this->hashValue($item, $depth + 1)] as $part) {
                hash_update($context, strlen($part).':'.$part);
            }
        }
        return 'array:'.hash_final($context);
    }
}
