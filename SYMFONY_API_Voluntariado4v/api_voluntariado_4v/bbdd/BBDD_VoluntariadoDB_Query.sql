/* =========================================================================
   PROYECTO: GESTI�N DE VOLUNTARIADO
   MOTOR: MICROSOFT SQL SERVER
   VERSI�N: FINAL (CORREGIDA Y DOCUMENTADA)
   =========================================================================
*/

USE master;
GO

IF EXISTS (SELECT name FROM sys.databases WHERE name = 'VoluntariadoDB')
BEGIN
    ALTER DATABASE VoluntariadoDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE VoluntariadoDB;
END
GO

CREATE DATABASE VoluntariadoDB;
GO
USE VoluntariadoDB;
GO

-- =========================================================================
-- TABLAS GENERALES
-- =========================================================================

CREATE TABLE ROL (
    id_rol INT IDENTITY(1,1) PRIMARY KEY,
    nombre_rol NVARCHAR(50) NOT NULL
);

CREATE TABLE CURSO (
    id_curso INT IDENTITY(1,1) PRIMARY KEY,
    nombre_curso NVARCHAR(100) NOT NULL,
    abreviacion_curso NVARCHAR(10) NOT NULL,
    grado NVARCHAR(50) NOT NULL,
    CONSTRAINT CK_Grado_Curso CHECK (grado IN ('Grado Superior', 'Grado Medio', 'Grado B�sico')),
    nivel INT NOT NULL,
    CONSTRAINT CK_Nivel_Curso CHECK (nivel IN (1, 2))
);

CREATE TABLE IDIOMA (
    id_idioma INT IDENTITY(1,1) PRIMARY KEY,
    nombre_idioma NVARCHAR(50) NOT NULL,
    codigo_iso NVARCHAR(5)
);

CREATE TABLE ODS (
    id_ods INT PRIMARY KEY,
    nombre NVARCHAR(150) NOT NULL,
    descripcion NVARCHAR(MAX)
);

CREATE TABLE TIPO_VOLUNTARIADO (
    id_tipo INT IDENTITY(1,1) PRIMARY KEY,
    nombre_tipo NVARCHAR(100) NOT NULL
);

-- =========================================================================
-- USUARIOS Y SUS PERFILES
-- =========================================================================

CREATE TABLE USUARIO (
    id_usuario INT IDENTITY(1,1) PRIMARY KEY,
    correo NVARCHAR(100) NOT NULL,
    google_id NVARCHAR(255) NOT NULL,
    refresh_token NVARCHAR(500) NULL,
    id_rol INT NOT NULL,
    fecha_registro DATETIME DEFAULT GETDATE(),
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    estado_cuenta NVARCHAR(20) DEFAULT 'Pendiente',
    
    -- Restricciones de Estado
    CONSTRAINT CK_Estado_Usuario CHECK (estado_cuenta IN ('Pendiente', 'Activa', 'Rechazada', 'Bloqueada', 'Inactiva')),
    CONSTRAINT FK_Usuario_Rol FOREIGN KEY (id_rol) REFERENCES ROL(id_rol)
);

CREATE TABLE VOLUNTARIO (
    id_usuario INT PRIMARY KEY,
    dni NVARCHAR(9) UNIQUE,
    nombre NVARCHAR(50) NOT NULL,
    apellidos NVARCHAR(100) NOT NULL,
    telefono NVARCHAR(20),
    fecha_nac DATE,
    carnet_conducir BIT DEFAULT 0,
    img_perfil NVARCHAR(255),
    id_curso_actual INT,
    updated_at DATETIME NULL,
    
    -- Restricciones de Formato
    CONSTRAINT CK_Tlf_Voluntario CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    
    /* RESTRICCI�N DE NEGOCIO (QA): Evitar viajes en el tiempo.
       Un voluntario no puede haber nacido en el futuro. */
    CONSTRAINT CK_FechaNac_Valida CHECK (fecha_nac < GETDATE()),
    
    -- Claves For�neas
    CONSTRAINT FK_Vol_Usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario) ON DELETE CASCADE,
    CONSTRAINT FK_Vol_Curso FOREIGN KEY (id_curso_actual) REFERENCES CURSO(id_curso)
);

CREATE TABLE ORGANIZACION (
    id_usuario INT PRIMARY KEY,
    cif NVARCHAR(20) UNIQUE,
    nombre NVARCHAR(100),
    descripcion NVARCHAR(MAX),
    direccion NVARCHAR(MAX),
    sitio_web NVARCHAR(200),
    telefono NVARCHAR(20),
    img_perfil NVARCHAR(255),
    updated_at DATETIME NULL,
    
    CONSTRAINT CK_Tlf_Organizacion CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    CONSTRAINT FK_Org_Usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario) ON DELETE CASCADE
);

CREATE TABLE COORDINADOR (
    id_usuario INT PRIMARY KEY,
    nombre NVARCHAR(50),
    apellidos NVARCHAR(100),
    telefono NVARCHAR(20),
    updated_at DATETIME NULL,
    
    CONSTRAINT CK_Tlf_Coordinador CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    CONSTRAINT FK_Coord_Usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario) ON DELETE CASCADE
);

-- =========================================================================
-- TABLAS INTERMEDIAS
-- =========================================================================

CREATE TABLE VOLUNTARIO_IDIOMA (
    id_voluntario INT,
    id_idioma INT,
    nivel NVARCHAR(20),
    
    PRIMARY KEY (id_voluntario, id_idioma),
    
    /* RESTRICCI�N DE NEGOCIO (QA): Estandarizaci�n.
       Asegurar que los niveles de idioma sigan el Marco Com�n Europeo. */
    CONSTRAINT CK_Nivel_Idioma CHECK (nivel IN ('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Nativo')),
    
    CONSTRAINT FK_VI_Vol FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO(id_usuario) ON DELETE CASCADE,
    CONSTRAINT FK_VI_Idioma FOREIGN KEY (id_idioma) REFERENCES IDIOMA(id_idioma)
);

CREATE TABLE PREFERENCIA_VOLUNTARIO (
    id_voluntario INT,
    id_tipo INT,
    
    PRIMARY KEY (id_voluntario, id_tipo),
    
    CONSTRAINT FK_PV_Vol FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO(id_usuario) ON DELETE CASCADE,
    CONSTRAINT FK_PV_Tipo FOREIGN KEY (id_tipo) REFERENCES TIPO_VOLUNTARIADO(id_tipo)
);

-- =========================================================================
-- GESTI�N DE ACTIVIDADES E INSCRIPCIONES
-- =========================================================================

CREATE TABLE ACTIVIDAD (
    id_actividad INT IDENTITY(1,1) PRIMARY KEY,
    id_organizacion INT NOT NULL,
    titulo NVARCHAR(150) NOT NULL,
    descripcion NVARCHAR(MAX),
    fecha_inicio DATETIME,
    duracion_horas INT,
    cupo_maximo INT,
    ubicacion NVARCHAR(MAX),
    estado_publicacion NVARCHAR(20) DEFAULT 'En revision',
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    
    -- Restricciones de Estado
    CONSTRAINT CK_Estado_Act CHECK (estado_publicacion IN ('En revision', 'Publicada', 'Cancelada', 'Finalizada')),
    
    /* RESTRICCIONES DE NEGOCIO (QA): Consistencia de Datos.
       1. Evitar duraciones negativas o cero.
       2. Evitar cupos negativos (si hay cupo, debe ser > 0). */
    CONSTRAINT CK_Duracion_Positiva CHECK (duracion_horas > 0),
    CONSTRAINT CK_Cupo_Positivo CHECK (cupo_maximo > 0),
    
    -- Claves For�neas
    CONSTRAINT FK_Act_Org FOREIGN KEY (id_organizacion) REFERENCES ORGANIZACION(id_usuario) ON DELETE CASCADE
);

CREATE TABLE IMAGEN_ACTIVIDAD (
    id_imagen INT IDENTITY(1,1) PRIMARY KEY,
    id_actividad INT NOT NULL,
    url_imagen NVARCHAR(255) NOT NULL,
    descripcion_pie_foto NVARCHAR(255),
    
    CONSTRAINT FK_Img_Act FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD(id_actividad) ON DELETE CASCADE
);

CREATE TABLE ACTIVIDAD_ODS (
    id_actividad INT,
    id_ods INT,
    PRIMARY KEY (id_actividad, id_ods),
    CONSTRAINT FK_AO_Act FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD(id_actividad) ON DELETE CASCADE,
    CONSTRAINT FK_AO_ODS FOREIGN KEY (id_ods) REFERENCES ODS(id_ods)
);

CREATE TABLE ACTIVIDAD_TIPO (
    id_actividad INT,
    id_tipo INT,
    PRIMARY KEY (id_actividad, id_tipo),
    CONSTRAINT FK_AT_Act FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD(id_actividad) ON DELETE CASCADE,
    CONSTRAINT FK_AT_Tipo FOREIGN KEY (id_tipo) REFERENCES TIPO_VOLUNTARIADO(id_tipo)
);

CREATE TABLE INSCRIPCION (
    id_voluntario INT,
    id_actividad INT,
    fecha_solicitud DATETIME DEFAULT GETDATE(),
    updated_at DATETIME NULL,
    estado_solicitud NVARCHAR(20) DEFAULT 'Pendiente',

    PRIMARY KEY (id_voluntario, id_actividad),

    CONSTRAINT CK_Estado_Insc CHECK (estado_solicitud IN ('Pendiente', 'Aceptada', 'Rechazada', 'Cancelada', 'Finalizada')),
    
    CONSTRAINT FK_Insc_Vol FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO(id_usuario) ON DELETE CASCADE,
    CONSTRAINT FK_Insc_Act FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD(id_actividad)
);
GO

-- =========================================================================
-- �NDICES
-- =========================================================================

/*
�NDICES DE RENDIMIENTO
OBJETIVO: Acelerar las b�squedas y los JOINS m�s comunes.
*/
CREATE INDEX IX_Actividad_Organizacion ON ACTIVIDAD(id_organizacion);
CREATE INDEX IX_Inscripcion_Voluntario ON INSCRIPCION(id_voluntario);
CREATE INDEX IX_Inscripcion_Actividad ON INSCRIPCION(id_actividad);

-- Filtrar usuarios eliminados (Soft Delete) para no leer basura.
CREATE INDEX IX_Usuario_DeletedAt ON USUARIO(deleted_at);
CREATE INDEX IX_Actividad_DeletedAt ON ACTIVIDAD(deleted_at);

/*
�NDICES FILTRADOS
OBJETIVO: �ndices m�s peque�os y r�pidos que solo contienen datos "Activos".
VENTAJA: Ahorran espacio y hacen las consultas de usuarios activos instant�neas.
*/
CREATE INDEX IX_Usuario_Estado ON USUARIO(estado_cuenta) WHERE deleted_at IS NULL;

-- Optimiza la vista principal (buscar actividades publicadas futuras).
CREATE INDEX IX_Actividad_Estado_Fecha ON ACTIVIDAD(estado_publicacion, fecha_inicio) WHERE deleted_at IS NULL;
GO

/*
RESTRICCIONES DE UNICIDAD CONDICIONAL (Business Logic)
OBJETIVO: Garantizar datos �nicos siendo compatible con soft delete.
L�GICA: Permite duplicados en la 'papelera' (usuarios borrados) pero no entre los usuarios activos.
*/
CREATE UNIQUE INDEX UX_Usuario_Correo_Activo ON USUARIO(correo) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX UX_Usuario_GoogleId_Activo ON USUARIO(google_id) WHERE deleted_at IS NULL;
GO

-- =========================================================================
-- TRIGGERS
-- =========================================================================

/*
GRUPO DE TRIGGERS: AUTOMATIZACI�N DE FECHAS (Updated_At)
PROP�SITO: Mantener el rastro de cu�ndo se modific� un dato por �ltima vez.
*/
GO
CREATE TRIGGER TR_Usuario_UpdatedAt 
ON USUARIO AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE USUARIO SET updated_at = GETDATE() FROM USUARIO u INNER JOIN inserted i ON u.id_usuario = i.id_usuario; 
END
GO

CREATE TRIGGER TR_Voluntario_UpdatedAt 
ON VOLUNTARIO AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE VOLUNTARIO SET updated_at = GETDATE() FROM VOLUNTARIO v INNER JOIN inserted i ON v.id_usuario = i.id_usuario; 
END
GO

CREATE TRIGGER TR_Organizacion_UpdatedAt 
ON ORGANIZACION AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE ORGANIZACION SET updated_at = GETDATE() FROM ORGANIZACION o INNER JOIN inserted i ON o.id_usuario = i.id_usuario; 
END
GO

CREATE TRIGGER TR_Coordinador_UpdatedAt 
ON COORDINADOR AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE COORDINADOR SET updated_at = GETDATE() FROM COORDINADOR c INNER JOIN inserted i ON c.id_usuario = i.id_usuario; 
END
GO

CREATE TRIGGER TR_Actividad_UpdatedAt 
ON ACTIVIDAD AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE ACTIVIDAD SET updated_at = GETDATE() FROM ACTIVIDAD a INNER JOIN inserted i ON a.id_actividad = i.id_actividad; 
END
GO

CREATE TRIGGER TR_Inscripcion_UpdatedAt 
ON INSCRIPCION AFTER UPDATE AS 
BEGIN 
    SET NOCOUNT ON; 
    UPDATE INSCRIPCION SET updated_at = GETDATE() FROM INSCRIPCION ins INNER JOIN inserted i ON ins.id_voluntario = i.id_voluntario AND ins.id_actividad = i.id_actividad; 
END
GO

/*
TRIGGER DE SEGURIDAD: TR_Protect_Usuario_Delete
TIPO: INSTEAD OF DELETE
OBJETIVO: Protecci�n contra borrados accidentales (Hard Delete).
L�GICA:
    1. Intercepta el comando DELETE.
    2. En su lugar, realiza un Soft Delete (actualiza deleted_at).
    3. Bloquea la cuenta para impedir accesos.
*/
GO
CREATE TRIGGER TR_Protect_Usuario_Delete
ON USUARIO
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON;
    PRINT '>> ALERTA: Se ha interceptado un intento de borrado f�sico. Transformando en Soft Delete...';
    UPDATE USUARIO
    SET deleted_at = GETDATE(),
        estado_cuenta = 'Bloqueada'
    WHERE id_usuario IN (SELECT id_usuario FROM deleted);
END
GO

/*
TRIGGER MAESTRO: TR_Check_Cupo_Actividad
TIPO: INSTEAD OF INSERT
OBJETIVO: Validar reglas complejas antes de permitir una inscripci�n.
L�GICA:
    1. VALIDACI�N DE FECHA Y ESTADO: Impide inscribirse si la actividad no est� publicada o ya pas�.
    2. VALIDACI�N DE AGENDA: Impide que un voluntario se apunte a dos actividades que ocurren a la misma hora (Solapamiento).
    3. VALIDACI�N DE CUPO: Impide Overbooking si el cupo est� lleno.
*/
GO
CREATE OR ALTER TRIGGER TR_Check_Cupo_Actividad
ON INSCRIPCION
INSTEAD OF INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- 1. VALIDACI�N: La actividad debe estar publicada y vigente
    IF EXISTS (
        SELECT 1 FROM inserted i
        JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
        WHERE (a.estado_publicacion <> 'Publicada') OR (a.fecha_inicio < GETDATE())
    )
    BEGIN
        RAISERROR ('ERROR: No se puede inscribir. La actividad no est� publicada o ya ha finalizado.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END

    -- 2. VALIDACI�N: Agenda (No permitir estar en dos sitios a la vez)
    IF EXISTS (
        SELECT 1
        FROM inserted i_new
        JOIN ACTIVIDAD a_new ON i_new.id_actividad = a_new.id_actividad
        JOIN INSCRIPCION i_old ON i_new.id_voluntario = i_old.id_voluntario
        JOIN ACTIVIDAD a_old ON i_old.id_actividad = a_old.id_actividad
        WHERE i_old.estado_solicitud IN ('Aceptada', 'Pendiente')
          AND a_new.id_actividad <> a_old.id_actividad
          -- F�rmula matem�tica del choque de horarios
          AND a_new.fecha_inicio < DATEADD(HOUR, a_old.duracion_horas, a_old.fecha_inicio)
          AND DATEADD(HOUR, a_new.duracion_horas, a_new.fecha_inicio) > a_old.fecha_inicio
    )
    BEGIN
        RAISERROR ('ERROR DE AGENDA: Ya tienes otra actividad en ese horario.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END

    -- 3. VALIDACI�N: Cupo (Overbooking en Insert)
    IF EXISTS (
        SELECT 1 FROM inserted i
        JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
        WHERE a.cupo_maximo IS NOT NULL
          AND (SELECT COUNT(*) FROM INSCRIPCION ins
               WHERE ins.id_actividad = a.id_actividad
               AND ins.estado_solicitud = 'Aceptada') >= a.cupo_maximo
    )
    BEGIN
        RAISERROR ('ERROR DE CUPO: La actividad ya est� completa.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END

    -- 4. �XITO
    INSERT INTO INSCRIPCION (id_voluntario, id_actividad, fecha_solicitud, estado_solicitud)
    SELECT id_voluntario, id_actividad, GETDATE(), estado_solicitud FROM inserted;
END
GO

/*
TRIGGER DE CONSISTENCIA: TR_Check_Cupo_Update
TIPO: AFTER UPDATE
OBJETIVO: Evitar que se vulnere el cupo mediante actualizaciones administrativas.
L�GICA:
    - Se dispara cuando alguien cambia el estado de una inscripci�n a 'Aceptada'.
    - Verifica si ese cambio provoca que se supere el cupo m�ximo.
*/
CREATE TRIGGER TR_Check_Cupo_Update
ON INSCRIPCION
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    -- Verificar si estamos cambiando a 'Aceptada' y si eso viola el cupo
    IF EXISTS (
        SELECT 1 FROM inserted i
        INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
        INNER JOIN deleted d ON i.id_voluntario = d.id_voluntario AND i.id_actividad = d.id_actividad
        WHERE i.estado_solicitud = 'Aceptada'
          AND d.estado_solicitud <> 'Aceptada' -- Solo si el estado CAMBIA
          AND a.cupo_maximo IS NOT NULL
    )
    BEGIN
        -- Contamos ocupaci�n total
        IF EXISTS (
            SELECT a.id_actividad
            FROM ACTIVIDAD a
            INNER JOIN inserted i ON a.id_actividad = i.id_actividad
            WHERE a.cupo_maximo IS NOT NULL
            AND (
                (SELECT COUNT(*) FROM INSCRIPCION ins 
                 WHERE ins.id_actividad = a.id_actividad 
                 AND ins.estado_solicitud = 'Aceptada'
                 AND ins.id_voluntario NOT IN (SELECT id_voluntario FROM inserted)) +
                (SELECT COUNT(*) FROM inserted WHERE id_actividad = a.id_actividad AND estado_solicitud = 'Aceptada')
            ) > a.cupo_maximo
        )
        BEGIN
            RAISERROR ('ERROR DE NEGOCIO: No se puede aceptar la inscripci�n. El cupo de la actividad se ha excedido.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
    END
END
GO

-- =========================================================================
-- VISTAS
-- =========================================================================

/*
VISTA: VW_Usuarios_Activos
PROP�SITO: Nivel base de seguridad y autenticaci�n.
FILTRO CR�TICO: Excluye a cualquiera que tenga fecha en deleted_at.
*/
CREATE VIEW VW_Usuarios_Activos AS
SELECT u.*, r.nombre_rol FROM USUARIO u INNER JOIN ROL r ON u.id_rol = r.id_rol WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Voluntarios_Activos
PROP�SITO: Obtener el perfil completo de los voluntarios activos sin JOINS manuales.
*/
CREATE VIEW VW_Voluntarios_Activos AS
SELECT u.id_usuario, u.correo, u.estado_cuenta, u.fecha_registro, v.nombre, v.apellidos, v.telefono, v.fecha_nac, v.carnet_conducir, v.img_perfil, c.nombre_curso, c.abreviacion_curso, c.grado, c.nivel
FROM USUARIO u INNER JOIN VOLUNTARIO v ON u.id_usuario = v.id_usuario LEFT JOIN CURSO c ON v.id_curso_actual = c.id_curso WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Organizaciones_Activas
PROP�SITO: Obtener el perfil completo de las organizaciones activas.
*/
CREATE VIEW VW_Organizaciones_Activas AS
SELECT u.id_usuario, u.correo, u.estado_cuenta, u.fecha_registro, o.cif, o.nombre, o.descripcion, o.direccion, o.sitio_web, o.telefono, o.img_perfil
FROM USUARIO u INNER JOIN ORGANIZACION o ON u.id_usuario = o.id_usuario WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Actividades_Activas
PROP�SITO: Gesti�n interna de actividades (Panel de Administraci�n).
*/
CREATE VIEW VW_Actividades_Activas AS
SELECT a.*, o.nombre AS nombre_organizacion, o.img_perfil AS img_organizacion,
    (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados
FROM ACTIVIDAD a INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario WHERE a.deleted_at IS NULL;
GO

/*
VISTA: VW_Actividades_Publicadas
PROP�SITO: Escaparate p�blico (lo que ven los voluntarios en la Aplicaci�n Web).
FILTROS: Actividad no borrada + Estado 'Publicada' + Organizaci�n activa.
*/
CREATE VIEW VW_Actividades_Publicadas AS
SELECT a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas, a.cupo_maximo, a.ubicacion, a.estado_publicacion, o.nombre AS nombre_organizacion, o.img_perfil AS img_organizacion,
    (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados,
    (SELECT TOP 1 url_imagen FROM IMAGEN_ACTIVIDAD img WHERE img.id_actividad = a.id_actividad) AS imagen_principal
FROM ACTIVIDAD a INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario INNER JOIN USUARIO u ON o.id_usuario = u.id_usuario
WHERE a.deleted_at IS NULL AND a.estado_publicacion = 'Publicada' AND u.deleted_at IS NULL;
GO

-- =========================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =========================================================================

/*
PROCEDIMIENTO: SP_SoftDelete_Usuario
OBJETIVO: Realizar un "Borrado L�gico" de un usuario sin utilizar DELETE.
*/
CREATE PROCEDURE SP_SoftDelete_Usuario @id_usuario INT AS
BEGIN
    SET NOCOUNT ON;
    UPDATE USUARIO SET deleted_at = GETDATE(), estado_cuenta = 'Bloqueada' WHERE id_usuario = @id_usuario;
    IF @@ROWCOUNT > 0 PRINT 'Usuario marcado como eliminado'; ELSE PRINT 'Usuario no encontrado';
END
GO

/*
PROCEDIMIENTO: SP_SoftDelete_Actividad
OBJETIVO: Cancelar y ocultar una actividad sin borrar su historial.
*/
CREATE PROCEDURE SP_SoftDelete_Actividad @id_actividad INT AS
BEGIN
    SET NOCOUNT ON;
    UPDATE ACTIVIDAD SET deleted_at = GETDATE(), estado_publicacion = 'Cancelada' WHERE id_actividad = @id_actividad;
    IF @@ROWCOUNT > 0 PRINT 'Actividad marcada como eliminada'; ELSE PRINT 'Actividad no encontrada';
END
GO

/*
PROCEDIMIENTO: SP_Restore_Usuario
OBJETIVO: Reactivar un usuario previamente eliminado (Deshacer).
*/
CREATE PROCEDURE SP_Restore_Usuario @id_usuario INT AS
BEGIN
    SET NOCOUNT ON;
    UPDATE USUARIO SET deleted_at = NULL, estado_cuenta = 'Pendiente' WHERE id_usuario = @id_usuario;
    IF @@ROWCOUNT > 0 PRINT 'Usuario restaurado'; ELSE PRINT 'Usuario no encontrado';
END
GO

/*
PROCEDIMIENTO: SP_Dashboard_Stats
OBJETIVO: Obtener m�tricas globales para el Panel de Administraci�n.
*/
CREATE PROCEDURE SP_Dashboard_Stats AS
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
GO

/*
PROCEDIMIENTO: SP_Get_Recomendaciones_Voluntario
OBJETIVO: Encontrar actividades que coincidan con los ODS que le interesan al voluntario.
L�GICA:
    1. Cruza actividades con sus ODS y con los intereses del voluntario.
    2. Filtra solo actividades publicadas y con cupo disponible.
*/
CREATE PROCEDURE SP_Get_Recomendaciones_Voluntario
    @id_voluntario INT
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM VOLUNTARIO WHERE id_usuario = @id_voluntario) BEGIN
        PRINT 'El usuario no es un voluntario v�lido.';
        RETURN;
    END

    SELECT DISTINCT
        a.titulo,
        o.nombre AS organizacion,
        a.fecha_inicio,
        ods.nombre AS causa_ods
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