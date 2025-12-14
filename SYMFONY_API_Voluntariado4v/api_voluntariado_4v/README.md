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
    -   Debes descargar las DLLs (`php_sqlsrv` y `php_pdo_sqlsrv`) correspondientes a tu versión de PHP. Puedes descargar desde: https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server?view=sql-server-ver17
    -   Pegarlas en la carpeta `ext` de tu PHP.
    -   Activarlas en el `php.ini`:
        `extension=php_sqlsrv_82_ts_x64.dll`
        `extension=php_pdo_sqlsrv_82_ts_x64.dll`

---

## ⚙️ 1. Configuración de SQL Server (Solo la primera vez)

Para que la aplicación conecte, necesitamos configurar el servidor y crear el usuario dedicado `symfony_app`.

### A. Habilitar TCP/IP y Modo Mixto

1.  Win + R > SQLServerManager16.msc
2.  Configuración de Red de SQL Server > Protocolos de ['nombreInstancia'] > TCP/IP
3.  Habilita **TCP/IP**. En propiedades > Direcciones IP > **IPAll**, pon el puerto **1433**.
4.  **Reinicia el servicio de SQL Server**.
5.  Abre **SSMS**, clic derecho en el Servidor > Propiedades > Seguridad.
6.  Marca **"Modo de autenticación de SQL Server y de Windows"**.

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

DATABASE_URL="sqlsrv://symfony_app:Symfony2025!@127.0.0.1/VoluntariadoDB?instance=SQLEXPRESS&trustServerCertificate=true&charset=UTF-8"

4. En el archivo doctrine.yaml, asegúrate de que el driver esté configurado como 'sqlsrv'.

```yaml
doctrine:
    dbal:
        url: "%env(resolve:DATABASE_URL)%"
        driver: "sqlsrv" # Esto es lo único fijo importante
```

5. Verificar conexión: Ejecuta este comando. Si ves la versión de SQL Server, todo está correcto.

```bash
php bin/console doctrine:query:sql "SELECT @@VERSION"
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

Para iniciar el servidor de desarrollo, usa una de estas opciones:

**Opción 1: Usar Symfony CLI (Recomendado)**
```bash
symfony server:start
```

**Opción 2: Usar servidor PHP built-in**
```bash
php -S localhost:8000 -t public
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

# 7.1. Error de autenticación de usuario en SQLServer

Error 18456: Login failed for user 'root'

Solución: Asegúrate de que en el .env. tienes la URL correcta con el usuario y contraseña.

Solución 2: Asegurate de que en SQL server en tu usuario
    Carpeta Security
        Logins
            symfony_app (clic derecho)
                properties
                    General: Enforce password policy: No
                    User Mapping: Esté la base de datos seleccionada y que esté marcado como db_owner
                    status: Enabled

# 🧪 8. QA y Testing (Documentación Oficial)

Este proyecto sigue una estrategia de testing piramidal, cubriendo Unitarios, Integración y Funcionales.

## 8.1 Ejecutar Tests según Capa

### **1. Tests Unitarios (Lógica de Negocio)**
Verifican el comportamiento interno de las Entidades y Servicios sin tocar la Base de Datos.
- **Ubicación**: `tests/Entity`
- **Comando**:
```bash
php bin/phpunit --testdox tests/Entity
```

### **2. Tests de Integración (Capa de Datos)**
Verifican que el repositorio y el driver conectan correctamente con SQL Server y las Vistas/SP.
- **Ubicación**: `tests/Integration`
- **Comando**:
```bash
php bin/phpunit --testdox tests/Integration
```

### **3. Tests Funcionales (Endpoints API)**
Prueban la aplicación completa simulando peticiones HTTP reales. Validan rutas, seguridad y respuestas JSON.
- **Ubicación**: `tests/Controller`
- **Comando**:
```bash
php bin/phpunit --testdox tests/Controller
```

## 8.2 Ejecución Global
Para lanzar toda la suite de QA:
```bash
php bin/phpunit --testdox
```
