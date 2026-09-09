<?php

namespace App\Interface\Configuration;

use Doctrine\ORM\QueryBuilder;

interface ListDataTableInterface
{

    public function getStart(): ?string;

    public function getLength(): ?string;

    public function getDraw(): ?string;

    public function getSearch(): ?string;

    public function getOrderColumn(): ?string;

    public function getOrderDir(): ?string;

    public function getColumns(): array;

    public function setRealColumns(array $columns): void;

    public function getRealColumns(?array $replace = null): array;

    public function preGetQuery(QueryBuilder $query, bool $isCount): QueryBuilder;

    public function searchByAllColumns(QueryBuilder $query, ?array $aditionalfield = []): QueryBuilder;

    public function complementQueryResult(QueryBuilder $query, bool $isCount): array;
}
