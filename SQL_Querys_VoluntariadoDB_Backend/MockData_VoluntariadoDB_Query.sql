USE VoluntariadoDB;
GO

-- =========================================================================
-- CARGA DE DATOS MAESTROS (Catálogos)
-- =========================================================================

-- ROLES
INSERT INTO ROL (nombre_rol) VALUES 
('Administrador'), ('Voluntario'), ('Organizacion'), ('Coordinador');

-- IDIOMAS
INSERT INTO IDIOMA (nombre_idioma, codigo_iso) VALUES 
('Español', 'ES'), ('Inglés', 'EN'), ('Francés', 'FR'), ('Alemán', 'DE');

-- TIPOS DE VOLUNTARIADO
INSERT INTO TIPO_VOLUNTARIADO (nombre_tipo) VALUES 
('Medioambiente'), ('Acción Social'), ('Educación'), ('Protección Animal'), ('Salud');

-- ODS (Objetivos de Desarrollo Sostenible - Ejemplo parcial)
INSERT INTO ODS (id_ods, nombre, descripcion) VALUES 
(1, 'Fin de la Pobreza', 'Poner fin a la pobreza en todas sus formas en todo el mundo.'),
(2, 'Hambre Cero', 'Poner fin al hambre.'),
(4, 'Educación de Calidad', 'Garantizar una educación inclusiva, equitativa y de calidad.'),
(13, 'Acción por el Clima', 'Adoptar medidas urgentes para combatir el cambio climático.');

-- CURSOS (Ejemplos FP)
INSERT INTO CURSO (nombre_curso, abreviacion_curso, grado, nivel) VALUES 
('Desarrollo de Aplicaciones Web', 'DAW', 'Grado Superior', 2),
('Administración y Finanzas', 'ADFIN', 'Grado Superior', 2),
('Cuidados Auxiliares de Enfermería', 'CAE', 'Grado Medio', 1);

-- =========================================================================
-- CREACIÓN DE USUARIOS Y PERFILES
-- Usamos variables para capturar el ID generado automáticamente
-- =========================================================================

DECLARE @idUser INT; -- Variable auxiliar para guardar el ID recién creado

-- ORGANIZACIÓN: "EcoPlanet"
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('contacto@ecoplanet.org', 'google_org_001', 3, 'Activa'); -- Rol 3 = Organizacion

SET @idUser = SCOPE_IDENTITY(); -- Captura el ID del usuario insertado arriba

INSERT INTO ORGANIZACION (id_usuario, cif, nombre, descripcion, direccion, sitio_web, telefono)
VALUES (@idUser, 'G12345678', 'EcoPlanet', 'ONG dedicada a la reforestación y limpieza de playas.', 'Calle Verde 123, Madrid', 'www.ecoplanet.org', '910000001');

DECLARE @idOrgEco INT = @idUser; -- Guardamos este ID para crear actividades luego


-- COORDINADORA: "Iryna Kovalenko"
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('iryna.admin@voluntariado.com', 'google_coord_iryna', 4, 'Activa'); -- Rol 4 = Coordinador

SET @idUser = SCOPE_IDENTITY();

INSERT INTO COORDINADOR (id_usuario, nombre, apellidos, telefono)
VALUES (@idUser, 'Iryna', 'Kovalenko', '611223344');

-- VOLUNTARIO: "Juan Pérez" (Estudiante de DAW)
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('juan.perez@email.com', 'google_vol_001', 2, 'Activa'); -- Rol 2 = Voluntario

SET @idUser = SCOPE_IDENTITY();

INSERT INTO VOLUNTARIO (id_usuario, dni, nombre, apellidos, telefono, fecha_nac, carnet_conducir, id_curso_actual)
VALUES (@idUser, '12345678A', 'Juan', 'Pérez Gómez', '600111222', '2000-05-15', 1, 1); -- Curso 1 = DAW

-- Asignar idioma e intereses a Juan
INSERT INTO VOLUNTARIO_IDIOMA (id_voluntario, id_idioma, nivel) VALUES (@idUser, 2, 'B2'); -- Inglés
INSERT INTO PREFERENCIA_VOLUNTARIO (id_voluntario, id_tipo) VALUES (@idUser, 1); -- Medioambiente
DECLARE @idVolJuan INT = @idUser; -- Guardamos ID


-- VOLUNTARIO: "Laura García" (Estudiante de Enfermería)
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('laura.garcia@email.com', 'google_vol_002', 2, 'Activa');

SET @idUser = SCOPE_IDENTITY();

INSERT INTO VOLUNTARIO (id_usuario, dni, nombre, apellidos, telefono, fecha_nac, carnet_conducir, id_curso_actual)
VALUES (@idUser, '87654321B', 'Laura', 'García López', '600333444', '1998-11-20', 0, 3); -- Curso 3 = Enfermería

INSERT INTO PREFERENCIA_VOLUNTARIO (id_voluntario, id_tipo) VALUES (@idUser, 5); -- Salud
DECLARE @idVolLaura INT = @idUser; -- Guardamos ID

-- =========================================================================
-- GESTIÓN DE ACTIVIDADES
-- =========================================================================

-- ACTIVIDAD 1: Limpieza de Playa (publicada y con cupo)
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, duracion_horas, cupo_maximo, ubicacion, estado_publicacion)
VALUES (@idOrgEco, 'Limpieza de Playa Norte', 'Recogida de plásticos en la costa.', DATEADD(day, 7, GETDATE()), 4, 20, 'Playa Norte, Valencia', 'Publicada');

DECLARE @idActividad1 INT = SCOPE_IDENTITY();

-- Vincular ODS a la actividad ('Acción por el clima')
INSERT INTO ACTIVIDAD_ODS (id_actividad, id_ods) VALUES (@idActividad1, 13);
-- Vincular Tipo
INSERT INTO ACTIVIDAD_TIPO (id_actividad, id_tipo) VALUES (@idActividad1, 1); -- Medioambiente


-- ACTIVIDAD 2: Taller de Reciclaje ('En revisión')
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, duracion_horas, cupo_maximo, ubicacion, estado_publicacion)
VALUES (@idOrgEco, 'Taller de Reciclaje Creativo', 'Aprende a reutilizar materiales.', DATEADD(day, 14, GETDATE()), 2, 10, 'Centro Cívico', 'En revision');


-- ACTIVIDAD 3: Reforestación ('Cancelada')
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, estado_publicacion, deleted_at)
VALUES (@idOrgEco, 'Reforestación Sierra', 'Evento antiguo cancelado.', GETDATE(), 'Cancelada', GETDATE());

-- =========================================================================
-- INSCRIPCIONES
-- =========================================================================

-- Juan y Ana se inscribe a la limpieza de playa (estado 'Pendiente' por defecto)
INSERT INTO INSCRIPCION (id_voluntario, id_actividad) 
VALUES (@idVolJuan, @idActividad1); -- En este caso Juan tiene el id 3 y la actividad id 1

INSERT INTO INSCRIPCION (id_voluntario, id_actividad) 
VALUES (@idVolLaura, @idActividad1); -- En este caso Laura tiene el id 4 y la actividad id 1

-- Simulamos como el coordinador podrá aceptar la solucitud de inscripción en este caso de Juan a la actividad 1
UPDATE INSCRIPCION
SET estado_solicitud = 'Aceptada'
WHERE id_voluntario = @idVolJuan
  AND id_actividad = @idActividad1;

-- =========================================================================
-- SIMULACIÓN EN LA QUE LA COORDINADORA PUBLICA
-- UNA ACTIVIDAD SOLICITADA POR UNA ORGANIZACIÓN
-- =========================================================================

-- Mostramos las actividades de la vista VW_Actividades_Activas que están 'En revision' (la vista nos muestra todas las actividades que no tienen aplicado el soft delete)
SELECT * FROM VW_Actividades_Activas
WHERE estado_publicacion = 'En revision';

-- ACCIÓN: PUBLICAR EL TALLER DE RECICLAJE
UPDATE ACTIVIDAD
SET estado_publicacion = 'Publicada',
    updated_at = GETDATE() -- El trigger que hemos creado lo haría automáticamente pero es buena práctica ponerlo
WHERE titulo = 'Taller de Reciclaje Creativo';

-- Mostramos la vista VW_Actividades_Publicadas y comprobamos como aparece junto a 'Limpieza de Playa Norte' (esta vista muestra solamente las actividades publicadas)
SELECT * FROM VW_Actividades_Publicadas;