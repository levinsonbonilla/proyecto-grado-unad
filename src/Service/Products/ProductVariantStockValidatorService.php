<?php

namespace App\Service\Products;

use App\Exception\GenericException;
use App\Util\ArrayUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProductVariantStockValidatorService implements ProductVariantStockValidatorInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function validate(?int $generalStock, array $variants): void
    {

        if ($generalStock === null) {
            return;
        }

        $assigned = 0;
        foreach ($variants as $block) {

            if (empty($block['images'] ?? [])) {
                continue;
            }

            $medidaEntries = $block['medidas'] ?? [];
            if (!empty($medidaEntries)) {
                foreach ($medidaEntries as $entry) {
                    $stockRaw = ArrayUtil::validateExistKey($entry, 'stock');
                    if ($stockRaw === null || trim($stockRaw) === '') {
                        continue;
                    }
                    $assigned += (int) $stockRaw;
                }
                continue;
            }

            $stockRaw = ArrayUtil::validateExistKey($block, 'stock');
            if ($stockRaw === null || trim($stockRaw) === '') {
                continue;
            }

            $assigned += (int) $stockRaw;
        }

        if ($assigned > $generalStock) {
            throw new GenericException(
                $this->translator->trans('product_color_stock_exceeds_general', [
                    '%assigned%' => $assigned,
                    '%general%' => $generalStock,
                ], 'modules'),
                400
            );
        }
    }
}
