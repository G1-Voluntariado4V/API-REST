/* SCRIPT DE CONFIGURACIÓN INICIAL DEL ENTORNO DE DESARROLLO
   Instrucciones para el equipo:
   1. Abre SQL Server Management Studio (SSMS).
   2. Conéctate con tu usuario de Windows (Authentication: Windows Authentication).
   3. Abre este archivo y ejecútalo (F5).
*/

USE master;
GO

-- 1. Crear el Login (Si no existe)
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = 'symfony_app')
BEGIN
    CREATE LOGIN symfony_app WITH PASSWORD = 'Symfony2025!';
    ALTER LOGIN symfony_app ENABLE;
END
GO

-- 2. Crear la Base de Datos (Si no existe)
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'VoluntariadoDB')
BEGIN
    CREATE DATABASE VoluntariadoDB;
END
GO

-- 3. Asignar usuario y permisos
USE VoluntariadoDB;
GO

IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'symfony_app')
BEGIN
    CREATE USER symfony_app FOR LOGIN symfony_app;
    ALTER ROLE db_owner ADD MEMBER symfony_app;
END
GO

PRINT '¡Entorno configurado! Usuario symfony_app creado y BBDD lista.';