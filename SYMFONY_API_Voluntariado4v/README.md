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
    - Activarlas en el `php.ini`:
      `extension=php_sqlsrv_82_ts_x64.dll`
      `extension=php_pdo_sqlsrv_82_ts_x64.dll`
      `extension=intl`

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
    CREATE LOGIN symfony_app WITH PASSWORD = 'Symfony2025!';
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

## 3. Crear Base de Datos(Migraciones)

Este proyecto usa Code First. No crees tablas manualmente.

### Paso 1: Ejecutar Migraciones:

Este comando crea todas las tablas (USUARIO, VOLUNTARIO, ORGANIZACION...) automáticamente.

```bash
php bin/console doctrine:migrations:migrate
```

## 4. Cargar Datos de Prueba (Fixtures)

Para poder entrar en la aplicación nada más instalarla, hemos preparado un set de datos.

1. Ejecuta el comando de carga: (Escribe 'yes' cuando pregunte si quieres purgar la base de datos).

```bash
php bin/console doctrine:fixtures:load
```

2. Usuarios disponibles tras la carga
   Rol Correo (Login Google) Estado Notas
   Coordinador maitesolam@gmail.com Activo Usuario Admin Principal
   Voluntario pepe.voluntario@test.com Activo Usuario Ficticio
   Organización ayuda@ong.com Activo Usuario Ficticio

⚠️ Nota para Desarrolladores (Frontend):

Para ser Admin: Si queréis entrar como Coordinador con vuestra cuenta de Google, id a src/DataFixtures/AppFixtures.php, cambiad el UID de Maite por el vuestro real y ejecutad de nuevo el comando de fixtures.

Para probar Voluntario/ONG: Usad el formulario de registro del frontend o editad el UID en base de datos para suplantar a los usuarios ficticios.

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
