<?php

namespace TheStreamable\ClearCloudflareCache;

use Kirby\Cms\Page;
use Kirby\Http\Remote;
use Kirby\Toolkit\Collection;

class CloudflareCache
{
    protected const API_URL_BATCH_SIZE = 30;

    public static function handlePageHook($hook, $page, $oldPage = null)
    {
        $callback = option('thestreamable.clearcloudflarecache.dependantUrlsForPage');
        if ($callback && is_callable($callback)) {
            static::purgeURLs($callback($hook, $page, $oldPage));
        }
    }

    public static function handleFileHook($hook, $file, $oldFile = null)
    {
        $callback = option('thestreamable.clearcloudflarecache.dependantUrlsForFile');
        if ($callback && is_callable($callback)) {
            static::purgeURLs($callback($hook, $file, $oldFile));
        }
    }

    public static function handleSiteHook()
    {
        static::purgeAll();
    }

    public static function purgeAll()
    {
        $cloudflareZone = option('thestreamable.clearcloudflarecache.cloudflareZoneID');
        $cloudflareToken = option('thestreamable.clearcloudflarecache.cloudflareToken');
        if (empty($cloudflareZone || empty($cloudflareToken))) {
            return;
        }

        $r = Remote::post('https://api.cloudflare.com/client/v4/zones/' . $cloudflareZone . '/purge_cache', [
            'headers' => [
                'Authorization: Bearer ' . $cloudflareToken,
                'Content-Type: application/json',
            ],
            'data' => json_encode([
                'purge_everything' => true,
            ]),
        ]);
    }

    public static function purgeURLs($pagesOrURLs)
    {
        if (!$pagesOrURLs) {
            return;
        }

        $cloudflareZone = option('thestreamable.clearcloudflarecache.cloudflareZoneID');
        $cloudflareToken = option('thestreamable.clearcloudflarecache.cloudflareToken');
        if (empty($cloudflareZone || empty($cloudflareToken))) {
            return;
        }

        if ($pagesOrURLs instanceof Collection) {
            $pagesOrURLs = $pagesOrURLs->pluck('url');
        }
        elseif ($pagesOrURLs instanceof Page) {
            $pagesOrURLs = [$pagesOrURLs->url()];
        }
        elseif (!is_array($pagesOrURLs)) {
            $pagesOrURLs = [$pagesOrURLs];
        }

        $pagesOrURLs = array_map(function($urlItem) {
            return $urlItem instanceof Page ? $urlItem->url() : (string)$urlItem;
        }, $pagesOrURLs);

        $pagesOrURLs = array_unique($pagesOrURLs);
        if (!count($pagesOrURLs)) {
            return;
        }

        foreach (array_chunk($pagesOrURLs, static::API_URL_BATCH_SIZE) as $urlBatch) {
            Remote::post('https://api.cloudflare.com/client/v4/zones/' . $cloudflareZone . '/purge_cache', [
                'headers' => [
                    'Authorization: Bearer ' . $cloudflareToken,
                    'Content-Type: application/json',
                ],
                'data' => json_encode([
                    'files' => array_values($urlBatch),
                ]),
            ]);
        }
    }
}
