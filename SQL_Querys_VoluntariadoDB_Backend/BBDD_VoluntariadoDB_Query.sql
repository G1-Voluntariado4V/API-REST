/* =========================================================================
   PROYECTO: GESTIÓN DE VOLUNTARIADO
   MOTOR: MICROSOFT SQL SERVER
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
    CONSTRAINT CK_Grado_Curso CHECK (grado IN ('Grado Superior', 'Grado Medio', 'Grado Básico')),
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
-- USUARIOS Y SUS PERFILES (EXTIENDEN DE LA TABLA USUARIO)
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
    CONSTRAINT CK_Estado_Usuario CHECK (estado_cuenta IN ('Pendiente', 'Activa', 'Rechazada', 'Bloqueada', 'Inactiva')),
    CONSTRAINT FK_Usuario_Rol FOREIGN KEY (id_rol) REFERENCES ROL(id_rol)
);

CREATE TABLE VOLUNTARIO (
    id_usuario INT PRIMARY KEY,
	dni NVARCHAR(9) UNIQUE,
    nombre NVARCHAR(50) NOT NULL,
    apellidos NVARCHAR(100) NOT NULL,
    telefono NVARCHAR(20),
    CONSTRAINT CK_Tlf_Voluntario CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    fecha_nac DATE,
    carnet_conducir BIT DEFAULT 0,
    img_perfil NVARCHAR(255),
    id_curso_actual INT,
    updated_at DATETIME NULL,
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
    CONSTRAINT CK_Tlf_Organizacion CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    img_perfil NVARCHAR(255),
    updated_at DATETIME NULL,
    CONSTRAINT FK_Org_Usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario) ON DELETE CASCADE
);

CREATE TABLE COORDINADOR (
    id_usuario INT PRIMARY KEY,
    nombre NVARCHAR(50),
    apellidos NVARCHAR(100),
    telefono NVARCHAR(20),
    CONSTRAINT CK_Tlf_Coordinador CHECK (telefono NOT LIKE '%[^0-9+ ]%'),
    updated_at DATETIME NULL,
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
-- GESTIÓN DE ACTIVIDADES E INSCRIPCIONES
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
    CONSTRAINT CK_Estado_Act CHECK (estado_publicacion IN ('En revision', 'Publicada', 'Cancelada', 'Finalizada')),
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

    CONSTRAINT CK_Estado_Insc CHECK (estado_solicitud IN ('Pendiente', 'Aceptada', 'Rechazada', 'Cancelada', 'Finalizada')),
    PRIMARY KEY (id_voluntario, id_actividad),

    CONSTRAINT FK_Insc_Vol FOREIGN KEY (id_voluntario) REFERENCES VOLUNTARIO(id_usuario) ON DELETE CASCADE,
    CONSTRAINT FK_Insc_Act FOREIGN KEY (id_actividad) REFERENCES ACTIVIDAD(id_actividad)
);

-- =========================================================================
-- ÍNDICES
-- =========================================================================

/*
ÍNDICES DE RENDIMIENTO
OBJETIVO: Acelerar las búsquedas y los JOINS que son más comunes. 
Como las claves foráneas no se indexan solas en SQL Server creamos estos índices para que las vistas vayan rápido.
*/
-- Buscar todas las actividades de una organización específica.
CREATE INDEX IX_Actividad_Organizacion ON ACTIVIDAD(id_organizacion);

-- Buscar el historial de inscripciones de un voluntario.
CREATE INDEX IX_Inscripcion_Voluntario ON INSCRIPCION(id_voluntario);

-- Ver quién está apuntado a una actividad concreta.
CREATE INDEX IX_Inscripcion_Actividad ON INSCRIPCION(id_actividad);

-- Filtrar usuarios eliminados (Soft Delete) para no leer basura.
CREATE INDEX IX_Usuario_DeletedAt ON USUARIO(deleted_at);
CREATE INDEX IX_Actividad_DeletedAt ON ACTIVIDAD(deleted_at);

/*
ÍNDICES FILTRADOS
OBJETIVO: Índices más pequeños y rápidos que solo contienen datos "Activos".
VENTAJA: Ahorran espacio y hacen las consultas de usuarios activos instantáneas.
*/

-- Optimiza búsquedas por estado ignorando borrados (ej: "Ver todos los que estan en Pendientes").
CREATE INDEX IX_Usuario_Estado 
ON USUARIO(estado_cuenta) 
WHERE deleted_at IS NULL;

-- Optimiza la vista principal de la aplicación (buscar actividades publicadas futuras).
-- Es un índice compuesto ya que usa dos columnas a la vez.
CREATE INDEX IX_Actividad_Estado_Fecha 
ON ACTIVIDAD(estado_publicacion, fecha_inicio) 
WHERE deleted_at IS NULL;

GO

/*
RESTRICCIONES DE UNICIDAD CONDICIONAL (Business Logic)
OBJETIVO: Garantizar datos únicos siendo compatible con soft delete.
LÓGICA: 
    - Normalmente usar un UNIQUE impediría registrar de nuevo un email que ya se ha borrado lógicamente.
    - Con el filtro "WHERE deleted_at IS NULL" permitimos que existan duplicados en la 'papelera' pero no entre los usuarios activos.
*/

-- Garantiza correos únicos solo entre usuarios activos.
CREATE UNIQUE INDEX UX_Usuario_Correo_Activo
ON USUARIO(correo)
WHERE deleted_at IS NULL;

-- Garantiza que no se pueda vincular la misma cuenta de Google dos veces activas.
CREATE UNIQUE INDEX UX_Usuario_GoogleId_Activo
ON USUARIO(google_id)
WHERE deleted_at IS NULL;
GO

-- =========================================================================
-- TRIGGERS
-- =========================================================================

/*
GRUPO DE TRIGGERS CON MISMO USO: AUTOMATIZACIÓN DE FECHAS (Updated_At)
PROPÓSITO: Mantener el rastro de cuándo se modificó un dato por última vez en las tablas USUARIO, VOLUNTARIO, ORGANIZACION, COORDINADOR, ACTIVIDAD e INSCRIPCION.
CÓMO FUNCIONA:
    - Se dispara DESPUÉS (AFTER) de cualquier UPDATE en la tabla.
    - Une la tabla "inserted" (que contiene los nuevos datos) con la tabla real.
    - Actualiza el campo 'updated_at' con la fecha/hora actual.
*/
GO
CREATE TRIGGER TR_Usuario_UpdatedAt
ON USUARIO AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE USUARIO SET updated_at = GETDATE()
    FROM USUARIO u INNER JOIN inserted i ON u.id_usuario = i.id_usuario;
END
GO

CREATE TRIGGER TR_Voluntario_UpdatedAt
ON VOLUNTARIO AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE VOLUNTARIO SET updated_at = GETDATE()
    FROM VOLUNTARIO v INNER JOIN inserted i ON v.id_usuario = i.id_usuario;
END
GO

CREATE TRIGGER TR_Organizacion_UpdatedAt
ON ORGANIZACION AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE ORGANIZACION SET updated_at = GETDATE()
    FROM ORGANIZACION o INNER JOIN inserted i ON o.id_usuario = i.id_usuario;
END
GO

CREATE TRIGGER TR_Coordinador_UpdatedAt
ON COORDINADOR AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE COORDINADOR SET updated_at = GETDATE()
    FROM COORDINADOR c INNER JOIN inserted i ON c.id_usuario = i.id_usuario;
END
GO

CREATE TRIGGER TR_Actividad_UpdatedAt
ON ACTIVIDAD AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE ACTIVIDAD SET updated_at = GETDATE()
    FROM ACTIVIDAD a INNER JOIN inserted i ON a.id_actividad = i.id_actividad;
END
GO

CREATE TRIGGER TR_Inscripcion_UpdatedAt
ON INSCRIPCION AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE INSCRIPCION SET updated_at = GETDATE()
    FROM INSCRIPCION ins
    INNER JOIN inserted i ON ins.id_voluntario = i.id_voluntario
                              AND ins.id_actividad = i.id_actividad;
END
GO

/*
TRIGGER: TR_Check_Cupo_Actividad
TIPO: INSTEAD OF INSERT (Se ejecuta antes de que ocurra el INSERT).
PROPÓSITO: Regla de Negocio - Evitar 'Overbooking'.
LÓGICA:
    1. Verifica si la actividad tiene un límite de cupo (cupo_maximo no es NULL).
    2. Cuenta cuántas personas ya tienen estado 'Aceptada' en esa actividad.
    3. Si se ha llegado o superado el cupo máximo se lanza un error al usuario (RAISERROR) y se cancela la operación (ROLLBACK).
    4. Si no, realiza la inserción que habíamos interceptado.
*/
GO
CREATE TRIGGER TR_Check_Cupo_Actividad
ON INSCRIPCION
INSTEAD OF INSERT
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM inserted i
        JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
        WHERE a.cupo_maximo IS NOT NULL
          AND (SELECT COUNT(*) FROM INSCRIPCION ins
               WHERE ins.id_actividad = a.id_actividad
               AND ins.estado_solicitud = 'Aceptada') >= a.cupo_maximo
    )
    BEGIN
        RAISERROR ('ERROR DE NEGOCIO: No se puede realizar la inscripción. El cupo de la actividad está completo.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END

    INSERT INTO INSCRIPCION (id_voluntario, id_actividad, fecha_solicitud, estado_solicitud)
    SELECT id_voluntario, id_actividad, GETDATE(), estado_solicitud FROM inserted;
END
GO

-- =========================================================================
-- VISTAS
-- =========================================================================

/*
VISTA: VW_Usuarios_Activos
PROPÓSITO: Nivel base de seguridad y autenticación para el Login y validaciones del sistema entre otros usos
QUÉ HACE:
    1. Obtiene los datos de login (usuario genérico).
    2. Traduce el 'id_rol' numérico al nombre real.
    3. FILTRO CRÍTICO: Excluye a cualquiera que tenga fecha en deleted_at.
*/
CREATE VIEW VW_Usuarios_Activos AS
SELECT
    u.*,
    r.nombre_rol
FROM USUARIO u
INNER JOIN ROL r ON u.id_rol = r.id_rol
WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Voluntarios_Activos
PROPÓSITO: Obtener el perfil completo de los voluntarios activos sin JOINS manuales. Nos sirve para mostrar la lista de voluntarios activos o el perfil "Mi Cuenta" entre otras cosas.
QUÉ HACE:
    1. Combina la tabla padre (USUARIO) con la hija (VOLUNTARIO).
    2. Trae la información académica (CURSO) si el voluntario está estudiando.
    3. Solo muestra usuarios activos.
*/
CREATE VIEW VW_Voluntarios_Activos AS
SELECT
    u.id_usuario,
    u.correo,
    u.estado_cuenta,
    u.fecha_registro,
    v.nombre,
    v.apellidos,
    v.telefono,
    v.fecha_nac,
    v.carnet_conducir,
    v.img_perfil,
    c.nombre_curso,
    c.abreviacion_curso,
    c.grado,
    c.nivel
FROM USUARIO u
INNER JOIN VOLUNTARIO v ON u.id_usuario = v.id_usuario
LEFT JOIN CURSO c ON v.id_curso_actual = c.id_curso
WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Organizaciones_Activas
PROPÓSITO: Obtener el perfil completo de las organizaciones activas sin JOINS manuales. Nos sirve para mostrar las organizaciones activas o información de contacto entre otras cosas.
QUÉ HACE:
    1. Combina la tabla padre (USUARIO) con la hija (ORGANIZACION).
    2. Muestra datos públicos como web, dirección y CIF.
    3. Solo muestra organizaciones activas.
*/
CREATE VIEW VW_Organizaciones_Activas AS
SELECT
    u.id_usuario,
    u.correo,
    u.estado_cuenta,
    u.fecha_registro,
    o.cif,
    o.nombre,
    o.descripcion,
    o.direccion,
    o.sitio_web,
    o.telefono,
    o.img_perfil
FROM USUARIO u
INNER JOIN ORGANIZACION o ON u.id_usuario = o.id_usuario
WHERE u.deleted_at IS NULL;
GO

/*
VISTA: VW_Actividades_Activas
PROPÓSITO: Gestión interna de actividades (Panel de Administración). Para que los coordinadores puedan ver el estado real de cupos y gestión.
QUÉ HACE:
    1. Lista todas las actividades que NO han sido borradas (soft delete).
    2. Incluye el nombre y logo de la organización dueña.
    3. Cuenta automáticamente cuántas inscripciones están en estado 'Aceptado'.
*/
CREATE VIEW VW_Actividades_Activas AS
SELECT
    a.*,
    o.nombre AS nombre_organizacion,
    o.img_perfil AS img_organizacion,
    -- Subconsulta para contar inscritos reales
    (SELECT COUNT(*) FROM INSCRIPCION i
     WHERE i.id_actividad = a.id_actividad
     AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados
FROM ACTIVIDAD a
INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario
WHERE a.deleted_at IS NULL;
GO

/*
VISTA: VW_Actividades_Publicadas
PROPÓSITO: Obtener un escaparate público (lo que ven los voluntarios en la Aplicación Web). Para mostrar el listado principal de la página web o móvil.
QUÉ HACE:
    1. Aplica estos filtros: Actividad no borrada, estado 'Publicada', y que la organización dueña tampoco esté borrada.
    2. Trae la primera imagen disponible de la actividad para usarla de portada.
    3. Incluye el contador de inscritos para mostrar plazas ocupadas.
*/
CREATE VIEW VW_Actividades_Publicadas AS
SELECT
    a.id_actividad,
    a.titulo,
    a.descripcion,
    a.fecha_inicio,
    a.duracion_horas,
    a.cupo_maximo,
    a.ubicacion,
    a.estado_publicacion,
    o.nombre AS nombre_organizacion,
    o.img_perfil AS img_organizacion,
    (SELECT COUNT(*) FROM INSCRIPCION i
     WHERE i.id_actividad = a.id_actividad
     AND i.estado_solicitud = 'Aceptada') AS inscritos_confirmados,
    (SELECT TOP 1 url_imagen FROM IMAGEN_ACTIVIDAD img
     WHERE img.id_actividad = a.id_actividad) AS imagen_principal
FROM ACTIVIDAD a
INNER JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario
INNER JOIN USUARIO u ON o.id_usuario = u.id_usuario
WHERE a.deleted_at IS NULL
  AND a.estado_publicacion = 'Publicada'
  AND u.deleted_at IS NULL;
GO

-- =========================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =========================================================================

/*
PROCEDIMIENTO: SP_SoftDelete_Usuario
OBJETIVO: Realizar un "Borrado Lógico" de un usuario sin utilizar DELETE para poder mantenerlo como historial.
LÓGICA:
    1. Recibe el ID del usuario.
    2. No elimina la fila.
    3. Marca 'deleted_at' con la fecha actual.
    4. Cambia el estado de la cuenta a 'Bloqueada' para impedir acceso inmediato.
*/
CREATE PROCEDURE SP_SoftDelete_Usuario
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE USUARIO
    SET deleted_at = GETDATE(),
        estado_cuenta = 'Bloqueada'
    WHERE id_usuario = @id_usuario;

    IF @@ROWCOUNT > 0
        PRINT 'Usuario marcado como eliminado';
    ELSE
        PRINT 'Usuario no encontrado';
END
GO

/*
PROCEDIMIENTO: SP_SoftDelete_Actividad
OBJETIVO: Cancelar y ocultar una actividad sin borrar su historial (no usamos DELETE).
LÓGICA:
    1. Marca la fecha de borrado.
    2. Fuerza el estado de la actividad a 'Cancelada' para que las vistas la filtren.
*/
CREATE PROCEDURE SP_SoftDelete_Actividad
    @id_actividad INT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE ACTIVIDAD
    SET deleted_at = GETDATE(),
        estado_publicacion = 'Cancelada'
    WHERE id_actividad = @id_actividad;

    IF @@ROWCOUNT > 0
        PRINT 'Actividad marcada como eliminada';
    ELSE
        PRINT 'Actividad no encontrada';
END
GO

/*
PROCEDIMIENTO: SP_Restore_Usuario
OBJETIVO: Reactivar un usuario previamente eliminado (Deshacer).
LÓGICA:
    1. Limpia la fecha 'deleted_at' (la pone a NULL).
    2. Restablece el estado a 'Pendiente' (por seguridad ponemos Pendiente para revisar de nuevo aunque se podría activar la cuenta directamente).
*/
CREATE PROCEDURE SP_Restore_Usuario
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE USUARIO
    SET deleted_at = NULL,
        estado_cuenta = 'Pendiente'
    WHERE id_usuario = @id_usuario;

    IF @@ROWCOUNT > 0
        PRINT 'Usuario restaurado';
    ELSE
        PRINT 'Usuario no encontrado';
END
GO

/*
PROCEDIMIENTO: SP_Dashboard_Stats
OBJETIVO: Obtener métricas globales para el Panel de Administración.
LÓGICA:
    - Realiza varias subconsultas para devolver una sola fila con todos los contadores.
    - Aprovecha las VISTAS creadas anteriormente para asegurar consistencia.
*/
CREATE PROCEDURE SP_Dashboard_Stats
AS
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