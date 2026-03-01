<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageDownloader
{
    public function downloadForProduct(Product $product): ?string
    {
        if (! $product->image_url) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(10)
                ->withOptions([
                    'stream' => true,
                    'allow_redirects' => true,
                ])
                ->get($product->image_url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $contentLength = (int) $response->header('Content-Length', 0);
            if ($contentLength > 5 * 1024 * 1024) {
                return null;
            }

            $body = $response->body();
            if (strlen($body) > 5 * 1024 * 1024) {
                return null;
            }

            $extension = $this->resolveExtension($contentType);
            $filename = sprintf('products/%s-%s.%s', $product->id, now()->format('YmdHis'), $extension);

            Storage::disk('local')->put($filename, $body);

            return $filename;
        } catch (\Throwable $exception) {
            Log::warning('Product image download failed', [
                'product_id' => $product->id,
                'image_url' => $product->image_url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveExtension(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default => 'jpg',
        };
    }
}
