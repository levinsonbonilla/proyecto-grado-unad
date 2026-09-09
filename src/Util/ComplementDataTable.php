<?php

namespace App\Util;

use App\Interface\Configuration\ListDataTableInterface;

final class ComplementDataTable
{

    public function filtersDataTable($query, $data, $columns, $isCount = false, $isBetween = false)
    {
        extract($data);
        $filters = ((empty($filterValue["value"])) ? [] : json_decode($filterValue["value"], true));

        $vectorColumn = [];
        $vectorValue = null;

        foreach ($filters as $key => $filter) {
            $condition = "and";
            $value = "";
            $nameColumn = explode(".", $columns[$key]);
            $isPending = true;

            if (is_array($filter)) {
                $value = $filter["value"];
                $condition = $filter["condition"];
            } else {
                $value = $filter;
            }

            if (end($nameColumn)  == "status" && $value != "2" && $isPending) {
                $query
                    ->andWhere($columns[$key] . " = :filterValue$key")
                    ->setParameter("filterValue$key", Utils::convertBollean($value));
                $isPending = false;
            }

            $isPending = ((end($nameColumn)  == "status") ? false : true);

            if (!empty($value) && end($nameColumn) == "roles" && $isPending && $value != "0") {
                $condition2 = ((trim(Utils::removeAccents(mb_strtoupper($value))) == "ROLE_PARTNER_ADMIN") ? "ROLE_IMEX_ADMIN" : trim(Utils::removeAccents(mb_strtoupper($value))));
                $value = "roles,+," . trim(Utils::removeAccents(mb_strtoupper($value))) . ",+,=,+," . $condition2 . ",+,or";
                $query
                    ->andWhere("JSON_ARRAY_ELEMENTS_TEXT('$value') = true");
                $isPending = false;
            }

            if (!empty($value) && end($nameColumn) == "startDate" && $isPending) {
                $date = new  \DateTime($value);
                if ($isBetween) {
                    $query
                        ->andWhere($columns[$key] . " BETWEEN :startDate AND :endDate ")
                        ->setParameter("startDate", $date->format("Y-m-d") . " 01:00:00")
                        ->setParameter("endDate", $date->format("Y-m-d") . " 23:59:59");
                } else {
                    $query
                        ->andWhere($columns[$key] . " = :startDate")
                        ->setParameter("startDate", $date->format("Y-m-d"));
                }
                $isPending = false;
            }

            if (!empty($value) && $condition == "and" && $isPending) {
                $query
                    ->andWhere("REPLACE_ACCENTS(UPPER(" . $columns[$key] . ")) LIKE :filterValue$key")
                    ->setParameter("filterValue$key", '%' . trim(Utils::removeAccents(mb_strtoupper($value))) . '%');
                $isPending = false;
            }

            if (!empty($value) && $condition == "or" && $isPending) {
                $query
                    ->orWhere("REPLACE_ACCENTS(UPPER(" . $columns[$key] . ")) LIKE :filterValue$key")
                    ->setParameter("filterValue$key", '%' . trim(Utils::removeAccents(mb_strtoupper($value))) . '%');
                $isPending = false;
            }

            if (!empty($value) && $condition == "vector" && $isPending) {
                $vectorColumn = (empty($vectorColumn) ? $columns[$key] : $vectorColumn . " " . $columns[$key]);
                $value = trim(Utils::removeAccents(Utils::replaceSpaces(mb_strtoupper($value))));
                $vectorValue = ((empty($vectorValue)) ? $value : $vectorValue . "%" . $value);
                $isPending = false;
            }
        }

        if (!empty($vectorColumn) && !empty($vectorValue)) {
            $dataComplete = $vectorColumn . ",+," . $vectorValue;
            $dataComplete = "VECTOR_QUERY('$dataComplete') = true";
            $query->andWhere($dataComplete);
        }

        if (!$isCount && is_array($order)) {

            if ((intval(reset($order)["column"]) + 1) <= count($columns)) {
                $columnOrder = explode('.', $columns[reset($order)["column"]]);
                if (end($columnOrder) != "roles") {
                    $query
                        ->orderBy($columns[reset($order)["column"]], reset($order)["dir"]);
                }

                if (end($columnOrder) == "roles") {
                    $query
                        ->orderBy("JSON_ARRAY_ELEMENTS_TEXT(" . $columns[reset($order)["column"]] . ")", reset($order)["dir"]);
                }
            }
            $query
                ->setFirstResult($start);

            if (intval($length) > 0) {
                $query
                    ->setMaxResults($length);
            }
        }

        return $query;
    }

    public static function returnListDataTable(
        ListDataTableInterface $dataTable,
        array $totalElements = [0],
        array $elements = [0]
    ): array {
        return [
            "draw" => $dataTable->getDraw(),
            "recordsTotal" => reset($totalElements),
            "recordsFiltered" => reset($totalElements),
            "data" => $elements
        ];
    }
}
