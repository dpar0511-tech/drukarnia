<?php

namespace App\Traits;

use App\Models\Parameters\{Material, MaterialSize};
use App\Exceptions\PriceCalculationException;

trait MaterialPickerTrait
{
    private function pickSize(Material $material, ?int $mediaFormatId): MaterialSize
    {
        $q = $material->sizes()->with('mediaFormat');
        if ($mediaFormatId !== null) {
            $size = (clone $q)->where('media_format_id', $mediaFormatId)->first();
            if ($size) return $size;
        }
        $size = $q->first();
        if (!$size) {
            throw new PriceCalculationException("Materiał '{$material->name}' nie ma zdefiniowanego rozmiaru (MaterialSize).");
        }
        return $size;
    }
}
