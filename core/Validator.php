<?php

declare(strict_types=1);

namespace Core;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function required(string $field, string $value): self
    {
        if ($value === '') {
            $this->errors[$field] = 'This field is required.';
        }

        return $this;
    }

    public function email(string $field, string $value): self
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Enter a valid email address.';
        }

        return $this;
    }

    public function length(string $field, string $value, int $min, int $max): self
    {
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            $this->errors[$field] = "Use between {$min} and {$max} characters.";
        }

        return $this;
    }

    public function slug(string $field, string $value): self
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->errors[$field] = 'Use a clean URL slug.';
        }

        return $this;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
