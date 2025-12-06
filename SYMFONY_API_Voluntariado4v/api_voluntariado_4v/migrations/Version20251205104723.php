<?php
//CREO ROL Y USUARIO
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205104723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ROL (id_rol INT IDENTITY NOT NULL, nombre_rol NVARCHAR(50) NOT NULL, PRIMARY KEY (id_rol))');
        $this->addSql('CREATE TABLE USUARIO (id_usuario INT IDENTITY NOT NULL, correo NVARCHAR(100) NOT NULL, google_id NVARCHAR(255) NOT NULL, refresh_token NVARCHAR(500), fecha_registro DATETIME2(6) NOT NULL, estado_cuenta NVARCHAR(20) NOT NULL, updated_at DATETIME2(6), deleted_at DATETIME2(6), id_rol INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE INDEX IDX_1D204E4790F1D76D ON USUARIO (id_rol)');
        $this->addSql('ALTER TABLE USUARIO ADD CONSTRAINT FK_1D204E4790F1D76D FOREIGN KEY (id_rol) REFERENCES ROL (id_rol)');
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
        $this->addSql('ALTER TABLE USUARIO DROP CONSTRAINT FK_1D204E4790F1D76D');
        $this->addSql('DROP TABLE ROL');
        $this->addSql('DROP TABLE USUARIO');
    }
}
