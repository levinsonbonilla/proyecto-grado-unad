<?php

namespace App\DataFixtures;

use App\ArgumentHandler\CategoriesArgument;
use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\ColorsArgument;
use App\ArgumentHandler\CountriesArgument;
use App\ArgumentHandler\DomainsArgument;
use App\ArgumentHandler\ImageProductArgument;
use App\ArgumentHandler\MedidasArgument;
use App\ArgumentHandler\ProductColorArgument;
use App\ArgumentHandler\ProductsArgument;
use App\ArgumentHandler\RegionsArgument;
use App\ArgumentHandler\TenantsArgument;
use App\ArgumentHandler\UsersArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Configurations\Cities\CitiesPaymentMethods;
use App\Entity\Configurations\Countries\CountriesPaymentMethods;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Configurations\Globals\Status;
use App\Entity\Configurations\Regions\RegionsPaymentMethods;
use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

class ProdFixtures extends Fixture implements FixtureGroupInterface
{
    private readonly string $projectDir;
    private array $tenants        = [];
    private array $domains        = [];
    private array $countries      = [];
    private array $regions        = [];
    private array $cities         = [];
    private array $paymentMethods = [];
    private array $products       = [];
    private array $categories     = [];
    private array $users          = [];
    private array $statuses       = [];
    private array $colors         = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly UserPasswordHasherInterface $userPasswordHasher
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    public static function getGroups(): array
    {
        return ['prod'];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        if (($_ENV['LOAD_PROD_FIXTURES'] ?? null) !== '1') {
            return;
        }

        $routeYaml = $this->projectDir . "/preloaded_data/";

        $this->createTenants(Yaml::parseFile($routeYaml . "tenants.yaml"), $manager);
        $this->createDomains(Yaml::parseFile($routeYaml . "domains.yaml"), $manager);
        $this->createCountries(Yaml::parseFile($routeYaml . "countries.yaml"), $manager);
        $this->createRegions(Yaml::parseFile($routeYaml . "regions.yaml"), $manager);
        $this->createCities(Yaml::parseFile($routeYaml . "cities.yaml"), $manager);
        $this->createLoginUser(Yaml::parseFile($routeYaml . "users.yaml"), $manager);
        $this->createPaymentMethods(Yaml::parseFile($routeYaml . "payment_methods.yaml"), $manager);
        $this->createProducts(Yaml::parseFile($routeYaml . "products.yaml"), $manager);
        $this->createCategories(Yaml::parseFile($routeYaml . "categories.yaml"), $manager);
        $this->createOrderStatuses($manager);
        $manager->flush();

        $this->createPaymentMethodsGeo($manager);
        $this->createColorsAndProductsColors($manager);
        $this->createMedidasAndVariantBlocks($manager);
        $this->createMultiMedidaVariantBlock($manager);
        $this->createVariantBlockOrderScenario($manager);
        $manager->flush();
    }

    private function createTenants(array $data, ObjectManager $em): void
    {
        foreach ($data as $value) {
            $entity = new Tenants();
            $entity->add(new TenantsArgument($value));
            if ($value['isPrincipal'] ?? false) {
                $entity->markAsPrincipal();
            }
            $em->persist($entity);
            $this->tenants[] = $entity;
        }
    }

    private function createDomains(array $data, ObjectManager $em): void
    {
        foreach ($data as $value) {
            $tenant = $this->tenants[$value['tenantIndex'] ?? 0];
            $entity = new Domains();
            $entity->add(new DomainsArgument($value, $tenant));
            $em->persist($entity);
            $this->domains[] = $entity;
        }
    }

    private function createCountries(array $data, ObjectManager $em): void
    {
        foreach ($data as $value) {
            $entity = new Countries();
            $entity->add(new CountriesArgument($value));
            $em->persist($entity);
            $this->countries[] = $entity;
        }
    }

    private function createRegions(array $data, ObjectManager $em): void
    {
        foreach ($data as $value) {
            foreach ($this->countries as $country) {
                if (in_array($country->getIsoCode(), $value)) {
                    $entity = new Regions();
                    $entity->add(new RegionsArgument($value, $country));
                    $em->persist($entity);
                    $this->regions[] = $entity;
                }
            }
        }
    }

    private function createCities(array $data, ObjectManager $em): void
    {
        foreach ($data as $value) {
            foreach ($this->regions as $region) {
                if (in_array($region->getIsoCode(), $value)) {
                    $entity = new Cities();
                    $entity->add(new CitiesArgument($value, $region));
                    $em->persist($entity);
                    $this->cities[] = $entity;
                }
            }
        }
    }

    private function createLoginUser(array $data, ObjectManager $em): void
    {
        $adminData = null;
        foreach ($data as $value) {
            if (in_array('ROLE_SUPER_ADMIN', $value['roles'] ?? [], true)) {
                $adminData = $value;
                break;
            }
        }
        $adminData ??= reset($data);

        if (!empty($_ENV['PROD_SEED_ADMIN_PASSWORD'])) {
            $adminData['password'] = $_ENV['PROD_SEED_ADMIN_PASSWORD'];
        }

        $entity = new Users($this->userPasswordHasher);
        $argument = new UsersArgument(
            $adminData,
            reset($this->domains),
            reset($this->countries) ?: null,
            reset($this->regions) ?: null,
            reset($this->cities) ?: null
        );
        $entity->add($argument);
        $entity->validateEmail();
        $em->persist($entity);
        $this->users[] = $entity;

        $entity2 = new UsersDomains();
        $entity2->add(new UsersDomainsArgument([
            "user"   => $entity,
            "domain" => reset($this->domains),
            "roles"  => $adminData['roles'],
        ]));
        $em->persist($entity2);
    }

    private function createPaymentMethods(array $data, ObjectManager $em): void
    {
        $domain = reset($this->domains);
        foreach ($data as $value) {
            $entity = new PaymentMethods();
            $entity->add(
                $domain,
                $value['name'],
                $value['provider'] ?? PaymentMethods::PROVIDER_MANUAL,
                $value['instructions'] ?? null,
            );
            $em->persist($entity);
            $this->paymentMethods[] = $entity;
        }
    }

    private function createProducts(array $data, ObjectManager $em): void
    {
        $domain = reset($this->domains);
        foreach ($data as $value) {
            $entity = new Products();
            $entity->add(new ProductsArgument($value, $domain));
            $em->persist($entity);
            $this->products[] = $entity;
        }
    }

    private function createCategories(array $data, ObjectManager $em): void
    {
        $domain = reset($this->domains);
        foreach ($data as $value) {
            $entity = new Categories();
            $entity->add(new CategoriesArgument($value, $domain));
            $em->persist($entity);
            $this->categories[] = $entity;
        }
    }

    private function createOrderStatuses(ObjectManager $em): void
    {
        $statuses = [
            ['name' => 'Pendiente',           'description' => 'Pedido recibido, pendiente de pago o confirmación.'],
            ['name' => 'Procesando',          'description' => 'Pago confirmado, preparando el pedido.'],
            ['name' => 'Enviado',             'description' => 'Pedido enviado al transportista.'],
            ['name' => 'Entregado',           'description' => 'Pedido entregado al cliente.'],
            ['name' => 'Cancelado',           'description' => 'Pedido cancelado.'],
            ['name' => 'Retracto Solicitado', 'description' => 'El cliente solicitó retracto (ley 1480/2011).'],
            ['name' => 'Retracto Aprobado',   'description' => 'Retracto aprobado y reembolso en proceso.'],
        ];

        foreach ($statuses as $data) {
            $status = new Status();
            $status->add($data['name'], $data['description']);
            $em->persist($status);
            $this->statuses[] = $status;
        }
    }

    private function createPaymentMethodsGeo(ObjectManager $em): void
    {
        if (empty($this->paymentMethods)) {
            return;
        }

        $pm = $this->paymentMethods[0];

        if (!empty($this->countries)) {
            $em->persist((new CountriesPaymentMethods())->add($this->countries[0], $pm));
        }
        if (!empty($this->regions)) {
            $em->persist((new RegionsPaymentMethods())->add($this->regions[0], $pm));
        }
        if (!empty($this->cities)) {
            $em->persist((new CitiesPaymentMethods())->add($this->cities[0], $pm));
        }
    }

    private function createColorsAndProductsColors(ObjectManager $em): void
    {
        if (empty($this->domains) || empty($this->products)) {
            return;
        }

        $domain  = $this->domains[0];
        $product = $this->products[0];

        $negro = (new Colors())->add(new ColorsArgument(['name' => 'Negro', 'hexCode' => '#1a1a1a'], $domain));
        $rojo  = (new Colors())->add(new ColorsArgument(['name' => 'Rojo', 'hexCode' => '#e53935'], $domain));
        $azul  = (new Colors())->add(new ColorsArgument(['name' => 'Azul', 'hexCode' => '#1e88e5'], $domain));
        $em->persist($negro);
        $em->persist($rojo);
        $em->persist($azul);

        $this->colors['negro'] = $negro;
        $this->colors['rojo']  = $rojo;
        $this->colors['azul']  = $azul;

        $negroBlock = (new ProductsColors())->add(new ProductColorArgument(['color' => $negro, 'stock' => 5, 'orderColumn' => 2], $product));
        $rojoBlock  = (new ProductsColors())->add(new ProductColorArgument(['color' => $rojo, 'stock' => 0, 'orderColumn' => 1], $product));
        $em->persist($negroBlock);
        $em->persist($rojoBlock);
        $em->persist((new ProductsColors())->add(new ProductColorArgument(['color' => $azul, 'stock' => null], $product)));

        $negroImage = (new ImagesProducts())->add(new ImageProductArgument([
            'description' => null,
            'orderColumn' => 1,
            'image' => 'https://example.com/negro.jpg',
            'productColor' => $negroBlock,
        ], $product));
        $em->persist($negroImage);
    }

    private function createMedidasAndVariantBlocks(ObjectManager $em): void
    {
        if (empty($this->domains) || empty($this->products) || empty($this->colors)) {
            return;
        }

        $domain  = $this->domains[0];
        $product = $this->products[0];

        $m = (new Medidas())->add(new MedidasArgument(['name' => 'M'], $domain));
        $l = (new Medidas())->add(new MedidasArgument(['name' => 'L'], $domain));
        $em->persist($m);
        $em->persist($l);

        $negroM = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $this->colors['negro'], 'medida' => $m, 'stock' => 3,
        ], $product));
        $negroL = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $this->colors['negro'], 'medida' => $l, 'stock' => 2,
        ], $product));
        $em->persist($negroM);
        $em->persist($negroL);

        $soloMedidaM = (new ProductsColors())->add(new ProductColorArgument([
            'medida' => $m, 'stock' => 4,
        ], $product));
        $em->persist($soloMedidaM);
    }

    private function createMultiMedidaVariantBlock(ObjectManager $em): void
    {
        if (empty($this->domains) || count($this->products) < 2) {
            return;
        }

        $domain  = $this->domains[0];
        $product = $this->products[1];

        $rosa = (new Colors())->add(new ColorsArgument(['name' => 'Rosa', 'hexCode' => '#f48fb1'], $domain));
        $em->persist($rosa);

        $s = (new Medidas())->add(new MedidasArgument(['name' => 'S'], $domain));
        $m = (new Medidas())->add(new MedidasArgument(['name' => 'M'], $domain));
        $l = (new Medidas())->add(new MedidasArgument(['name' => 'L'], $domain));
        $em->persist($s);
        $em->persist($m);
        $em->persist($l);

        $groupId = Uuid::v4()->toRfc4122();

        $rosaS = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $rosa, 'medida' => $s, 'stock' => 4, 'variantGroupId' => $groupId,
        ], $product));
        $rosaM = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $rosa, 'medida' => $m, 'stock' => 6, 'variantGroupId' => $groupId,
        ], $product));
        $rosaL = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $rosa, 'medida' => $l, 'stock' => 2, 'variantGroupId' => $groupId,
        ], $product));
        $em->persist($rosaS);
        $em->persist($rosaM);
        $em->persist($rosaL);

        $photoUrls = ['https://example.com/rosa-1.jpg', 'https://example.com/rosa-2.jpg'];
        foreach ([$rosaS, $rosaM, $rosaL] as $block) {
            foreach ($photoUrls as $i => $url) {
                $image = (new ImagesProducts())->add(new ImageProductArgument([
                    'description' => null,
                    'orderColumn' => $i + 1,
                    'image' => $url,
                    'productColor' => $block,
                ], $product));
                $em->persist($image);
            }
        }
    }

    private function createVariantBlockOrderScenario(ObjectManager $em): void
    {
        if (empty($this->colors) || count($this->products) < 3) {
            return;
        }

        $product = $this->products[2];

        $rojoBlock = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $this->colors['rojo'], 'stock' => 3, 'orderColumn' => 1,
        ], $product));
        $negroBlock = (new ProductsColors())->add(new ProductColorArgument([
            'color' => $this->colors['negro'], 'stock' => 3, 'orderColumn' => 2,
        ], $product));
        $em->persist($rojoBlock);
        $em->persist($negroBlock);

        $em->persist((new ImagesProducts())->add(new ImageProductArgument([
            'description' => null,
            'orderColumn' => 99,
            'image' => 'https://example.com/orden-rojo.jpg',
            'productColor' => $rojoBlock,
        ], $product)));
        $em->persist((new ImagesProducts())->add(new ImageProductArgument([
            'description' => null,
            'orderColumn' => 1,
            'image' => 'https://example.com/orden-negro.jpg',
            'productColor' => $negroBlock,
        ], $product)));
    }
}
