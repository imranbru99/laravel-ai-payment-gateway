<?php

namespace Truvo\Pay\Credentials;

use Illuminate\Support\Facades\Crypt;
use Truvo\Pay\Contracts\PaymentGatewayContract;

class CredentialVault
{
    /**
     * Encrypt array of credentials for database storage.
     */
    public static function encrypt(array $credentials): string
    {
        return Crypt::encryptString(json_encode($credentials, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decrypt credentials from database storage.
     */
    public static function decrypt(?string $encrypted): array
    {
        if (empty($encrypted)) {
            return [];
        }

        try {
            $json = Crypt::decryptString($encrypted);
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Mask credentials for display in Filament or API responses.
     * e.g. "sk_live_12345678abcdef" => "sk_live_••••cdef"
     */
    public static function mask(array $credentials, array $credentialFields = []): array
    {
        $secretNames = [];
        foreach ($credentialFields as $field) {
            if ($field instanceof CredentialField && $field->isSecret) {
                $secretNames[$field->name] = true;
            }
        }

        $masked = [];
        foreach ($credentials as $key => $value) {
            if (!is_string($value) || empty($value)) {
                $masked[$key] = $value;
                continue;
            }

            // Mask if explicitly marked secret, or if key contains common secret keywords
            $isSecret = isset($secretNames[$key]) ||
                str_contains(strtolower($key), 'secret') ||
                str_contains(strtolower($key), 'key') ||
                str_contains(strtolower($key), 'token') ||
                str_contains(strtolower($key), 'password');

            if ($isSecret) {
                $len = strlen($value);
                if ($len <= 4) {
                    $masked[$key] = '••••';
                } elseif ($len <= 8) {
                    $masked[$key] = '••••' . substr($value, -2);
                } else {
                    $prefix = substr($value, 0, 4);
                    $suffix = substr($value, -4);
                    $masked[$key] = $prefix . '••••' . $suffix;
                }
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Merge submitted form data with existing credentials, preserving original secret values
     * if the user submitted a masked placeholder (containing "••••").
     */
    public static function mergeWithExisting(array $submitted, array $existing): array
    {
        $merged = $existing;

        foreach ($submitted as $key => $value) {
            if (is_string($value) && str_contains($value, '••••')) {
                // User did not modify the secret field; keep existing value
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * Convert CredentialField array into Filament Form components dynamically.
     * Works with Filament 3 forms if installed, otherwise returns structured array.
     */
    public static function toFilamentComponents(array $fields): array
    {
        $components = [];

        foreach ($fields as $field) {
            if (!$field instanceof CredentialField) {
                continue;
            }

            // If Filament is installed, instantiate native Filament components
            if (class_exists(\Filament\Forms\Components\TextInput::class)) {
                $comp = match ($field->type) {
                    'password' => \Filament\Forms\Components\TextInput::make('credentials.' . $field->name)
                        ->label($field->label)
                        ->password()
                        ->revealable(),
                    'select' => \Filament\Forms\Components\Select::make('credentials.' . $field->name)
                        ->label($field->label)
                        ->options($field->options),
                    'textarea' => \Filament\Forms\Components\Textarea::make('credentials.' . $field->name)
                        ->label($field->label)
                        ->rows(3),
                    'boolean' => \Filament\Forms\Components\Toggle::make('credentials.' . $field->name)
                        ->label($field->label),
                    default => \Filament\Forms\Components\TextInput::make('credentials.' . $field->name)
                        ->label($field->label),
                };

                if ($field->required) {
                    $comp->required();
                }
                if ($field->placeholder) {
                    $comp->placeholder($field->placeholder);
                }
                if ($field->description) {
                    $comp->helperText($field->description);
                }
                if ($field->default !== null) {
                    $comp->default($field->default);
                }

                $components[] = $comp;
            } else {
                // Fallback metadata array
                $components[] = $field->toArray();
            }
        }

        return $components;
    }
}
