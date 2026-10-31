<?php

namespace Lambda;

use Illuminate\Support\Facades\Config;

/**
 * config('lambda') as handed to the browser (window.lambda) — the login page
 * is public, so the backup section and any key that names a credential
 * (…_password, …_secret, …_token, api_key, …) are left out.
 */
class PublicConfig
{
    const HIDDEN_SECTIONS = ['backup'];

    // UI text (labels such as 'password' => 'Нууц үг'); not scanned for secrets.
    const TEXT_SECTIONS = ['static_words'];

    public static function get(): array
    {
        $config = Config::get('lambda', []);
        if (!is_array($config)) {
            return [];
        }
        foreach (self::HIDDEN_SECTIONS as $key) {
            unset($config[$key]);
        }
        return self::withoutSecrets($config);
    }

    private static function withoutSecrets(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::TEXT_SECTIONS, true)) {
                continue;
            }
            if (is_string($key) && preg_match('/(^|_)(pass|password|passwd|pwd|secret|token|api_?key|private_?key|credentials?)$/i', $key)) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = self::withoutSecrets($value);
            }
        }
        return $values;
    }
}
