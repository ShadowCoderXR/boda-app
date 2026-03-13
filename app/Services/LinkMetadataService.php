<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkMetadataService
{
    protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    public function getMetadata(string $url): array
    {
        $metadata = [
            'title' => null,
            'thumbnail' => null,
            'provider' => null,
        ];

        try {
            // Handle direct image URLs
            if (Str::endsWith(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), ['.jpg', '.jpeg', '.png', '.gif', '.webp'])) {
                return [
                    'title' => basename(parse_url($url, PHP_URL_PATH)),
                    'thumbnail' => $url,
                    'provider' => 'Image',
                ];
            }

            // Handle Google Images redirects/source links
            if (Str::contains($url, 'google') && Str::contains($url, 'imgurl=')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
                if (isset($query['imgurl'])) {
                    return [
                        'title' => 'Google Image',
                        'thumbnail' => $query['imgurl'],
                        'provider' => 'Google',
                    ];
                }
            }

            if (Str::contains($url, ['youtube.com', 'youtu.be'])) {
                return $this->getYoutubeMetadata($url);
            }

            if (Str::contains($url, 'tiktok.com')) {
                return $this->getTikTokMetadata($url);
            }

            // Fallback for other URLs including Pinterest
            return $this->getGenericMetadata($url);
        } catch (\Exception $e) {
            Log::error("Error fetching metadata for {$url}: ".$e->getMessage());

            return $metadata;
        }
    }

    protected function getYoutubeMetadata(string $url): array
    {
        $response = Http::withoutVerifying()->get('https://www.youtube.com/oembed', [
            'url' => $url,
            'format' => 'json',
        ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'title' => $data['title'] ?? null,
                'thumbnail' => $data['thumbnail_url'] ?? null,
                'provider' => 'YouTube',
            ];
        }

        return ['provider' => 'YouTube'];
    }

    protected function getTikTokMetadata(string $url): array
    {
        $response = Http::withoutVerifying()->get('https://www.tiktok.com/oembed', [
            'url' => $url,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'title' => $data['title'] ?? null,
                'thumbnail' => $data['thumbnail_url'] ?? null,
                'provider' => 'TikTok',
            ];
        }

        return ['provider' => 'TikTok'];
    }

    protected function getGenericMetadata(string $url): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ])
            ->timeout(8)
            ->get($url);

        if (! $response->successful()) {
            // Try one more time with a different UA if blocked
            $response = Http::withoutVerifying()->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->timeout(8)->get($url);
            if (! $response->successful()) {
                return ['provider' => parse_url($url, PHP_URL_HOST)];
            }
        }

        $html = $response->body();

        // Title detection
        $title = null;
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $title = $matches[1];
        } elseif (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $title = $matches[1];
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = $matches[1];
        }

        // Thumbnail detection
        $thumbnail = null;
        if (preg_match('/<meta[^>]+property=["\']og:image:secure_url["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $thumbnail = $matches[1];
        } elseif (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $thumbnail = $matches[1];
        } elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $thumbnail = $matches[1];
        } elseif (preg_match('/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            $thumbnail = $matches[1];
        } elseif (preg_match('/<meta[^>]+itemprop=["\']image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $thumbnail = $matches[1];
        }

        $host = parse_url($url, PHP_URL_HOST);
        $provider = Str::title(Str::before(Str::after($host, 'www.'), '.'));
        if (empty($provider) || $provider === 'Www') {
            $provider = Str::title(Str::before($host, '.'));
        }

        return [
            'title' => $title ? html_entity_decode($title) : null,
            'thumbnail' => $thumbnail,
            'provider' => $provider,
        ];
    }
}
