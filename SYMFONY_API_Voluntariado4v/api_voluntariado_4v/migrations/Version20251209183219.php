<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209183219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ACTIVIDAD (id_actividad INT IDENTITY NOT NULL, titulo NVARCHAR(150) NOT NULL, descripcion VARCHAR(MAX), fecha_inicio DATETIME2(6) NOT NULL, duracion_horas INT NOT NULL, cupo_maximo INT NOT NULL, ubicacion VARCHAR(MAX), estado_publicacion NVARCHAR(20) NOT NULL, updated_at DATETIME2(6), deleted_at DATETIME2(6), id_organizacion INT NOT NULL, PRIMARY KEY (id_actividad))');
        $this->addSql('CREATE INDEX IDX_C930A3E92FE17928 ON ACTIVIDAD (id_organizacion)');
        $this->addSql('ALTER TABLE ACTIVIDAD ADD DEFAULT \'En revision\' FOR estado_publicacion');
        $this->addSql('CREATE TABLE ACTIVIDAD_ODS (id_actividad INT NOT NULL, id_ods INT NOT NULL, PRIMARY KEY (id_actividad, id_ods))');
        $this->addSql('CREATE INDEX IDX_E22C2136DC70121 ON ACTIVIDAD_ODS (id_actividad)');
        $this->addSql('CREATE INDEX IDX_E22C2136EAF33370 ON ACTIVIDAD_ODS (id_ods)');
        $this->addSql('CREATE TABLE ACTIVIDAD_TIPO (id_actividad INT NOT NULL, id_tipo INT NOT NULL, PRIMARY KEY (id_actividad, id_tipo))');
        $this->addSql('CREATE INDEX IDX_5FD41A3ADC70121 ON ACTIVIDAD_TIPO (id_actividad)');
        $this->addSql('CREATE INDEX IDX_5FD41A3AFB0D0145 ON ACTIVIDAD_TIPO (id_tipo)');
        $this->addSql('CREATE TABLE COORDINADOR (nombre NVARCHAR(50), apellidos NVARCHAR(100), telefono NVARCHAR(20), updated_at DATETIME2(6), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE TABLE CURSO (id_curso INT IDENTITY NOT NULL, nombre_curso NVARCHAR(100) NOT NULL, abreviacion_curso NVARCHAR(10) NOT NULL, grado NVARCHAR(50) NOT NULL, nivel INT NOT NULL, PRIMARY KEY (id_curso))');
        $this->addSql('CREATE TABLE IDIOMA (id_idioma INT IDENTITY NOT NULL, nombre_idioma NVARCHAR(50) NOT NULL, codigo_iso NVARCHAR(3), PRIMARY KEY (id_idioma))');
        $this->addSql('CREATE TABLE IMAGEN_ACTIVIDAD (id_imagen INT IDENTITY NOT NULL, url_imagen NVARCHAR(255) NOT NULL, descripcion_pie_foto NVARCHAR(255), id_actividad INT NOT NULL, PRIMARY KEY (id_imagen))');
        $this->addSql('CREATE INDEX IDX_7EDBA3FFDC70121 ON IMAGEN_ACTIVIDAD (id_actividad)');
        $this->addSql('CREATE TABLE INSCRIPCION (fecha_solicitud DATETIME2(6) NOT NULL, estado_solicitud NVARCHAR(20) NOT NULL, updated_at DATETIME2(6), id_voluntario INT NOT NULL, id_actividad INT NOT NULL, PRIMARY KEY (id_voluntario, id_actividad))');
        $this->addSql('CREATE INDEX IDX_1E5D0B1884E8B129 ON INSCRIPCION (id_voluntario)');
        $this->addSql('CREATE INDEX IDX_1E5D0B18DC70121 ON INSCRIPCION (id_actividad)');
        $this->addSql('ALTER TABLE INSCRIPCION ADD DEFAULT \'Pendiente\' FOR estado_solicitud');
        $this->addSql('CREATE TABLE ORGANIZACION (cif NVARCHAR(20), nombre NVARCHAR(100), descripcion VARCHAR(MAX), direccion VARCHAR(MAX), sitio_web NVARCHAR(200), telefono NVARCHAR(20), img_perfil NVARCHAR(255), updated_at DATETIME2(6), id_usuario INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9912454AA53EB8E8 ON ORGANIZACION (cif) WHERE cif IS NOT NULL');
        $this->addSql('CREATE TABLE ROL (id_rol INT IDENTITY NOT NULL, nombre_rol NVARCHAR(50) NOT NULL, PRIMARY KEY (id_rol))');
        $this->addSql('CREATE TABLE USUARIO (id_usuario INT IDENTITY NOT NULL, correo NVARCHAR(100) NOT NULL, google_id NVARCHAR(255) NOT NULL, refresh_token NVARCHAR(500), estado_cuenta NVARCHAR(20) NOT NULL, fecha_registro DATETIME2(6) NOT NULL, updated_at DATETIME2(6), deleted_at DATETIME2(6), id_rol INT NOT NULL, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D204E4777040BC9 ON USUARIO (correo) WHERE correo IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D204E4776F5C865 ON USUARIO (google_id) WHERE google_id IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_1D204E4790F1D76D ON USUARIO (id_rol)');
        $this->addSql('ALTER TABLE USUARIO ADD DEFAULT \'Pendiente\' FOR estado_cuenta');
        $this->addSql('CREATE TABLE VOLUNTARIO (dni NVARCHAR(9), nombre NVARCHAR(50) NOT NULL, apellidos NVARCHAR(100) NOT NULL, telefono NVARCHAR(20), fecha_nac DATE, carnet_conducir BIT, img_perfil NVARCHAR(255), updated_at DATETIME2(6), id_usuario INT NOT NULL, id_curso_actual INT, PRIMARY KEY (id_usuario))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2AFD2CC17F8F253B ON VOLUNTARIO (dni) WHERE dni IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_2AFD2CC1FCD6492F ON VOLUNTARIO (id_curso_actual)');
        $this->addSql('CREATE TABLE PREFERENCIA_VOLUNTARIO (id_voluntario INT NOT NULL, id_tipo INT NOT NULL, PRIMARY KEY (id_voluntario, id_tipo))');
        $this->addSql('CREATE INDEX IDX_6333FBBA84E8B129 ON PREFERENCIA_VOLUNTARIO (id_voluntario)');
        $this->addSql('CREATE INDEX IDX_6333FBBAFB0D0145 ON PREFERENCIA_VOLUNTARIO (id_tipo)');
        $this->addSql('CREATE TABLE VOLUNTARIO_IDIOMA (nivel NVARCHAR(20), id_voluntario INT NOT NULL, id_idioma INT NOT NULL, PRIMARY KEY (id_voluntario, id_idioma))');
        $this->addSql('CREATE INDEX IDX_712CF4CD84E8B129 ON VOLUNTARIO_IDIOMA (id_voluntario)');
        $this->addSql('CREATE INDEX IDX_712CF4CD3BFFEBE1 ON VOLUNTARIO_IDIOMA (id_idioma)');
        $this->addSql('CREATE TABLE ods (id_ods INT NOT NULL, nombre NVARCHAR(150) NOT NULL, descripcion VARCHAR(MAX), PRIMARY KEY (id_ods))');
        $this->addSql('CREATE TABLE tipo_voluntariado (id_tipo INT IDENTITY NOT NULL, nombre_tipo NVARCHAR(100) NOT NULL, PRIMARY KEY (id_tipo))');
        $this->addSql('ALTER TABLE ACTIVIDAD ADD CONSTRAINT FK_C930A3E92FE17928 FOREIGN KEY (id_organizacion) REFERENCES ORGANIZACION (id_usuario)');
        $this->addSql('ALTER TABLE ACTIVIDAD_ODS ADD CONSTRAINT FK_E22C2136DC70121 FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD (id_actividad)');
        $this->addSql('ALTER TABLE ACTIVIDAD_ODS ADD CONSTRAINT FK_E22C2136EAF33370 FOREIGN KEY (id_ods) REFERENCES ods (id_ods)');
        $this->addSql('ALTER TABLE ACTIVIDAD_TIPO ADD CONSTRAINT FK_5FD41A3ADC70121 FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD (id_actividad)');
        $this->addSql('ALTER TABLE ACTIVIDAD_TIPO ADD CONSTRAINT FK_5FD41A3AFB0D0145 FOREIGN KEY (id_tipo) REFERENCES tipo_voluntariado (id_tipo)');
        $this->addSql('ALTER TABLE COORDINADOR ADD CONSTRAINT FK_84DB3FF2FCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE IMAGEN_ACTIVIDAD ADD CONSTRAINT FK_7EDBA3FFDC70121 FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD (id_actividad) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE INSCRIPCION ADD CONSTRAINT FK_1E5D0B1884E8B129 FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE INSCRIPCION ADD CONSTRAINT FK_1E5D0B18DC70121 FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD (id_actividad)');
        $this->addSql('ALTER TABLE ORGANIZACION ADD CONSTRAINT FK_9912454AFCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE USUARIO ADD CONSTRAINT FK_1D204E4790F1D76D FOREIGN KEY (id_rol) REFERENCES ROL (id_rol)');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCF8192D FOREIGN KEY (id_usuario) REFERENCES USUARIO (id_usuario) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE VOLUNTARIO ADD CONSTRAINT FK_2AFD2CC1FCD6492F FOREIGN KEY (id_curso_actual) REFERENCES CURSO (id_curso)');
        $this->addSql('ALTER TABLE PREFERENCIA_VOLUNTARIO ADD CONSTRAINT FK_6333FBBA84E8B129 FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO (id_usuario)');
        $this->addSql('ALTER TABLE PREFERENCIA_VOLUNTARIO ADD CONSTRAINT FK_6333FBBAFB0D0145 FOREIGN KEY (id_tipo) REFERENCES tipo_voluntariado (id_tipo)');
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
        $this->addSql('ALTER TABLE ACTIVIDAD DROP CONSTRAINT FK_C930A3E92FE17928');
        $this->addSql('ALTER TABLE ACTIVIDAD_ODS DROP CONSTRAINT FK_E22C2136DC70121');
        $this->addSql('ALTER TABLE ACTIVIDAD_ODS DROP CONSTRAINT FK_E22C2136EAF33370');
        $this->addSql('ALTER TABLE ACTIVIDAD_TIPO DROP CONSTRAINT FK_5FD41A3ADC70121');
        $this->addSql('ALTER TABLE ACTIVIDAD_TIPO DROP CONSTRAINT FK_5FD41A3AFB0D0145');
        $this->addSql('ALTER TABLE COORDINADOR DROP CONSTRAINT FK_84DB3FF2FCF8192D');
        $this->addSql('ALTER TABLE IMAGEN_ACTIVIDAD DROP CONSTRAINT FK_7EDBA3FFDC70121');
        $this->addSql('ALTER TABLE INSCRIPCION DROP CONSTRAINT FK_1E5D0B1884E8B129');
        $this->addSql('ALTER TABLE INSCRIPCION DROP CONSTRAINT FK_1E5D0B18DC70121');
        $this->addSql('ALTER TABLE ORGANIZACION DROP CONSTRAINT FK_9912454AFCF8192D');
        $this->addSql('ALTER TABLE USUARIO DROP CONSTRAINT FK_1D204E4790F1D76D');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCF8192D');
        $this->addSql('ALTER TABLE VOLUNTARIO DROP CONSTRAINT FK_2AFD2CC1FCD6492F');
        $this->addSql('ALTER TABLE PREFERENCIA_VOLUNTARIO DROP CONSTRAINT FK_6333FBBA84E8B129');
        $this->addSql('ALTER TABLE PREFERENCIA_VOLUNTARIO DROP CONSTRAINT FK_6333FBBAFB0D0145');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA DROP CONSTRAINT FK_712CF4CD84E8B129');
        $this->addSql('ALTER TABLE VOLUNTARIO_IDIOMA DROP CONSTRAINT FK_712CF4CD3BFFEBE1');
        $this->addSql('DROP TABLE ACTIVIDAD');
        $this->addSql('DROP TABLE ACTIVIDAD_ODS');
        $this->addSql('DROP TABLE ACTIVIDAD_TIPO');
        $this->addSql('DROP TABLE COORDINADOR');
        $this->addSql('DROP TABLE CURSO');
        $this->addSql('DROP TABLE IDIOMA');
        $this->addSql('DROP TABLE IMAGEN_ACTIVIDAD');
        $this->addSql('DROP TABLE INSCRIPCION');
        $this->addSql('DROP TABLE ORGANIZACION');
        $this->addSql('DROP TABLE ROL');
        $this->addSql('DROP TABLE USUARIO');
        $this->addSql('DROP TABLE VOLUNTARIO');
        $this->addSql('DROP TABLE PREFERENCIA_VOLUNTARIO');
        $this->addSql('DROP TABLE VOLUNTARIO_IDIOMA');
        $this->addSql('DROP TABLE ods');
        $this->addSql('DROP TABLE tipo_voluntariado');
    }
}
