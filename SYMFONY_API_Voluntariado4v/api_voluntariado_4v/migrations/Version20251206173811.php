<?php
//Creo organización
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206173811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ORGANIZACION (cif NVARCHAR(20), nombre NVARCHAR(100), descripcion VARCHAR(MAX), direccion VARCHAR(MAX), sitio_web NVARCHAR(200), telefono NVARCHAR(20), img_perfil NVARCHAR(255), updated_at DATETIME2(6), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9912454AA53EB8E8 ON ORGANIZACION (cif) WHERE cif IS NOT NULL');
        $this->addSql('ALTER TABLE ORGANIZACION ADD CONSTRAINT FK_9912454AFCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA db_accessadmin');
        $this->addSql('CREATE SCHEMA db_backupoperator');
        $this->addSql('CREATE SCHEMA db_datareader');
        $this->addSql('CREATE SCHEMA db_datawriter');
        $this->addSql('CREATE SCHEMA db_ddladmin');
        $this->addSql('CREATE SCHEMA db_denydatareader');
        $this->addSql('CREATE SCHEMA db_denydatawriter');
        $this->addSql('CREATE SCHEMA db_owner');
        $this->addSql('CREATE SCHEMA db_securityadmin');
        $this->addSql('ALTER TABLE ORGANIZACION DROP CONSTRAINT FK_9912454AFCF8192D');
        $this->addSql('DROP TABLE ORGANIZACION');
    }
}
