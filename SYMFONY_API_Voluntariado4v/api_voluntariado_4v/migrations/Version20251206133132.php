<?php
//creo voluntario y modifico usuario
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206133132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE VOLUNTARIO (dni NVARCHAR(9), nombre NVARCHAR(50) NOT NULL, apellidos NVARCHAR(100) NOT NULL, telefono NVARCHAR(20), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2AFD2CC17F8F253B ON VOLUNTARIO (dni) WHERE dni IS NOT NULL');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario)');
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
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCF8192D');
        $this->addSql('DROP TABLE VOLUNTARIO');
    }
}
