<?php

namespace App\Models;

class ClientWebsite extends WorkspaceModel
{
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function domainKey(string $domain): string
    {
        $url = preg_match('~^https?://~i', $domain) ? $domain : 'https://'.$domain;

        return strtolower(rtrim(parse_url($url, PHP_URL_HOST) ?? '', '.'));
    }
}
