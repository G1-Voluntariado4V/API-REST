<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103120130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade triggers, vistas y procedimientos almacenados (Sin referencias a img_perfil)';
    }

    public function up(Schema $schema): void
    {
        // =========================================================================
        // 1. TRIGGERS DE FECHA (UPDATED_AT)
        // =========================================================================
        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Usuario_UpdatedAt ON USUARIO AFTER UPDATE AS 
            BEGIN 
                SET NOCOUNT ON; 
                UPDATE USUARIO SET updated_at = GETDATE() FROM USUARIO u INNER JOIN inserted i ON u.id_usuario = i.id_usuario; 
            END
        ");

        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Voluntario_UpdatedAt ON VOLUNTARIO AFTER UPDATE AS 
            BEGIN 
                SET NOCOUNT ON; 
                UPDATE VOLUNTARIO SET updated_at = GETDATE() FROM VOLUNTARIO v INNER JOIN inserted i ON v.id_usuario = i.id_usuario; 
            END
        ");

        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Organizacion_UpdatedAt ON ORGANIZACION AFTER UPDATE AS 
            BEGIN 
                SET NOCOUNT ON; 
                UPDATE ORGANIZACION SET updated_at = GETDATE() FROM ORGANIZACION o INNER JOIN inserted i ON o.id_usuario = i.id_usuario; 
            END
        ");

        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Actividad_UpdatedAt ON ACTIVIDAD AFTER UPDATE AS 
            BEGIN 
                SET NOCOUNT ON; 
                UPDATE ACTIVIDAD SET updated_at = GETDATE() FROM ACTIVIDAD a INNER JOIN inserted i ON a.id_actividad = i.id_actividad; 
            END
        ");

        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Inscripcion_UpdatedAt ON INSCRIPCION AFTER UPDATE AS 
            BEGIN 
                SET NOCOUNT ON; 
                UPDATE INSCRIPCION SET updated_at = GETDATE() FROM INSCRIPCION ins INNER JOIN inserted i ON ins.id_voluntario = i.id_voluntario AND ins.id_actividad = i.id_actividad; 
            END
        ");

        // =========================================================================
        // 2. TRIGGER DE SEGURIDAD (SOFT DELETE USUARIO)
        // =========================================================================
        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Protect_Usuario_Delete ON USUARIO INSTEAD OF DELETE AS
            BEGIN
                SET NOCOUNT ON;
                UPDATE USUARIO
                SET deleted_at = GETDATE(), estado_cuenta = 'Bloqueada'
                WHERE id_usuario IN (SELECT id_usuario FROM deleted);
            END
        ");

        // =========================================================================
        // 3. TRIGGER MAESTRO DE VALIDACIÓN (INSCRIPCIONES)
        // =========================================================================
        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Check_Cupo_Actividad ON INSCRIPCION INSTEAD OF INSERT AS
            BEGIN
                SET NOCOUNT ON;
                
                -- 1. VALIDACIÓN: Estado y Fecha
                IF EXISTS (
                    SELECT 1 FROM inserted i JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                    WHERE (a.estado_publicacion <> 'Publicada') OR (a.fecha_inicio < GETDATE())
                )
                BEGIN
                    RAISERROR ('ERROR: Actividad no publicada o finalizada.', 16, 1);
                    ROLLBACK TRANSACTION;
                    RETURN;
                END

                -- 2. VALIDACIÓN: Agenda (Solapamiento)
                IF EXISTS (
                    SELECT 1 FROM inserted i_new
                    JOIN ACTIVIDAD a_new ON i_new.id_actividad = a_new.id_actividad
                    JOIN INSCRIPCION i_old ON i_new.id_voluntario = i_old.id_voluntario
                    JOIN ACTIVIDAD a_old ON i_old.id_actividad = a_old.id_actividad
                    WHERE i_old.estado_solicitud IN ('Aceptada', 'Pendiente')
                      AND a_new.id_actividad <> a_old.id_actividad
                      AND a_new.fecha_inicio < DATEADD(HOUR, a_old.duracion_horas, a_old.fecha_inicio)
                      AND DATEADD(HOUR, a_new.duracion_horas, a_new.fecha_inicio) > a_old.fecha_inicio
                )
                BEGIN
                    RAISERROR ('ERROR AGENDA: Ya tienes actividad en ese horario.', 16, 1);
                    ROLLBACK TRANSACTION;
                    RETURN;
                END

                -- 3. VALIDACIÓN: Cupo
                IF EXISTS (
                    SELECT 1 FROM inserted i JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                    WHERE a.cupo_maximo IS NOT NULL
                      AND (SELECT COUNT(*) FROM INSCRIPCION ins WHERE ins.id_actividad = a.id_actividad AND ins.estado_solicitud = 'Aceptada') >= a.cupo_maximo
                )
                BEGIN
                    RAISERROR ('ERROR CUPO: Actividad completa.', 16, 1);
                    ROLLBACK TRANSACTION;
                    RETURN;
                END

                -- 4. ÉXITO
                INSERT INTO INSCRIPCION (id_voluntario, id_actividad, fecha_solicitud, estado_solicitud)
                SELECT id_voluntario, id_actividad, GETDATE(), estado_solicitud FROM inserted;
            END
        ");

        // TRIGGER CONSISTENCIA UPDATE CUPO
        $this->addSql("
            CREATE OR ALTER TRIGGER TR_Check_Cupo_Update ON INSCRIPCION AFTER UPDATE AS
            BEGIN
                SET NOCOUNT ON;
                IF EXISTS (
                    SELECT 1 FROM inserted i
                    INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                    INNER JOIN deleted d ON i.id_voluntario = d.id_voluntario AND i.id_actividad = d.id_actividad
                    WHERE i.estado_solicitud = 'Aceptada' AND d.estado_solicitud <> 'Aceptada' AND a.cupo_maximo IS NOT NULL
                )
                BEGIN
                    IF EXISTS (
                        SELECT a.id_actividad FROM ACTIVIDAD a
                        INNER JOIN inserted i ON a.id_actividad = i.id_actividad
                        WHERE a.cupo_maximo IS NOT NULL
                        AND (
                            (SELECT COUNT(*) FROM INSCRIPCION ins WHERE ins.id_actividad = a.id_actividad AND ins.estado_solicitud = 'Aceptada' AND ins.id_voluntario NOT IN (SELECT id_voluntario FROM inserted)) +
                            (SELECT COUNT(*) FROM inserted WHERE id_actividad = a.id_actividad AND estado_solicitud = 'Aceptada')
                        ) > a.cupo_maximo
                    )
                    BEGIN
                        RAISERROR ('ERROR NEGOCIO: Cupo excedido al aceptar.', 16, 1);
                        ROLLBACK TRANSACTION;
                        RETURN;
                    END
                END
            END
        ");

        // =========================================================================
        // 4. VISTAS (CORREGIDAS: SIN img_perfil)
        // =========================================================================

        $this->addSql("
            CREATE OR ALTER VIEW VW_Usuarios_Activos AS
            SELECT u.*, r.nombre_rol FROM USUARIO u INNER JOIN ROL r ON u.id_rol = r.id_rol WHERE u.deleted_at IS NULL
        ");


        $this->addSql("
            CREATE OR ALTER VIEW VW_Voluntarios_Activos AS
            SELECT u.id_usuario, u.correo, u.estado_cuenta, u.fecha_registro, v.nombre, v.apellidos, v.telefono, v.fecha_nac, v.carnet_conducir, 
                   c.nombre_curso, c.abreviacion_curso, c.grado, c.nivel
            FROM USUARIO u INNER JOIN VOLUNTARIO v ON u.id_usuario = v.id_usuario LEFT JOIN CURSO c ON v.id_curso_actual = c.id_curso WHERE u.deleted_at IS NULL
        ");


        $this->addSql("
            CREATE OR ALTER VIEW VW_Organizaciones_Activas AS
            SELECT u.id_usuario, u.correo, u.estado_cuenta, u.fecha_registro, o.cif, o.nombre, o.descripcion, o.direccion, o.sitio_web, o.telefono
            FROM USUARIO u INNER JOIN ORGANIZACION o ON u.id_usuario = o.id_usuario WHERE u.deleted_at IS NULL
        ");


        $this->addSql("
            CREATE OR ALTER VIEW VW_Actividades_Activas AS
            SELECT a.*, o.nombre AS nombre_organizacion, 
                   (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados
            FROM ACTIVIDAD a 
            INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario 
            INNER JOIN USUARIO u ON o.id_usuario = u.id_usuario
            WHERE a.deleted_at IS NULL
        ");


        $this->addSql("
            CREATE OR ALTER VIEW VW_Actividades_Publicadas AS
            SELECT a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas, a.cupo_maximo, a.ubicacion, a.estado_publicacion, o.nombre AS nombre_organizacion, 
                   (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados
                   -- , (SELECT TOP 1 url_imagen FROM IMAGEN_ACTIVIDAD img WHERE img.id_actividad = a.id_actividad) AS imagen_principal
            FROM ACTIVIDAD a 
            INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario 
            INNER JOIN USUARIO u ON o.id_usuario = u.id_usuario
            WHERE a.deleted_at IS NULL AND a.estado_publicacion = 'Publicada' AND u.deleted_at IS NULL
        ");

        // =========================================================================
        // 5. PROCEDIMIENTOS ALMACENADOS
        // =========================================================================

        $this->addSql("CREATE OR ALTER PROCEDURE SP_SoftDelete_Usuario @id_usuario INT AS BEGIN SET NOCOUNT ON; UPDATE USUARIO SET deleted_at = GETDATE(), estado_cuenta = 'Bloqueada' WHERE id_usuario = @id_usuario; END");

        $this->addSql("CREATE OR ALTER PROCEDURE SP_SoftDelete_Actividad @id_actividad INT AS BEGIN SET NOCOUNT ON; UPDATE ACTIVIDAD SET deleted_at = GETDATE(), estado_publicacion = 'Cancelada' WHERE id_actividad = @id_actividad; END");

        $this->addSql("CREATE OR ALTER PROCEDURE SP_Restore_Usuario @id_usuario INT AS BEGIN SET NOCOUNT ON; UPDATE USUARIO SET deleted_at = NULL, estado_cuenta = 'Pendiente' WHERE id_usuario = @id_usuario; END");

        $this->addSql("
            CREATE OR ALTER PROCEDURE SP_Dashboard_Stats AS
            BEGIN
                SET NOCOUNT ON;
                SELECT
                    (SELECT COUNT(*) FROM VW_Voluntarios_Activos WHERE estado_cuenta = 'Activa') AS voluntarios_activos,
                    (SELECT COUNT(*) FROM VW_Voluntarios_Activos WHERE estado_cuenta = 'Pendiente') AS voluntarios_pendientes,
                    (SELECT COUNT(*) FROM VW_Organizaciones_Activas WHERE estado_cuenta = 'Activa') AS organizaciones_activas,
                    (SELECT COUNT(*) FROM VW_Actividades_Activas WHERE estado_publicacion = 'Publicada') AS actividades_publicadas,
                    (SELECT COUNT(*) FROM VW_Actividades_Activas WHERE estado_publicacion = 'En revision') AS actividades_pendientes,
                    (SELECT COUNT(*) FROM INSCRIPCION WHERE estado_solicitud = 'Pendiente') AS inscripciones_pendientes;
            END
        ");

        $this->addSql("
            CREATE OR ALTER PROCEDURE SP_Get_Recomendaciones_Voluntario @id_voluntario INT AS
            BEGIN
                SET NOCOUNT ON;
                IF NOT EXISTS (SELECT 1 FROM VOLUNTARIO WHERE id_usuario = @id_voluntario) BEGIN PRINT 'Voluntario no valido'; RETURN; END

                SELECT DISTINCT a.titulo, o.nombre AS organizacion, a.fecha_inicio, ods.nombre AS causa_ods
                FROM ACTIVIDAD a
                INNER JOIN ACTIVIDAD_ODS ao ON a.id_actividad = ao.id_actividad 
                INNER JOIN ODS ods ON ao.id_ods = ods.id_ods 
                INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario 
                WHERE ao.id_ods IN (
                    SELECT id_tipo FROM PREFERENCIA_VOLUNTARIO WHERE id_voluntario = @id_voluntario
                )
                AND a.estado_publicacion = 'Publicada'
                AND a.cupo_maximo > (SELECT COUNT(*) FROM INSCRIPCION WHERE id_actividad = a.id_actividad AND estado_solicitud = 'Aceptada');
            END
        ");
    }

    public function down(Schema $schema): void
    {
        // En caso de querer deshacer, borramos todo
        $this->addSql("DROP VIEW IF EXISTS VW_Usuarios_Activos");
        $this->addSql("DROP VIEW IF EXISTS VW_Voluntarios_Activos");
        $this->addSql("DROP VIEW IF EXISTS VW_Organizaciones_Activas");
        $this->addSql("DROP VIEW IF EXISTS VW_Actividades_Activas");
        $this->addSql("DROP VIEW IF EXISTS VW_Actividades_Publicadas");

        $this->addSql("DROP PROCEDURE IF EXISTS SP_SoftDelete_Usuario");
        $this->addSql("DROP PROCEDURE IF EXISTS SP_SoftDelete_Actividad");
        $this->addSql("DROP PROCEDURE IF EXISTS SP_Restore_Usuario");
        $this->addSql("DROP PROCEDURE IF EXISTS SP_Dashboard_Stats");
        $this->addSql("DROP PROCEDURE IF EXISTS SP_Get_Recomendaciones_Voluntario");

        $this->addSql("DROP TRIGGER IF EXISTS TR_Usuario_UpdatedAt");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Voluntario_UpdatedAt");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Organizacion_UpdatedAt");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Actividad_UpdatedAt");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Inscripcion_UpdatedAt");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Protect_Usuario_Delete");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Check_Cupo_Actividad");
        $this->addSql("DROP TRIGGER IF EXISTS TR_Check_Cupo_Update");
    }
}
