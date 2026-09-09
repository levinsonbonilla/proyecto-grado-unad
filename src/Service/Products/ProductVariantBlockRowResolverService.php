<?php

namespace App\Service\Products;

use App\ArgumentHandler\ProductColorArgument;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Util\ArrayUtil;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProductVariantBlockRowResolverService implements ProductVariantBlockRowResolverInterface
{
    public function __construct(
        private ColorResolverInterface $colorResolver,
        private MedidaResolverInterface $medidaResolver,
        private CustomeEntityManagerInterface $customeEntityManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function resolve(array $block, ?string $coverImageUrl, Products $product, Domains $domain, array $existingBlocksById = []): array
    {
        $colorName = trim((string) ($block['colorName'] ?? ''));
        $color = $colorName !== '' ? $this->colorResolver->resolve($domain, $colorName, $block['colorHex'] ?? null) : null;

        $orderColumn = $block['order'] ?? null;

        $medidaEntries = $block['medidas'] ?? [];

        if (empty($medidaEntries)) {
            $row = $this->resolveOneRow($block, $color, null, $coverImageUrl, null, $product, $existingBlocksById, $block['rowId'] ?? null, $orderColumn);
            return $row !== null ? [$row] : [];
        }

        $groupId = $this->resolveGroupId($block, $medidaEntries, $existingBlocksById);
        $rows = [];
        $seenNames = [];
        foreach ($medidaEntries as $entry) {
            $medidaName = trim((string) ($entry['medidaName'] ?? ''));
            if ($medidaName === '') {

                continue;
            }

            $normalizedName = mb_strtolower($medidaName);
            if (isset($seenNames[$normalizedName])) {
                throw new GenericException(
                    $this->translator->trans('duplicate_medida_in_block', ['%medida%' => $medidaName], 'modules'),
                    400
                );
            }
            $seenNames[$normalizedName] = true;

            $medida = $this->medidaResolver->resolve($domain, $medidaName);
            $row = $this->resolveOneRow($entry, $color, $medida, $coverImageUrl, $groupId, $product, $existingBlocksById, $entry['rowId'] ?? null, $orderColumn);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function resolveGroupId(array $block, array $medidaEntries, array $existingBlocksById): string
    {
        $candidateIds = array_filter(array_merge(
            [$block['rowId'] ?? null],
            array_map(fn (array $e) => $e['rowId'] ?? null, $medidaEntries)
        ));

        foreach ($candidateIds as $rowId) {
            if (isset($existingBlocksById[(string) $rowId])) {
                $existingGroupId = $existingBlocksById[(string) $rowId]->getVariantGroupId();
                if ($existingGroupId !== null) {
                    return (string) $existingGroupId;
                }
            }
        }

        return Uuid::v4()->toRfc4122();
    }

    private function resolveOneRow(
        array $stockSource,
        ?Colors $color,
        ?Medidas $medida,
        ?string $coverImageUrl,
        ?string $groupId,
        Products $product,
        array $existingBlocksById,
        mixed $rowId,
        mixed $orderColumn = null,
    ): ?ProductsColors {
        $stockRaw = ArrayUtil::validateExistKey($stockSource, 'stock');
        $hasStockValue = $stockRaw !== null && trim($stockRaw) !== '';

        if ($color === null && $medida === null && !$hasStockValue) {
            return null;
        }

        $data = [
            'color' => $color,
            'medida' => $medida,
            'stock' => $stockSource['stock'] ?? null,
            'image' => $coverImageUrl,
            'variantGroupId' => $groupId,
            'orderColumn' => $orderColumn,
        ];
        $argument = new ProductColorArgument($data, $product);

        if ($rowId && isset($existingBlocksById[(string) $rowId])) {
            $row = $existingBlocksById[(string) $rowId];
            $row->edit($argument);
            $row->activate();
            $this->customeEntityManager->add($row);
            return $row;
        }

        $row = (new ProductsColors())->add($argument);
        $this->customeEntityManager->add($row);
        return $row;
    }
}
