<?php

namespace App\Tests\Integration\Regression;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Interface\UseCase\Security\RegisterInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

class H05DemoDataHygieneTest extends IntegrationTestCase
{
    private const TEMPLATE_MARKERS = ['example.org', 'example.com', 'Replace with your domain', 'Reemplace por su dominio', 'No existe'];

    private function projectDir(): string
    {
        return static::getContainer()->getParameter('kernel.project_dir');
    }

    public function testSeedDomainsDoNotContainTemplateValues(): void
    {
        $domains = Yaml::parseFile($this->projectDir() . '/preloaded_data/domains.yaml');

        foreach ($domains as $index => $domain) {
            foreach (['domain', 'notificationEmail', 'supportEmail'] as $field) {
                foreach (self::TEMPLATE_MARKERS as $marker) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $marker,
                        (string) ($domain[$field] ?? ''),
                        "preloaded_data/domains.yaml[{$index}].{$field} conserva un valor de plantilla"
                    );
                }
            }
        }
    }

    public function testSelfServiceSignupDoesNotStorePlaceholderTextAsTheDomain(): void
    {
        foreach (['es', 'en'] as $locale) {
            $requestStack = static::getContainer()->get('request_stack');
            $request = Request::create('http://localhost:8060/' . $locale . '/register', 'POST');
            $request->setLocale($locale);
            $requestStack->push($request);
            static::getContainer()->get('translator')->setLocale($locale);

            $businessName = 'Negocio H05 ' . $locale;
            $result = static::getContainer()->get(RegisterInterface::class)->handler([
                'businessName' => $businessName,
                'phone' => '3005551234',
                'email' => 'h05-' . $locale . '@proyecto-grado.test',
                'name' => 'Dueño',
                'lastName' => 'Prueba',
                'password' => 'SuperClave123',
            ]);
            $this->assertFalse($result->isError(), (string) $result->getMessage());

            $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => $businessName]);
            $domain = $this->em->getRepository(Domains::class)->findOneBy(['tenant' => $tenant]);
            $this->assertNotNull($domain);

            foreach (self::TEMPLATE_MARKERS as $marker) {
                $this->assertStringNotContainsStringIgnoringCase($marker, $domain->getDomain(), "Registro en {$locale} guardó texto de plantilla como dominio");
            }
            $this->assertTrue($domain->isPendingConfiguration(), 'El dominio recién creado debe quedar marcado como pendiente de configurar');
        }
    }

    public function testSpanishAndEnglishCatalogsDefineTheSameKeys(): void
    {
        $missing = [];
        foreach (glob($this->projectDir() . '/translations/*.es.yaml') as $esFile) {
            $enFile = str_replace('.es.yaml', '.en.yaml', $esFile);
            $es = array_keys(Yaml::parseFile($esFile) ?? []);
            $en = is_file($enFile) ? array_keys(Yaml::parseFile($enFile) ?? []) : [];
            foreach (array_diff($es, $en) as $key) {
                $missing[] = basename($enFile) . ':' . $key;
            }
            foreach (array_diff($en, $es) as $key) {
                $missing[] = basename($esFile) . ':' . $key;
            }
        }

        $this->assertSame([], $missing, 'Claves sin traducir (provocan textos mezclados ES/EN)');
    }

    public function testMigrationConvertsStoredTemplateDomainsToPendingConfiguration(): void
    {
        $tenant = $this->em->getRepository(Tenants::class)->findOneBy([]);
        $connection = $this->em->getConnection();
        foreach (['Replace with your domain', 'Reemplace por su dominio'] as $templateValue) {
            $domain = new Domains();
            $domain->add(new \App\ArgumentHandler\DomainsArgument([
                'domain' => $templateValue,
                'logo' => '',
                'notificationEmail' => 'dueno@proyecto-grado.test',
                'supportEmail' => 'dueno@proyecto-grado.test',
            ], $tenant));
            $this->em->persist($domain);
        }
        $this->em->flush();

        require_once static::getContainer()->getParameter('kernel.project_dir') . '/migrations/Version20261122090000.php';
        $migration = new \DoctrineMigrations\Version20261122090000($connection, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        $this->em->clear();

        $remaining = (int) $connection->fetchOne("SELECT COUNT(*) FROM domains WHERE domain IN ('Replace with your domain', 'Reemplace por su dominio')");
        $this->assertSame(0, $remaining);
        $pending = array_filter(
            $this->em->getRepository(Domains::class)->findAll(),
            fn (Domains $domain) => $domain->isPendingConfiguration()
        );
        $this->assertCount(2, $pending);
        $this->assertCount(2, array_unique(array_map(fn (Domains $domain) => $domain->getDomain(), $pending)));
    }
}
