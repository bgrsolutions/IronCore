<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductImageDownloader;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        /** @var Product $product */
        $product = $this->record;

        $path = app(ProductImageDownloader::class)->downloadForProduct($product);
        if (! $path) {
            return;
        }

        $product->forceFill(['image_path' => $path])->saveQuietly();

        Notification::make()
            ->success()
            ->title('Product image downloaded successfully')
            ->send();
    }
}
