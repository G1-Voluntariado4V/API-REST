<?php
//Creo curso y modifico voluntario
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206141625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE CURSO (id_curso INT IDENTITY NOT NULL, nombre_curso NVARCHAR(100) NOT NULL, abreviacion_curso NVARCHAR(10) NOT NULL, grado NVARCHAR(50) NOT NULL, nivel INT NOT NULL, PRIMARY KEY (id_curso))');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD fecha_nac DATE');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD carnet_conducir BIT');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD img_perfil NVARCHAR(255)');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD updated_at DATETIME2(6)');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD id_curso_actual INT');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCD6492F FOREIGN KEY (id_curso_actual) REFERENCES CURSO (id_curso)');
        $this->addSql('CREATE INDEX IDX_2AFD2CC1FCD6492F ON VOLUNTARIO (id_curso_actual)');
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
        $this->addSql('DROP TABLE CURSO');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCD6492F');
        $this->addSql('DROP INDEX IDX_2AFD2CC1FCD6492F ON VOLUNTARIO');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP COLUMN fecha_nac');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP COLUMN carnet_conducir');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP COLUMN img_perfil');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP COLUMN updated_at');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP COLUMN id_curso_actual');
    }
}
