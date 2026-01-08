<?php

namespace App\Helpers;

class ContentFilter
{
    /**
     * Whitelisted usernames (not to be censored)
     */
    protected static $whitelist = [
        '@mangoyen',
    ];

    /**
     * Patterns to censor for chat moderation
     * Prevents exchange of contact info before payment
     */
    protected static $patterns = [
        // Phone patterns (Indonesia) - +62, 62, 08xxx
        'phone' => '/(\+62|62|0)(\s*[-.]?\s*)?\d{2,4}(\s*[-.]?\s*)?\d{2,4}(\s*[-.]?\s*)?\d{2,4}/u',

        // Email patterns
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u',

        // WhatsApp links
        'whatsapp' => '/wa\.me\/\d+/ui',

        // Telegram links
        'telegram' => '/t\.me\/\w+/ui',

        // General URLs (http/https)
        'url' => '/https?:\/\/[^\s]+/ui',

        // Social media usernames - exclude @mangoyen using negative lookahead
        'instagram' => '/@(?!mangoyen\b)[a-zA-Z0-9_.]{3,30}(?=\s|$|[.,!?])/ui',

        // Bank account patterns
        'bank' => '/\b\d{10,16}\b/u', // 10-16 digit numbers (likely bank accounts)

        // Address patterns (Indonesian)
        'address' => '/\b(jl\.|jln\.|jalan|gang|gg\.|blok|perumahan|perum|komplek|kav\.|kavling)\s+[a-zA-Z0-9\s.,]+\s*(no\.?|nomor)?\s*\d+[a-zA-Z]?/ui',
    ];

    /**
     * Replacement masks
     */
    protected static $masks = [
        'phone' => '📞[disensor]',
        'email' => '📧[disensor]',
        'whatsapp' => '🔗[wa-disensor]',
        'telegram' => '🔗[tg-disensor]',
        'url' => '🔗[link-disensor]',
        'instagram' => '📱[ig-disensor]',
        'bank' => '💳[no-rek-disensor]',
        'address' => '📍[alamat-disensor]',
    ];

    /**
     * Filter/censor a message text
     * 
     * @param string $text The message text to filter
     * @return array ['text' => censored text, 'censored' => bool, 'types' => array of types censored]
     */
    public static function filter(?string $text): array
    {
        if (empty($text)) {
            return [
                'text' => $text,
                'censored' => false,
                'types' => [],
            ];
        }

        $censored = false;
        $types = [];
        $result = $text;

        foreach (self::$patterns as $type => $pattern) {
            if (preg_match($pattern, $result)) {
                $result = preg_replace($pattern, self::$masks[$type], $result);
                $censored = true;
                $types[] = $type;
            }
        }

        return [
            'text' => $result,
            'censored' => $censored,
            'types' => $types,
        ];
    }

    /**
     * Check if text contains suspicious content without modifying it
     * 
     * @param string $text The text to check
     * @return array ['suspicious' => bool, 'types' => array of suspicious types]
     */
    public static function check(?string $text): array
    {
        if (empty($text)) {
            return ['suspicious' => false, 'types' => []];
        }

        $types = [];
        foreach (self::$patterns as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                $types[] = $type;
            }
        }

        return [
            'suspicious' => count($types) > 0,
            'types' => $types,
        ];
    }

    /**
     * Get warning message for censored content
     * 
     * @param array $types Types of content that were censored
     * @return string Warning message
     */
    public static function getWarningMessage(array $types): string
    {
        $warnings = [
            'phone' => 'nomor telepon',
            'email' => 'email',
            'whatsapp' => 'link WhatsApp',
            'telegram' => 'link Telegram',
            'url' => 'link/URL',
            'instagram' => 'username Instagram',
            'bank' => 'nomor rekening',
            'address' => 'alamat',
        ];

        $detected = array_map(fn($t) => $warnings[$t] ?? $t, $types);
        return 'Pesan berisi ' . implode(', ', $detected) . ' yang disensor untuk keamanan transaksi.';
    }

    /**
     * Get strike warning message (shown to user after censored message)
     */
    public static function getStrikeWarning(): string
    {
        return '⚠️ PERINGATAN: Mengirim informasi terlarang (nomor telepon, email, rekening, alamat) dapat mengakibatkan STRIKE pada akun Anda. 3x strike = BAN PERMANEN!';
    }
}
