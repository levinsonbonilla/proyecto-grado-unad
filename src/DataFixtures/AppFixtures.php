<?php

namespace App\DataFixtures;

use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\CountriesArgument;
use App\ArgumentHandler\DomainsArgument;
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
use App\Entity\Configurations\Regions\RegionsPaymentMethods;
use App\ArgumentHandler\ColorsArgument;
use App\ArgumentHandler\ImageProductArgument;
use App\ArgumentHandler\ProductColorArgument;
use App\ArgumentHandler\CategoriesArgument;
use App\ArgumentHandler\MedidasArgument;
use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Others\FavoriteProducts;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\OrdersProducts;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Entity\Tenants\Statistics\Statistics;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\HelpMessageImages;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

class AppFixtures extends Fixture
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
    private array $medidas        = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly UserPasswordHasherInterface $userPasswordHasher
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $routeYaml = $this->projectDir . "/preloaded_data/";

        $this->createTenants(Yaml::parseFile($routeYaml . "tenants.yaml"), $manager);
        $this->createDomains(Yaml::parseFile($routeYaml . "domains.yaml"), $manager);
        $this->createCountries(Yaml::parseFile($routeYaml . "countries.yaml"), $manager);
        $this->createRegions(Yaml::parseFile($routeYaml . "regions.yaml"), $manager);
        $this->createCities(Yaml::parseFile($routeYaml . "cities.yaml"), $manager);
        $this->createUsers(Yaml::parseFile($routeYaml . "users.yaml"), $manager);
        $this->createPaymentMethods(Yaml::parseFile($routeYaml . "payment_methods.yaml"), $manager);
        $this->createProducts(Yaml::parseFile($routeYaml . "products.yaml"), $manager);
        $this->createCategories(Yaml::parseFile($routeYaml . "categories.yaml"), $manager);
        $this->createOrderStatuses($manager);
        $manager->flush();

        $this->createPaymentMethodsGeo($manager);
        $this->createFavorites($manager);
        $this->createMessages($manager);
        $this->createStatistics($manager);
        $this->createColorsAndProductsColors($manager);
        $this->createMedidasAndVariantBlocks($manager);
        $this->createMultiMedidaVariantBlock($manager);
        $this->createVariantBlockOrderScenario($manager);
        $this->createOrders($manager);
        $manager->flush();
    }

    private function createTenants(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            $entity = new Tenants();
            $argument = new TenantsArgument($value);
            $entity->add($argument);

            if ($value['isPrincipal'] ?? false) {
                $entity->markAsPrincipal();
            }
            $em->persist($entity);
            $this->tenants[] = $entity;
        }
    }

    private function createDomains(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            $tenant = $this->tenants[$value['tenantIndex'] ?? 0];
            $entity = new Domains();
            $argument = new DomainsArgument($value, $tenant);
            $entity->add($argument);
            $em->persist($entity);
            $this->domains[] = $entity;
        }
    }

    private function createCountries(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            $entity = new Countries();
            $argument = new CountriesArgument($value);
            $entity->add($argument);
            $em->persist($entity);
            $this->countries[] = $entity;
        }
    }

    private function createRegions(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            foreach ($this->countries as $country) {
                if (in_array($country->getIsoCode(), $value)) {
                    $entity = new Regions();
                    $argument = new RegionsArgument($value, $country);
                    $entity->add($argument);
                    $em->persist($entity);
                    $this->regions[] = $entity;
                }
            }
        }
    }

    private function createCities(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            foreach ($this->regions as $region) {
                if (in_array($region->getIsoCode(), $value)) {
                    $entity = new Cities();
                    $argument = new CitiesArgument($value, $region);
                    $entity->add($argument);
                    $em->persist($entity);
                    $this->cities[] = $entity;
                }
            }
        }
    }

    private function createUsers(array $data, ObjectManager $em): void
    {
        foreach ($data as $key => $value) {
            $entity = new Users($this->userPasswordHasher);
            $argument = new UsersArgument(
                $value,
                reset($this->domains),
                reset($this->countries) ?: null,
                reset($this->regions) ?: null,
                reset($this->cities) ?: null
            );
            $entity->add($argument);
            $entity->validateEmail();
            $em->persist($entity);
            $this->users[] = $entity;

            $domainData = [
                "user"   => $entity,
                "domain" => reset($this->domains),
                "roles"  => $value['roles'],
            ];
            $entity2 = new UsersDomains();
            $entity2->add(new UsersDomainsArgument($domainData));
            $em->persist($entity2);
        }
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
            $argument = new ProductsArgument($value, $domain);
            $entity   = new Products();
            $entity->add($argument);
            $em->persist($entity);
            $this->products[] = $entity;
        }
    }

    private function createCategories(array $data, ObjectManager $em): void
    {
        $domain = reset($this->domains);
        foreach ($data as $value) {
            $argument = new CategoriesArgument($value, $domain);
            $entity   = new Categories();
            $entity->add($argument);
            $em->persist($entity);
            $this->categories[] = $entity;
        }
    }

    private function createFavorites(ObjectManager $em): void
    {
        $customer = $this->users[2] ?? null;
        $product  = $this->products[0] ?? null;

        if ($customer === null || $product === null) {
            return;
        }

        $em->persist((new FavoriteProducts())->add($customer, $product));
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

    private function createOrders(ObjectManager $em): void
    {
        $customer = $this->users[2] ?? null;
        $product  = $this->products[0] ?? null;
        $country  = $this->countries[0] ?? null;
        $region   = $this->regions[0] ?? null;
        $city     = $this->cities[0] ?? null;

        if ($customer === null || $product === null || $country === null || $region === null || $city === null) {
            return;
        }

        $quantity   = '2';
        $unitPrice  = $product->getPublicPrice();
        $totalPrice = (string) ((int) $unitPrice * (int) $quantity);

        $scenarios = [
            [
                'orderStatus'      => 'Pendiente',
                'txStatus'         => 'Pendiente',
                'paymentMethod'    => $this->paymentMethods[0] ?? null,
                'gatewayReference' => 'pi_fixture_pendiente',
                'tracking'         => null,
            ],
            [
                'orderStatus'      => 'Procesando',
                'txStatus'         => 'Procesando',
                'paymentMethod'    => $this->paymentMethods[1] ?? null,
                'gatewayReference' => null,
                'tracking'         => null,
            ],
            [
                'orderStatus'      => 'Enviado',
                'txStatus'         => 'Procesando',
                'paymentMethod'    => $this->paymentMethods[1] ?? null,
                'gatewayReference' => null,
                'tracking'         => ['number' => 'TRK-0001', 'carrier' => 'Servientrega'],
            ],
            [
                'orderStatus'      => 'Cancelado',
                'txStatus'         => 'Cancelado',
                'paymentMethod'    => $this->paymentMethods[0] ?? null,
                'gatewayReference' => 'pi_fixture_cancelado',
                'tracking'         => null,
            ],
        ];

        foreach ($scenarios as $scenario) {
            $orderStatus = $this->findStatusByName($scenario['orderStatus']);
            $txStatus    = $this->findStatusByName($scenario['txStatus']);
            if ($orderStatus === null || $txStatus === null) {
                continue;
            }

            $order = new Orders();
            $order->add($customer, $totalPrice, 'Calle 123 #45-67', $country, $region, $city, $orderStatus);
            if ($scenario['tracking'] !== null) {
                $order->setTracking($scenario['tracking']['number'], $scenario['tracking']['carrier']);
            }
            $em->persist($order);

            $orderProduct = (new OrdersProducts())->add($order, $product, $quantity, $unitPrice, $totalPrice);
            $em->persist($orderProduct);

            if ($scenario['paymentMethod'] !== null) {
                $tx = (new PaymentTransactions())->add($order, $txStatus, $totalPrice, $scenario['paymentMethod']);
                if ($scenario['gatewayReference'] !== null) {
                    $tx->setGatewayData($scenario['gatewayReference'], null);
                }
                $em->persist($tx);
            }
        }
    }

    private function findStatusByName(string $name): ?Status
    {
        foreach ($this->statuses as $status) {
            if ($status->getName() === $name) {
                return $status;
            }
        }
        return null;
    }

    private function createMessages(ObjectManager $em): void
    {
        if (count($this->users) < 2) {
            return;
        }

        $superAdmin = $this->users[0];
        $admin      = $this->users[1];

        $rootMessage = new HelpMessages();
        $rootMessage->add($admin, $superAdmin, 'Mensaje de soporte del superadmin al admin');
        $em->persist($rootMessage);

        $reply = new HelpMessages();
        $reply->addReply($superAdmin, $admin, 'Respuesta del admin al superadmin', $rootMessage);
        $reply->markAsRead();
        $em->persist($reply);

        $sentMessage = new HelpMessages();
        $sentMessage->add($superAdmin, $admin, 'Consulta del admin al superadmin');
        $sentMessage->markAsRead();
        $em->persist($sentMessage);

        $image = new HelpMessageImages();
        $image->add($sentMessage, 'uploads/messages/test-screenshot.png');
        $em->persist($image);
    }

    private function createStatistics(ObjectManager $em): void
    {
        $domain  = reset($this->domains);
        $country = !empty($this->countries) ? $this->countries[0] : null;
        $region  = !empty($this->regions)   ? $this->regions[0]   : null;
        $city    = !empty($this->cities)    ? $this->cities[0]    : null;

        $rows = [
            [
                'ip' => '192.168.1.1', 'device' => 'desktop', 'browser' => 'Chrome',
                'os' => 'Windows', 'lang' => 'es', 'page' => '/es/shop',
                'referrer' => 'https://google.com/search?q=test', 'referrerDomain' => 'google.com',
                'utmSource' => 'google', 'utmMedium' => 'cpc', 'utmCampaign' => 'black_friday',
                'sessionId' => 'session-fixture-001',
            ],
            [
                'ip' => '192.168.1.2', 'device' => 'mobile', 'browser' => 'Firefox',
                'os' => 'Android', 'lang' => 'es', 'page' => '/es/home',
                'referrer' => null, 'referrerDomain' => null,
                'utmSource' => null, 'utmMedium' => null, 'utmCampaign' => null,
                'sessionId' => 'session-fixture-002',
            ],
            [
                'ip' => '192.168.1.1', 'device' => 'desktop', 'browser' => 'Chrome',
                'os' => 'Windows', 'lang' => 'es', 'page' => '/es/shop',
                'referrer' => null, 'referrerDomain' => null,
                'utmSource' => 'google', 'utmMedium' => 'organic', 'utmCampaign' => null,
                'sessionId' => 'session-fixture-003',
            ],
        ];

        foreach ($rows as $row) {
            $em->persist((new Statistics())->add(
                domain:          $domain,
                ip:              $row['ip'],
                device:          $row['device'],
                country:         $country?->getName('es'),
                region:          $region?->getName('es'),
                city:            $city?->getName('es'),
                browser:         $row['browser'],
                operativeSystem: $row['os'],
                lang:            $row['lang'],
                page:            $row['page'],
                referrer:        $row['referrer'],
                referrerDomain:  $row['referrerDomain'],
                utmSource:       $row['utmSource'],
                utmMedium:       $row['utmMedium'],
                utmCampaign:     $row['utmCampaign'],
                sessionId:       $row['sessionId'],
                countryIsoCode:  $country?->getIsoCode(),
            ));
        }

        $this->createStatisticsHistory($em, $domain);
    }

    private function createStatisticsHistory(ObjectManager $em, Domains $domain): void
    {
        $today = new \DateTimeImmutable('today');

        $countries = [
            ['Colombia', 'CO'], ['México', 'MX'], ['Argentina', 'AR'],
            ['España', 'ES'], ['Estados Unidos', 'US'], ['Brasil', 'BR'],
            ['Chile', 'CL'], ['Perú', 'PE'], ['Venezuela', 'VE'], ['Ecuador', 'EC'],
        ];
        $browsers = [
            'Chrome', 'Chrome', 'Chrome', 'Chrome', 'Chrome', 'Chrome',
            'Firefox', 'Firefox', 'Firefox', 'Safari', 'Safari', 'Edge', 'Opera',
        ];
        $devices = ['desktop', 'desktop', 'desktop', 'mobile', 'mobile', 'mobile', 'tablet'];
        $oss     = ['Windows', 'Windows', 'Windows', 'Android', 'Android', 'macOS', 'iOS', 'Linux'];
        $pages   = [
            '/es/home', '/es/home', '/es/home',
            '/es/shop', '/es/shop',
            '/es/products/zapatillas-deportivas', '/es/products/zapatillas-deportivas',
            '/es/products/camiseta-polo',
            '/es/about', '/es/contact',
            '/es/categories/ropa', '/es/categories/calzado',
        ];
        $referrerPairs = [
            [null, null], [null, null], [null, null],
            ['https://google.com/search?q=ropa', 'google.com'],
            ['https://google.com/search?q=tienda', 'google.com'],
            ['https://facebook.com/ads', 'facebook.com'],
            ['https://instagram.com', 'instagram.com'],
            ['https://twitter.com', 'twitter.com'],
        ];
        $utms = [
            [null, null, null], [null, null, null], [null, null, null],
            ['google', 'cpc', 'black_friday'],
            ['google', 'organic', null],
            ['facebook', 'social', 'summer_sale'],
            ['instagram', 'social', 'launch_2024'],
            ['email', 'newsletter', 'promo_diciembre'],
        ];

        $createdAtProp = (new \ReflectionClass(Statistics::class))->getProperty('createdAt');
        $idx = 0;

        for ($daysAgo = 90; $daysAgo >= 2; $daysAgo--) {
            $visitsThisDay = match (true) {
                $daysAgo > 60 => 2,
                $daysAgo > 30 => 3,
                $daysAgo > 14 => 4,
                default       => 6,
            };
            $date = $today->modify("-{$daysAgo} days");

            for ($v = 0; $v < $visitsThisDay; $v++, $idx++) {
                [$referrer, $referrerDomain] = $referrerPairs[$idx % count($referrerPairs)];
                [$utmSource, $utmMedium, $utmCampaign] = $utms[$idx % count($utms)];
                [$countryName, $countryIso] = $countries[$idx % count($countries)];

                $stat = (new Statistics())->add(
                    domain:          $domain,
                    ip:              sprintf('10.%d.%d.1', $idx % 50, $idx % 200),
                    device:          $devices[$idx % count($devices)],
                    country:         $countryName,
                    countryIsoCode:  $countryIso,
                    browser:         $browsers[$idx % count($browsers)],
                    operativeSystem: $oss[$idx % count($oss)],
                    lang:            'es',
                    page:            $pages[$idx % count($pages)],
                    referrer:        $referrer,
                    referrerDomain:  $referrerDomain,
                    utmSource:       $utmSource,
                    utmMedium:       $utmMedium,
                    utmCampaign:     $utmCampaign,
                    sessionId:       sprintf('session-viz-%05d', $idx),
                );

                $em->persist($stat);

                $createdAtProp->setValue($stat, $date->modify("+{$v} hours"));
            }
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
        $this->medidas['m'] = $m;
        $this->medidas['l'] = $l;

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
