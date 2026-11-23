<?php

namespace App\Tests\Integration\Regression;

use PHPUnit\Framework\TestCase;

class H04H05ProductionDatabaseTest extends TestCase
{
    private const TEMPLATE_MARKERS = ['example.org', 'example.com', 'Replace with your domain', 'Reemplace por su dominio', 'no-existente'];

    private static \PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $path = dirname(__DIR__, 3) . '/var/data.db';
        if (!is_file($path)) {
            self::markTestSkipped('No existe var/data.db');
        }
        self::$pdo = new \PDO('sqlite:' . $path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private function column(string $sql): array
    {
        return self::$pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function testCountriesAreNotDuplicated(): void
    {
        $this->assertSame([], $this->column('SELECT iso_code FROM countries GROUP BY iso_code HAVING COUNT(*) > 1'));
        $this->assertSame([], $this->column(
            "SELECT LOWER(json_extract(name, '$.en')) AS label FROM countries WHERE json_valid(name) AND json_extract(name, '$.en') IS NOT NULL GROUP BY label HAVING COUNT(*) > 1"
        ));
    }

    public function testRegionsAndCitiesAreNotDuplicated(): void
    {
        $this->assertSame([], $this->column('SELECT iso_code FROM regions GROUP BY country_id, iso_code HAVING COUNT(*) > 1'));
        $this->assertSame([], $this->column('SELECT name FROM cities GROUP BY name HAVING COUNT(*) > 1'));
    }

    public function testDatabaseEnforcesUniqueGeoCodes(): void
    {
        $indexes = $this->column("SELECT name FROM sqlite_master WHERE type = 'index'");
        foreach (['uniq_countries_iso_code', 'uniq_regions_country_iso_code', 'uniq_cities_geoname_id'] as $index) {
            $this->assertContains($index, $indexes);
        }
    }

    public function testGeoReferencesAreNotOrphaned(): void
    {
        $this->assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM regions r LEFT JOIN countries c ON c.id = r.country_id WHERE c.id IS NULL')->fetchColumn());
        $this->assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM cities ci LEFT JOIN regions r ON r.id = ci.region_id WHERE r.id IS NULL')->fetchColumn());
        $this->assertSame([], self::$pdo->query('PRAGMA foreign_key_check')->fetchAll());
    }

    public function testActiveDomainsHaveNoTemplateData(): void
    {
        $rows = self::$pdo->query('SELECT name, domain, notification_email, support_email FROM domains WHERE active = 1')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach (['domain', 'notification_email', 'support_email'] as $field) {
                foreach (self::TEMPLATE_MARKERS as $marker) {
                    $this->assertStringNotContainsStringIgnoringCase($marker, (string) $row[$field], sprintf('%s.%s conserva un valor de plantilla', $row['name'] ?? $row['domain'], $field));
                }
            }
            $this->assertNotSame('No existe', $row['name'], 'El dominio de prueba "No existe" no debe estar activo');
        }
    }
}
