/* SCRIPT DE INICIALIZACIÓN: VOLUNTARIADODB (ENTORNO REAL) */
USE master;
GO

-- 1. Si existe, la borramos para empezar de cero (CUIDADO: Borra datos)
IF EXISTS (SELECT name FROM sys.databases WHERE name = 'VoluntariadoDB')
BEGIN
    -- Expulsamos a usuarios conectados para poder borrarla
    ALTER DATABASE VoluntariadoDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE VoluntariadoDB;
END
GO

-- 2. Creamos la base de datos vacía
CREATE DATABASE VoluntariadoDB;
GO

-- 3. Vinculamos el usuario symfony_app (que ya creamos ayer) a esta nueva BBDD
USE VoluntariadoDB;
GO

IF EXISTS (SELECT * FROM sys.server_principals WHERE name = 'symfony_app')
BEGIN
    -- Creamos el usuario dentro de la BBDD
    CREATE USER symfony_app FOR LOGIN symfony_app;
    -- Le damos permisos de dueño (db_owner) para crear tablas, borrar, insertar...
    ALTER ROLE db_owner ADD MEMBER symfony_app;
END
ELSE
BEGIN
    PRINT '¡ERROR! El login symfony_app no existe en el servidor. Ejecuta primero el script de creación de usuario.';
END
GO

PRINT 'Base de datos VoluntariadoDB creada y usuario symfony_app autorizado.';