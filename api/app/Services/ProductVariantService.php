<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    /** Stable identities survive reordering; legacy clients can match by name/value. */
    public static function normalize(?array $variants, ?array $existing = null): array
    {
        $result = [];
        $used = [];
        foreach ($variants ?? [] as $variant) {
            if (!is_array($variant) || !is_string($variant['name'] ?? null)
                || !is_numeric($variant['stock'] ?? null) || $variant['stock'] < 0
                || floor((float) $variant['stock']) !== (float) $variant['stock']) {
                throw ValidationException::withMessages(['variants' => 'Cada variante requiere un nombre y stock entero no negativo.']);
            }
            $id = $variant['id'] ?? null;
            if ($id && !Str::isUuid($id)) throw ValidationException::withMessages(['variants' => 'Identificador de variante inválido.']);
            if (!$id) {
                $matches = array_filter($existing ?? [], fn ($old) => ($old['name'] ?? '') === $variant['name']
                    && ($old['value'] ?? '') === ($variant['value'] ?? '') && !isset($used[$old['id'] ?? '']));
                if (count($matches) === 1) $id = array_values($matches)[0]['id'] ?? null;
            }
            $id ??= (string) Str::uuid();
            if (isset($used[$id])) throw ValidationException::withMessages(['variants' => 'Las variantes no pueden repetir identificador.']);
            $used[$id] = true;
            $result[] = array_merge($variant, ['id' => $id, 'stock' => (int) $variant['stock']]);
        }
        return $result;
    }

    /** Reserve once, before the external fiscal call commits. */
    public static function reserve(Product $product, array $item): array
    {
        $variant = null;
        $index = $item['variant_index'] ?? null;
        if ($index !== null) {
            $variants = self::normalize($product->variants);
            if (!isset($variants[$index])) throw new \RuntimeException("Variante inválida para {$product->name}");
            $variant = $variants[$index];
            if (!$product->unlimited_stock) {
                if ($variant['stock'] < $item['quantity']) throw new \RuntimeException("Stock insuficiente para {$product->name}");
                $variants[$index]['stock'] -= $item['quantity'];
            }
            $product->variants = $variants;
        } elseif (!$product->unlimited_stock) {
            if (!empty($product->variants)) throw new \RuntimeException("Seleccioná una variante de {$product->name}");
            if ($product->stock < $item['quantity']) throw new \RuntimeException("Stock insuficiente para {$product->name}");
            $product->stock -= $item['quantity'];
        }
        $product->save();
        return ['variant_id' => $variant['id'] ?? null, 'variant_snapshot' => $variant,
            'stock_reserved' => !$product->unlimited_stock];
    }

    /** Restore the sold identity, recreating a deleted variant instead of crediting another. */
    public static function restore(Product $product, array $item): void
    {
        if (($item['stock_reserved'] ?? !$product->unlimited_stock) === false) return;
        if (!empty($item['variant_id'])) {
            $variants = $product->variants ?? [];
            $index = array_search($item['variant_id'], array_column($variants, 'id'), true);
            if ($index === false) {
                $variant = $item['variant_snapshot'];
                $variant['stock'] = 0;
                $variants[] = $variant;
                $index = array_key_last($variants);
            }
            $variants[$index]['stock'] = ($variants[$index]['stock'] ?? 0) + $item['quantity'];
            $product->variants = $variants;
        } elseif (($item['variant_index'] ?? null) !== null) {
            // Historical rows lack a stable identity: never guess a possibly shifted index.
            throw new \RuntimeException('Esta venta antigua no identifica la variante. Revisá y conciliá su stock antes de anular.');
        } else {
            $product->stock += $item['quantity'];
        }
        $product->save();
    }
}
