<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208105934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE COORDINADOR (nombre NVARCHAR(50), apellidos NVARCHAR(100), telefono NVARCHAR(20), updated_at DATETIME2(6), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE TABLE CURSO (id_curso INT IDENTITY NOT NULL, nombre_curso NVARCHAR(100) NOT NULL, abreviacion_curso NVARCHAR(10) NOT NULL, grado NVARCHAR(50) NOT NULL, nivel INT NOT NULL, PRIMARY KEY (id_curso))');
        $this->addSql('CREATE TABLE IDIOMA (id_idioma INT IDENTITY NOT NULL, nombre_idioma NVARCHAR(50) NOT NULL, codigo_iso NVARCHAR(3), PRIMARY KEY (id_idioma))');
        $this->addSql('CREATE TABLE ORGANIZACION (cif NVARCHAR(20), nombre NVARCHAR(100), descripcion VARCHAR(MAX), direccion VARCHAR(MAX), sitio_web NVARCHAR(200), telefono NVARCHAR(20), img_perfil NVARCHAR(255), updated_at DATETIME2(6), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9912454AA53EB8E8 ON ORGANIZACION (cif) WHERE cif IS NOT NULL');
        $this->addSql('CREATE TABLE ROL (id_rol INT IDENTITY NOT NULL, nombre_rol NVARCHAR(50) NOT NULL, PRIMARY KEY (id_rol))');
        $this->addSql('CREATE TABLE USUARIO (id_usuario INT IDENTITY NOT NULL, correo NVARCHAR(180) NOT NULL, roles_symfony_ignored VARCHAR(MAX) NOT NULL, password NVARCHAR(255) NOT NULL, google_id NVARCHAR(255) NOT NULL, estado_cuenta NVARCHAR(20) NOT NULL, refresh_token NVARCHAR(500), fecha_registro DATETIME2(6), updated_at DATETIME2(6), deleted_at DATETIME2(6), id_rol INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE INDEX IDX_1D204E4790F1D76D ON USUARIO (id_rol)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_CORREO ON USUARIO (correo) WHERE correo IS NOT NULL');
        $this->addSql('CREATE TABLE VOLUNTARIO (dni NVARCHAR(9), nombre NVARCHAR(50) NOT NULL, apellidos NVARCHAR(100) NOT NULL, telefono NVARCHAR(20), fecha_nac DATE, carnet_conducir BIT, img_perfil NVARCHAR(255), updated_at DATETIME2(6), id_usuario INT NOT NULL, id_curso_actual INT, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2AFD2CC17F8F253B ON VOLUNTARIO (dni) WHERE dni IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_2AFD2CC1FCD6492F ON VOLUNTARIO (id_curso_actual)');
        $this->addSql('CREATE TABLE VOLUNTARIO_IDIOMA (id INT IDENTITY NOT NULL, nivel NVARCHAR(20), id_voluntario INT NOT NULL, id_idioma INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_712CF4CD84E8B129 ON VOLUNTARIO_IDIOMA (id_voluntario)');
        $this->addSql('CREATE INDEX IDX_712CF4CD3BFFEBE1 ON VOLUNTARIO_IDIOMA (id_idioma)');
        $this->addSql('CREATE TABLE ods (id INT NOT NULL, nombre NVARCHAR(150) NOT NULL, descripcion VARCHAR(MAX), PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE tipo_voluntariado (id INT IDENTITY NOT NULL, nombre_tipo NVARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE COORDINADOR ADD CONSTRAINT FK_84DB3FF2FCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ORGANIZACION ADD CONSTRAINT FK_9912454AFCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE USUARIO ADD CONSTRAINT FK_1D204E4790F1D76D FOREIGN KEY (id_rol) REFERENCES ROL (id_rol)');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCD6492F FOREIGN KEY (id_curso_actual) REFERENCES CURSO (id_curso)');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA ADD CONSTRAINT FK_712CF4CD84E8B129 FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA ADD CONSTRAINT FK_712CF4CD3BFFEBE1 FOREIGN KEY (id_idioma) REFERENCES IDIOMA (id_idioma)');
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
        $this->addSql('ALTER TABLE COORDINADOR DROP CONSTRAINT FK_84DB3FF2FCF8192D');
        $this->addSql('ALTER TABLE ORGANIZACION DROP CONSTRAINT FK_9912454AFCF8192D');
        $this->addSql('ALTER TABLE USUARIO DROP CONSTRAINT FK_1D204E4790F1D76D');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCF8192D');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCD6492F');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA DROP CONSTRAINT FK_712CF4CD84E8B129');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA DROP CONSTRAINT FK_712CF4CD3BFFEBE1');
        $this->addSql('DROP TABLE COORDINADOR');
        $this->addSql('DROP TABLE CURSO');
        $this->addSql('DROP TABLE IDIOMA');
        $this->addSql('DROP TABLE ORGANIZACION');
        $this->addSql('DROP TABLE ROL');
        $this->addSql('DROP TABLE USUARIO');
        $this->addSql('DROP TABLE VOLUNTARIO');
        $this->addSql('DROP TABLE VOLUNTARIO_IDIOMA');
        $this->addSql('DROP TABLE ods');
        $this->addSql('DROP TABLE tipo_voluntariado');
    }
}
