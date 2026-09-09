<?php

namespace App\Service\Products;

final readonly class GalleryImageGrouperService implements GalleryImageGrouperInterface
{
    public function group(array $images): array
    {
        $grouped = [];
        $order = [];

        foreach ($images as $img) {
            $colorId = $img['colorId'] ?? null;
            $groupKey = $img['variantGroupId'] ?? $colorId ?? '';
            $key = $groupKey . '|' . $img['image'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'image' => $img['image'],
                    'orderColumn' => $img['orderColumn'] ?? null,
                    'colorIds' => [],
                ];
                $order[] = $key;
            }

            if ($colorId !== null && !in_array($colorId, $grouped[$key]['colorIds'], true)) {
                $grouped[$key]['colorIds'][] = $colorId;
            }
        }

        $result = [];
        foreach ($order as $key) {
            $row = $grouped[$key];
            $row['colorIds'] = implode(',', $row['colorIds']);
            $result[] = $row;
        }

        return $result;
    }
}
