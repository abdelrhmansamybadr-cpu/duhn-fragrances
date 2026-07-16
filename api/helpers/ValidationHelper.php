<?php
/**
 * DUHN FRAGRANCES — Input Validation Helper
 */
class ValidationHelper
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $input)
    {
        $this->data = $input;
    }

    public function required(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field][] = "$label is required.";
        }
        return $this;
    }

    public function email(string $field): static
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'Invalid email address.';
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): static
    {
        $label = $label ?: ucfirst($field);
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) < $min) {
            $this->errors[$field][] = "$label must be at least $min characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): static
    {
        $label = $label ?: ucfirst($field);
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) > $max) {
            $this->errors[$field][] = "$label must not exceed $max characters.";
        }
        return $this;
    }

    public function egyptPhone(string $field): static
    {
        if (!empty($this->data[$field])) {
            $phone = preg_replace('/\D/', '', $this->data[$field]);
            if (!preg_match('/^01[0125]\d{8}$/', $phone)) {
                $this->errors[$field][] = 'Enter a valid Egyptian phone number (01xxxxxxxxx).';
            }
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst($field);
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "$label must be a number.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): static
    {
        $label = $label ?: ucfirst($field);
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field][] = "$label has an invalid value.";
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int)$value
            : $default;
    }
}
