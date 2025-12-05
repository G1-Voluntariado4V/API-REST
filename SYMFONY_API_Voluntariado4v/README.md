# API Gestión de Voluntariado (Symfony + SQL Server)

Backend para la plataforma de gestión de voluntariado. Desarrollado en **Symfony 7** utilizando **Doctrine ORM** conectado a **Microsoft SQL Server**.

## 📋 Requisitos Previos

Antes de empezar, asegúrate de tener instalado en tu máquina:

1.  **PHP 8.2+** (Recomendado versión Thread Safe - TS).
2.  **Composer** (Gestor de paquetes PHP).
3.  **Symfony CLI** (Opcional pero recomendado).
4.  **Microsoft SQL Server** (Express o Developer Edition).
5.  **SQL Server Management Studio (SSMS)**.
6.  **Drivers PHP para SQL Server**:
    - Debes descargar las DLLs (`php_sqlsrv` y `php_pdo_sqlsrv`) correspondientes a tu versión de PHP.
    - Pegarlas en la carpeta `ext` de tu PHP.
    - Activarlas en el `php.ini` (`extension=php_sqlsrv...`).

---

## ⚙️ 1. Configuración de SQL Server (Solo la primera vez)

Para que la aplicación conecte, necesitamos configurar el servidor y crear el usuario dedicado `symfony_app`.

### A. Habilitar TCP/IP y Modo Mixto

1.  Abre **SQL Server Configuration Manager**.
2.  Ve a **Configuración de red de SQL Server** > Protocolos.
3.  Habilita **TCP/IP**. En propiedades > Direcciones IP > **IPAll**, pon el puerto **1433**.
4.  Abre **SSMS**, clic derecho en el Servidor > Propiedades > Seguridad.
5.  Marca **"Modo de autenticación de SQL Server y de Windows"**.
6.  **Reinicia el servicio de SQL Server**.

### B. Ejecutar Script de Instalación

Hemos preparado un script que crea la BBDD y el usuario automáticamente.

1.  Abre el archivo `docs/database/setup_dev_env.sql` (o crea uno con el código de abajo).
2.  Ábrelo en **SSMS** y ejecútalo (F5).

````sql
/* setup_dev_env.sql */
USE master;
GO
-- Crear Login y Usuario
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = 'symfony_app')
BEGIN
    CREATE LOGIN symfony_app WITH PASSWORD = 'TuPasswordFuerte1!';
    ALTER LOGIN symfony_app ENABLE;
END
GO
-- Crear Base de Datos
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'VoluntariadoDB')
BEGIN
    CREATE DATABASE VoluntariadoDB;
END
GO
-- Asignar permisos
USE VoluntariadoDB;
GO
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'symfony_app')
BEGIN
    CREATE USER symfony_app FOR LOGIN symfony_app;
    ALTER ROLE db_owner ADD MEMBER symfony_app;
END
GO

---

## ⚙️ 2. Instalación del Proyecto Symfony

1. Clonar el repositorio:
```bash
git clone https://github.com/your-repo/api_voluntariado_4v.git
````

2. Instalar dependencias:

```bash
cd api_voluntariado_4v
composer install
```

3. Configurar variables de entorno:

```bash
cp .env.local .env
```

# .env.local

# Ajusta 'Symfony2025!' si lo cambiaste en el script SQL.

# Ajusta 'instance=SQLEXPRESS' si tu instancia tiene otro nombre.

DATABASE_URL="sqlsrv://symfony_app:Symfony2025!@127.0.0.1/VoluntariadoDB?instance=SQLEXPRESS&trustServerCertificate=true&charset=UTF-8"

4. En el archivo doctrine.yaml, asegúrate de que el driver esté configurado como 'sqlsrv'.

```yaml
doctrine:
  dbal:
    url: "%env(resolve:DATABASE_URL)%"
    driver: "sqlsrv" # Esto es lo único fijo importante
    # ... resto de opciones ...
```

5. Verificar conexión: Ejecuta este comando. Si ves la versión de SQL Server, todo está correcto.

```bash
php bin/console doctrine:dbal:run-sql "SELECT @@VERSION"
```

---

## 4. Flujo de Trabajo: Base de Datos e Ingenieria Inversa

### Paso 1: Cargar/Restaurar la BBDD

Si la base de datos está vacía o desactualizada:

Abre SSMS.

Ejecuta el script completo MockData_VoluntariadoDB_Query.sql (o el nombre que tenga el script maestro de creación de tablas).

Esto creará todas las tablas (USUARIO, ROL, ACTIVIDAD, etc.) y datos de prueba.

### Paso 2: Generar entidades a partir de la BBDD

Para crear las clases PHP (Entidades) basándonos en las tablas de SQL Server:
Importar mapeo:

```bash
php bin/console doctrine:mapping:import "App\Entity" attribute --path=src/Entity
```

Esto crea los archivos básicos en src/Entity.

Generar Getters y Setters:

```bash
php bin/console make:entity --regenerate App\Entity
```

Ajustes manuales: Revisa las entidades creadas. A veces Doctrine nombra las clases en plural (ej: Usuarios). Renómbralas a singular si es necesario y ajusta los nombres de archivo.

## ▶️ 5. Ejecutar el Servidor

Para iniciar el servidor de desarrollo de Symfony:

```bash
php bin/console server:start
```

La API estará disponible en http://127.0.0.1:8000.

## 📝 6. Documentación

La documentación de la API se encuentra en el archivo `voluntariado_api.yaml`.

## 7. 🛠️ Solución de Problemas Comunes

Error SQLSTATE[HY000] [2002]:

Symfony está intentando conectar a MySQL.

Solución: Asegúrate de que en config/packages/doctrine.yaml tienes driver: 'sqlsrv' y borra la caché con php bin/console cache:clear.

Error Login failed for user 'root':

No has configurado el usuario en el .env o la caché está sucia.

Solución: Revisa el .env.local y ejecuta php bin/console cache:clear.

Error SSL Provider... certificate chain...:

Falta confiar en el certificado.

Solución: Asegúrate de que la URL en el .env termina con &trustServerCertificate=true.
