<?php

namespace App\Support;

final class ContactMethod
{
    public const KEYS = [
        'phone',
        'email',
        'whatsapp',
        'messenger',
        'social_chat',
    ];

    public static function label(string $key): string
    {
        if ($key === '') {
            return '—';
        }

        return match ($key) {
            'phone' => 'Phone',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger (Meta)',
            'social_chat' => 'Social media chat',
            default => $key,
        };
    }
}
