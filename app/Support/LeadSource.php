<?php

namespace App\Support;

final class LeadSource
{
    /** Ordered for select UI: social → paid → organic → other. */
    public const KEYS = [
        'social_instagram',
        'social_facebook',
        'social_tiktok',
        'social_x',
        'social_youtube',
        'social_linkedin',
        'google_ads',
        'website',
        'referral',
        'walk_in',
        'email_campaign',
        'phone_inquiry',
        'event',
        'partner',
        'other',
        'unknown',
    ];

    public static function label(string $key): string
    {
        return match ($key) {
            'social_instagram' => 'Instagram',
            'social_facebook' => 'Facebook',
            'social_tiktok' => 'TikTok',
            'social_x' => 'X (Twitter)',
            'social_youtube' => 'YouTube',
            'social_linkedin' => 'LinkedIn',
            'google_ads' => 'Google Ads',
            'website' => 'Website / organic',
            'referral' => 'Referral',
            'walk_in' => 'Walk-in',
            'email_campaign' => 'Email campaign',
            'phone_inquiry' => 'Phone inquiry',
            'event' => 'Event / pop-up',
            'partner' => 'Partner / B2B',
            'other' => 'Other',
            'unknown' => 'Not specified',
            default => $key,
        };
    }

    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            if ($key === 'unknown') {
                continue;
            }
            $group = match (true) {
                str_starts_with($key, 'social_') => 'Social media',
                $key === 'google_ads' => 'Paid search',
                default => 'Other funnels',
            };
            $out[] = ['value' => $key, 'label' => self::label($key), 'group' => $group];
        }

        return $out;
    }
}
