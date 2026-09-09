<?php

namespace App\Handler\Configuration;

use App\Interface\Configuration\ListDataTableInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ListDataTableHandler implements ListDataTableInterface
{
    private ?string $start = null;
    private ?string $length = null;
    private ?string $draw = null;
    private ?array $search = null;
    private ?array $order = null;
    private array $columns = [];
    private array $realColumns = [];

    private array $columnsExclude = ["roles", "id", "active"];

    public function __construct(
        private readonly RequestStack $request
    ) {
        $request = $this->request->getCurrentRequest();
        if (!empty($request)) {
            $this->start = $request->get('start', 0);
            $this->length = $request->get('length', 10);
            $this->draw = $request->get('draw', 1);
            $rawSearch = $request->get('search', null);
            $this->search = is_array($rawSearch)
                ? $rawSearch
                : (is_string($rawSearch) && $rawSearch !== '' ? ['value' => $rawSearch, 'regex' => 'false'] : null);
            $this->order = $request->get('order', null);
            $this->columns = $request->get('columns', []);
        }
    }

    private function getColumnOrder(QueryBuilder $query, bool $isCount): QueryBuilder
    {
        if (!empty($this->getOrderColumn()) && !$isCount) {
            $columnNameArray = preg_split('/\s+as\s+/i', $this->realColumns[$this->getOrderColumn()]);
            $columnName = trim(reset($columnNameArray));
            $query->orderBy($columnName, $this->getOrderDir());
        }

        return $query;
    }

    private function returnConditionRecords(QueryBuilder $query, bool $isCount): QueryBuilder
    {
        if (!$isCount) {
            $query
                ->setFirstResult($this->getStart())
                ->setMaxResults($this->getLength());
        }
        return $query;
    }

    private function convertToRealColumn(string $fielSearch): ?string
    {
        $result = null;
        foreach ($this->realColumns as $column) {
            $columnTemp = explode(".", $column);
            $columnTemp = explode(" ", end($columnTemp));
            if (end($columnTemp) == $fielSearch) {
                $result = $column;
                break;
            }
        }

        return $result;
    }

    private function getAlias(string $column): string
    {
        $columnExplode = explode(".", $column);
        return end($columnExplode);
    }

    public function getStart(): ?string
    {
        return $this->start;
    }

    public function getLength(): ?string
    {
        return $this->length;
    }

    public function getDraw(): ?string
    {
        return $this->draw;
    }

    public function getSearch(): ?string
    {
        return $this->search["value"] ?? null;
    }

    public function getOrderColumn(): ?string
    {
        if (!empty($this->order)) {
            return reset($this->order)["column"] ?? null;
        }
        return null;
    }

    public function getOrderDir(): ?string
    {
        return reset($this->order)["dir"] ?? null;
    }

    public function getColumns(): array
    {
        $return = [];
        foreach ($this->columns as $key => $column) {
            if (!empty($column["data"]) && !in_array($column["data"], $this->columnsExclude)) {
                $realColumn = $this->convertToRealColumn($column["data"]);
                if (!empty($realColumn)) {
                    $return[] = $realColumn;
                }
            }
        }

        return $return;
    }

    public function searchByAllColumns(
        QueryBuilder $query,
        ?array $aditionalfield = []
    ): QueryBuilder {

        if (!empty($this->getSearch())) {
            $search = "";
            $key = 0;

            foreach (array_merge($this->getColumns(), $aditionalfield) as $column) {
                if (empty($column)) {
                    continue;
                }
                $temp = explode(" ", $column);
                $column = reset($temp);

                $alias = $this->getAlias($column);
                foreach (explode(" ", $this->getSearch()) as $valueSearch) {
                    $search .= (($key > 0) ? " OR " : "") . "$column LIKE :$alias";
                    $query->setParameter($alias, '%' . $valueSearch . '%');
                    $key++;
                }
            }
            $query->andWhere($search);
        }

        return $query;
    }

    public function setRealColumns(array $columns): void
    {
        $this->realColumns = $columns;
    }

    public function getRealColumns(?array $replace = null): array
    {
        if (empty($replace)) {
            return $this->realColumns;
        }

        $return = $this->realColumns;
        foreach ($replace as $key => $value) {
            $return[$key] = $value;
        }

        return $return;
    }

    public function preGetQuery(QueryBuilder $query, bool $isCount): QueryBuilder
    {

        $query = $this->getColumnOrder($query, $isCount);
        $query = $this->returnConditionRecords($query, $isCount);
        return $query;
    }

    public function complementQueryResult(QueryBuilder $query, bool $isCount): array
    {
        $query = $this->searchByAllColumns(
            query: $query
        );

        $query = $this->preGetQuery($query, $isCount);

        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }
}
