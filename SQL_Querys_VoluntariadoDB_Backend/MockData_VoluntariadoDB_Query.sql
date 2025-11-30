USE VoluntariadoDB;
GO

-- =========================================================================
-- CARGA DE DATOS MAESTROS (Catálogos)
-- =========================================================================

-- ROLES
-- IDs esperados: 1=Admin, 2=Voluntario, 3=Organizacion, 4=Coordinador
INSERT INTO ROL (nombre_rol) VALUES 
('Administrador'), ('Voluntario'), ('Organizacion'), ('Coordinador');

-- IDIOMAS
-- IDs esperados: 1=ES, 2=EN, 3=FR, 4=DE
INSERT INTO IDIOMA (nombre_idioma, codigo_iso) VALUES 
('Español', 'ES'), ('Inglés', 'EN'), ('Francés', 'FR'), ('Alemán', 'DE');

-- TIPOS DE VOLUNTARIADO
INSERT INTO TIPO_VOLUNTARIADO (nombre_tipo) VALUES 
('Medioambiente'), ('Acción Social'), ('Educación'), ('Protección Animal'), ('Salud');

-- ODS (Objetivos de Desarrollo Sostenible)
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
-- Nota: Usamos SCOPE_IDENTITY() para obtener el ID generado automáticamente
-- =========================================================================

DECLARE @idUser INT; -- Variable auxiliar

-- -------------------------------------------------------------------------
-- USUARIO 1: ORGANIZACIÓN "EcoPlanet"
-- -------------------------------------------------------------------------
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('contacto@ecoplanet.org', 'google_org_001', 3, 'Activa'); -- Rol 3

SET @idUser = SCOPE_IDENTITY();

INSERT INTO ORGANIZACION (id_usuario, cif, nombre, descripcion, direccion, sitio_web, telefono)
VALUES (@idUser, 'G12345678', 'EcoPlanet', 'ONG dedicada a la reforestación.', 'Calle Verde 123, Madrid', 'www.ecoplanet.org', '910000001');

DECLARE @idOrgEco INT = @idUser; -- Guardamos ID para crear actividades

-- -------------------------------------------------------------------------
-- USUARIO 2: COORDINADORA "Iryna"
-- -------------------------------------------------------------------------
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('iryna.admin@voluntariado.com', 'google_coord_iryna', 4, 'Activa'); -- Rol 4

SET @idUser = SCOPE_IDENTITY();

INSERT INTO COORDINADOR (id_usuario, nombre, apellidos, telefono)
VALUES (@idUser, 'Iryna', 'Kovalenko', '611223344');

-- -------------------------------------------------------------------------
-- USUARIO 3: VOLUNTARIO "Juan Pérez"
-- -------------------------------------------------------------------------
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('juan.perez@email.com', 'google_vol_001', 2, 'Activa'); -- Rol 2

SET @idUser = SCOPE_IDENTITY();

-- Validación Constraint CK_FechaNac_Valida: La fecha debe ser anterior a hoy.
INSERT INTO VOLUNTARIO (id_usuario, dni, nombre, apellidos, telefono, fecha_nac, carnet_conducir, id_curso_actual)
VALUES (@idUser, '12345678A', 'Juan', 'Pérez Gómez', '600111222', '2000-05-15', 1, 1);

-- Validación Constraint CK_Nivel_Idioma: El nivel 'B2' es válido.
INSERT INTO VOLUNTARIO_IDIOMA (id_voluntario, id_idioma, nivel) VALUES (@idUser, 2, 'B2'); -- Inglés
INSERT INTO PREFERENCIA_VOLUNTARIO (id_voluntario, id_tipo) VALUES (@idUser, 1); -- Medioambiente

DECLARE @idVolJuan INT = @idUser;

-- -------------------------------------------------------------------------
-- USUARIO 4: VOLUNTARIO "Laura García"
-- -------------------------------------------------------------------------
INSERT INTO USUARIO (correo, google_id, id_rol, estado_cuenta) 
VALUES ('laura.garcia@email.com', 'google_vol_002', 2, 'Activa');

SET @idUser = SCOPE_IDENTITY();

INSERT INTO VOLUNTARIO (id_usuario, dni, nombre, apellidos, telefono, fecha_nac, carnet_conducir, id_curso_actual)
VALUES (@idUser, '87654321B', 'Laura', 'García López', '600333444', '1998-11-20', 0, 3);

INSERT INTO PREFERENCIA_VOLUNTARIO (id_voluntario, id_tipo) VALUES (@idUser, 5); -- Salud

DECLARE @idVolLaura INT = @idUser;

-- =========================================================================
-- GESTIÓN DE ACTIVIDADES
-- Importante: El Trigger TR_Check_Cupo_Actividad requiere que la actividad
-- sea futura y esté publicada para poder inscribirse luego.
-- =========================================================================

-- ACTIVIDAD 1: Limpieza de Playa
-- Constraint CK_Duracion_Positiva: 4 horas es válido.
-- Constraint CK_Cupo_Positivo: 20 plazas es válido.
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, duracion_horas, cupo_maximo, ubicacion, estado_publicacion)
VALUES (@idOrgEco, 'Limpieza de Playa Norte', 'Recogida de plásticos en la costa.', DATEADD(day, 7, GETDATE()), 4, 20, 'Playa Norte, Valencia', 'Publicada');

DECLARE @idActividad1 INT = SCOPE_IDENTITY();

-- Vincular ODS y TIPO
INSERT INTO ACTIVIDAD_ODS (id_actividad, id_ods) VALUES (@idActividad1, 13); -- Acción por el clima
INSERT INTO ACTIVIDAD_TIPO (id_actividad, id_tipo) VALUES (@idActividad1, 1); -- Medioambiente

-- ACTIVIDAD 2: Taller de Reciclaje (Estado: 'En revision')
-- Nota: A esta actividad NO podremos inscribir a nadie por SQL hasta que no se publique (por el Trigger).
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, duracion_horas, cupo_maximo, ubicacion, estado_publicacion)
VALUES (@idOrgEco, 'Taller de Reciclaje Creativo', 'Aprende a reutilizar materiales.', DATEADD(day, 14, GETDATE()), 2, 10, 'Centro Cívico', 'En revision');

-- ACTIVIDAD 3: Reforestación (Borrada / Soft Delete)
-- Simulamos una actividad que se creó y se canceló/borró.
INSERT INTO ACTIVIDAD (id_organizacion, titulo, descripcion, fecha_inicio, estado_publicacion, deleted_at)
VALUES (@idOrgEco, 'Reforestación Sierra', 'Evento antiguo cancelado.', GETDATE(), 'Cancelada', GETDATE());

-- =========================================================================
-- INSCRIPCIONES
-- =========================================================================

/* PRUEBA DEL TRIGGER: TR_Check_Cupo_Actividad (INSTEAD OF INSERT)
   Este trigger validará:
   1. Que la actividad sea futura (Lo es, fecha_inicio = hoy + 7 días).
   2. Que esté publicada (Lo está).
   3. Que no haya choque de horario (Juan y Laura están libres).
   4. Que haya cupo (20 plazas > 0 ocupadas).
*/

-- Juan se inscribe
INSERT INTO INSCRIPCION (id_voluntario, id_actividad) 
VALUES (@idVolJuan, @idActividad1);

-- Laura se inscribe
INSERT INTO INSCRIPCION (id_voluntario, id_actividad) 
VALUES (@idVolLaura, @idActividad1);

/* PRUEBA DEL TRIGGER: TR_Check_Cupo_Update (AFTER UPDATE)
   El coordinador acepta a Juan. El trigger verificará que al cambiar a 'Aceptada'
   no excedamos el cupo de 20. (Hay 1 aceptado, cupo 20 -> OK).
*/
UPDATE INSCRIPCION
SET estado_solicitud = 'Aceptada'
WHERE id_voluntario = @idVolJuan
  AND id_actividad = @idActividad1;

-- =========================================================================
-- SIMULACIÓN DE FLUJO DE COORDINACIÓN
-- =========================================================================

-- Verificar qué actividades están pendientes de revisión (Excluye borradas por la vista)
SELECT * FROM VW_Actividades_Activas
WHERE estado_publicacion = 'En revision';

-- ACCIÓN: PUBLICAR EL TALLER DE RECICLAJE
-- Al cambiar a 'Publicada', el sistema ya permitirá inscripciones futuras.
UPDATE ACTIVIDAD
SET estado_publicacion = 'Publicada'
    -- updated_at se actualiza solo gracias al Trigger TR_Actividad_UpdatedAt
WHERE titulo = 'Taller de Reciclaje Creativo';

-- VERIFICACIÓN FINAL
-- Deberíamos ver 2 actividades publicadas: La limpieza y el taller.
SELECT * FROM VW_Actividades_Publicadas;