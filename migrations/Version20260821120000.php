<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catálogo de monedas (currencies/domain_currencies), asigna COP a los domains existentes y migra precios de productos ya cargados a la nueva convención (sin ÷100 fantasma).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE currencies (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, symbol VARCHAR(10) NOT NULL, decimal_places INT NOT NULL, thousands_separator VARCHAR(5) NOT NULL, decimal_separator VARCHAR(5) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CURRENCIES_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain_currencies (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', currency_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_DOMAIN_CURRENCIES_DOMAIN (domain_id), INDEX IDX_DOMAIN_CURRENCIES_CURRENCY (currency_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain_currencies ADD CONSTRAINT FK_DOMAIN_CURRENCIES_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domain_currencies ADD CONSTRAINT FK_DOMAIN_CURRENCIES_CURRENCY FOREIGN KEY (currency_id) REFERENCES currencies (id)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $copId = 'c0000000-0000-4000-8000-000000000001';
        $usdId = 'c0000000-0000-4000-8000-000000000002';
        $eurId = 'c0000000-0000-4000-8000-000000000003';

        $this->addSql(
            "INSERT INTO currencies (id, code, name, symbol, decimal_places, thousands_separator, decimal_separator, active, created_at, updated_at) VALUES
            (UUID_TO_BIN('$copId'), 'COP', 'Peso colombiano', '\$', 0, '.', ',', 1, '$now', '$now'),
            (UUID_TO_BIN('$usdId'), 'USD', 'Dólar estadounidense', '\$', 2, ',', '.', 1, '$now', '$now'),
            (UUID_TO_BIN('$eurId'), 'EUR', 'Euro', '€', 2, '.', ',', 1, '$now', '$now')"
        );

        $this->addSql(
            "INSERT INTO domain_currencies (id, domain_id, currency_id, active, created_at, updated_at)
            SELECT UUID_TO_BIN(UUID()), d.id, UUID_TO_BIN('$copId'), 1, '$now', '$now'
            FROM domains d WHERE d.active = 1"
        );

        $this->addSql('UPDATE products SET base_price = ROUND(base_price / 100), public_price = ROUND(public_price / 100)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE products SET base_price = base_price * 100, public_price = public_price * 100');
        $this->addSql('ALTER TABLE domain_currencies DROP FOREIGN KEY FK_DOMAIN_CURRENCIES_DOMAIN');
        $this->addSql('ALTER TABLE domain_currencies DROP FOREIGN KEY FK_DOMAIN_CURRENCIES_CURRENCY');
        $this->addSql('DROP TABLE domain_currencies');
        $this->addSql('DROP TABLE currencies');
    }
}
