<?php

namespace App\Service\Geo;

use Doctrine\DBAL\Connection;

final class GeoReferenceRepointer
{
    private array $referencesByTable = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function mergeExactDuplicates(): array
    {
        $merged = ['countries' => 0, 'regions' => 0, 'cities' => 0];

        $countryGroups = $this->connection->fetchFirstColumn(
            'SELECT iso_code FROM countries GROUP BY iso_code HAVING COUNT(*) > 1'
        );
        foreach ($countryGroups as $isoCode) {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT id FROM countries WHERE iso_code = ? ORDER BY created_at ASC, id ASC',
                [$isoCode]
            );
            $keepId = array_shift($ids);
            foreach ($ids as $duplicateId) {
                $this->repointCountry($duplicateId, $keepId);
            }
            $merged['countries'] += count($ids);
        }

        $regionGroups = $this->connection->fetchAllAssociative(
            'SELECT country_id, iso_code FROM regions GROUP BY country_id, iso_code HAVING COUNT(*) > 1'
        );
        foreach ($regionGroups as $group) {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT id FROM regions WHERE country_id = ? AND iso_code = ? ORDER BY created_at ASC, id ASC',
                [$group['country_id'], $group['iso_code']]
            );
            $merged['regions'] += $this->collapse('regions', $ids);
        }

        $cityGroups = $this->connection->fetchFirstColumn(
            'SELECT name FROM cities GROUP BY name HAVING COUNT(*) > 1'
        );
        foreach ($cityGroups as $geoNameId) {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT id FROM cities WHERE name = ? ORDER BY created_at ASC, id ASC',
                [$geoNameId]
            );
            $merged['cities'] += $this->collapse('cities', $ids);
        }

        return $merged;
    }

    public function repointCountry(string $duplicateId, string $keepId): void
    {
        $collisions = $this->connection->fetchAllAssociative(
            'SELECT d.id AS duplicate_id, k.id AS keep_id FROM regions d INNER JOIN regions k ON k.iso_code = d.iso_code AND k.country_id = ? WHERE d.country_id = ?',
            [$keepId, $duplicateId]
        );
        foreach ($collisions as $collision) {
            $this->repoint('regions', $collision['duplicate_id'], $collision['keep_id']);
        }

        $this->repoint('countries', $duplicateId, $keepId);
    }

    public function repoint(string $table, string $duplicateId, string $keepId): void
    {
        foreach ($this->referencesTo($table) as [$referencingTable, $column, $siblingColumns]) {
            foreach ($siblingColumns as $sibling) {
                $this->connection->executeStatement(
                    "DELETE FROM {$referencingTable} WHERE {$column} = ? AND {$sibling} IN (SELECT s FROM (SELECT {$sibling} AS s FROM {$referencingTable} WHERE {$column} = ?) AS keep_rows)",
                    [$duplicateId, $keepId]
                );
            }
            $this->connection->executeStatement(
                "UPDATE {$referencingTable} SET {$column} = ? WHERE {$column} = ?",
                [$keepId, $duplicateId]
            );
        }

        $this->connection->executeStatement("DELETE FROM {$table} WHERE id = ?", [$duplicateId]);
    }

    private function collapse(string $table, array $ids): int
    {
        $keepId = array_shift($ids);
        foreach ($ids as $duplicateId) {
            $this->repoint($table, $duplicateId, $keepId);
        }

        return count($ids);
    }

    private function referencesTo(string $table): array
    {
        if (isset($this->referencesByTable[$table])) {
            return $this->referencesByTable[$table];
        }

        $schemaManager = $this->connection->createSchemaManager();
        $references = [];
        foreach ($schemaManager->listTableNames() as $candidate) {
            $foreignKeys = $schemaManager->listTableForeignKeys($candidate);
            foreach ($foreignKeys as $foreignKey) {
                if (strtolower($foreignKey->getForeignTableName()) !== $table) {
                    continue;
                }
                $column = $foreignKey->getLocalColumns()[0];
                $siblings = [];
                if ($this->isLinkTable($candidate)) {
                    foreach ($foreignKeys as $other) {
                        if ($other !== $foreignKey) {
                            $siblings[] = $other->getLocalColumns()[0];
                        }
                    }
                }
                $references[] = [$candidate, $column, $siblings];
            }
        }

        return $this->referencesByTable[$table] = $references;
    }

    private function isLinkTable(string $table): bool
    {
        $columns = array_map('strtolower', array_keys($this->connection->createSchemaManager()->listTableColumns($table)));
        $payload = array_filter(
            $columns,
            fn (string $column) => !in_array($column, ['id', 'active', 'created_at', 'updated_at'], true) && !str_ends_with($column, '_id')
        );

        return $payload === [];
    }
}
