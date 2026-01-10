<?php
/**
 * CRC Input Validators
 * Validation rules and helpers
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Validator {
    private array $errors = [];
    private array $data = [];

    /**
     * Create validator with data
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Static factory method
     */
    public static function make(array $data): self {
        return new self($data);
    }

    /**
     * Validate email
     */
    public function email(string $field, bool $required = true): self {
        $value = $this->data[$field] ?? null;

        if ($required && empty($value)) {
            $this->errors[$field] = 'Email is required';
            return $this;
        }

        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email format';
        }

        return $this;
    }

    /**
     * Validate password
     */
    public function password(string $field, bool $required = true): self {
        $value = $this->data[$field] ?? null;

        if ($required && empty($value)) {
            $this->errors[$field] = 'Password is required';
            return $this;
        }

        if ($value) {
            if (strlen($value) < PASSWORD_MIN_LENGTH) {
                $this->errors[$field] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
            } elseif (!preg_match('/[A-Za-z]/', $value) || !preg_match('/[0-9]/', $value)) {
                $this->errors[$field] = 'Password must contain letters and numbers';
            }
        }

        return $this;
    }

    /**
     * Validate required string
     */
    public function required(string $field, string $label = null): self {
        $value = $this->data[$field] ?? null;
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));

        if (empty($value) || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "{$label} is required";
        }

        return $this;
    }

    /**
     * Validate string length
     */
    public function length(string $field, int $min = null, int $max = null, string $label = null): self {
        $value = $this->data[$field] ?? '';
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        $len = mb_strlen($value);

        if ($min !== null && $len < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters";
        }

        if ($max !== null && $len > $max) {
            $this->errors[$field] = "{$label} must be no more than {$max} characters";
        }

        return $this;
    }

    /**
     * Validate matches another field
     */
    public function matches(string $field, string $otherField, string $label = null): self {
        $value = $this->data[$field] ?? null;
        $otherValue = $this->data[$otherField] ?? null;
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));

        if ($value !== $otherValue) {
            $this->errors[$field] = "{$label} does not match";
        }

        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone(string $field, bool $required = false): self {
        $value = $this->data[$field] ?? null;

        if ($required && empty($value)) {
            $this->errors[$field] = 'Phone number is required';
            return $this;
        }

        if ($value) {
            // Remove spaces and dashes
            $cleaned = preg_replace('/[\s\-]/', '', $value);

            // South African phone validation
            if (!preg_match('/^(\+27|0)[0-9]{9}$/', $cleaned)) {
                $this->errors[$field] = 'Invalid phone number format';
            }
        }

        return $this;
    }

    /**
     * Validate integer
     */
    public function integer(string $field, int $min = null, int $max = null): self {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            if (!is_numeric($value) || (int)$value != $value) {
                $this->errors[$field] = 'Must be a whole number';
                return $this;
            }

            $value = (int)$value;

            if ($min !== null && $value < $min) {
                $this->errors[$field] = "Must be at least {$min}";
            }

            if ($max !== null && $value > $max) {
                $this->errors[$field] = "Must be no more than {$max}";
            }
        }

        return $this;
    }

    /**
     * Validate date
     */
    public function date(string $field, string $format = 'Y-m-d', bool $required = false): self {
        $value = $this->data[$field] ?? null;

        if ($required && empty($value)) {
            $this->errors[$field] = 'Date is required';
            return $this;
        }

        if ($value) {
            $date = DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->errors[$field] = 'Invalid date format';
            }
        }

        return $this;
    }

    /**
     * Validate is in list
     */
    public function in(string $field, array $allowed, string $label = null): self {
        $value = $this->data[$field] ?? null;
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));

        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "Invalid {$label}";
        }

        return $this;
    }

    /**
     * Validate URL
     */
    public function url(string $field, bool $required = false): self {
        $value = $this->data[$field] ?? null;

        if ($required && empty($value)) {
            $this->errors[$field] = 'URL is required';
            return $this;
        }

        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field] = 'Invalid URL format';
        }

        return $this;
    }

    /**
     * Validate boolean
     */
    public function boolean(string $field): self {
        $value = $this->data[$field] ?? null;

        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
            $this->errors[$field] = 'Must be true or false';
        }

        return $this;
    }

    /**
     * Custom validation
     */
    public function custom(string $field, callable $callback, string $message): self {
        $value = $this->data[$field] ?? null;

        if (!$callback($value, $this->data)) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Get first error
     */
    public function firstError(): ?string {
        return reset($this->errors) ?: null;
    }

    /**
     * Get validated data (only fields that were validated)
     */
    public function validated(): array {
        // Return only fields that don't have errors
        $validated = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validated[$key] = $value;
            }
        }
        return $validated;
    }

    /**
     * Validate or fail with JSON response
     */
    public function validateOrFail(): array {
        if ($this->fails()) {
            Response::validationError($this->errors());
        }
        return $this->validated();
    }
}

/**
 * Helper function for quick validation
 */
function validate(array $data): Validator {
    return Validator::make($data);
}
