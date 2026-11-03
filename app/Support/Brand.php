<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The mark the group puts on its own screens. The wordmark is the fallback because a group with no
 * logo uploaded still has a name, and a broken image on the rail looks like a fault in the system.
 */
class Brand
{
    public static function logo(): ?string
    {
        return Cache::remember('peg:branding', 300, function () {
            return Setting::query()->where('key', 'logo')->where('group', 'brand')->value('value');
        });
    }

    public static function setLogo(?string $dataUrl): void
    {
        if ($dataUrl === null) {
            Setting::query()->where('key', 'logo')->delete();
        } else {
            Setting::query()->updateOrCreate(
                ['key' => 'logo', 'group' => 'brand'],
                ['value' => $dataUrl]
            );
        }

        Cache::forget('peg:branding');
    }
}
