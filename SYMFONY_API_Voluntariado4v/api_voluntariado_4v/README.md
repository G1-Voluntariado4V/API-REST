# 🚀 API Gestión de Voluntariado (Symfony + SQL Server)

Backend para la plataforma de gestión de voluntariado 4v. Desarrollado en **Symfony 7** utilizando **Doctrine ORM** y **Microsoft SQL Server**.

---

## 📋 1. Requisitos Indispensables (Qué necesitas tener instalado)

Antes de descargar el código, asegúrate de que tu entorno de desarrollo cumpla con estos requisitos.

### 🛠️ Herramientas Básicas

| Herramienta     | Versión  | Notas                                                                                                            |
| --------------- | -------- | ---------------------------------------------------------------------------------------------------------------- |
| **PHP**         | 8.2+     | **Importante:** Se recomienda la versión **Thread Safe (TS)** para compatibilidad con los drivers de SQL Server. |
| **Composer**    | Última   | Gestor de dependencias de PHP.                                                                                   |
| **Symfony CLI** | Opcional | Recomendado para ejecutar el servidor local y gestionar certificados TLS.                                        |
| **Git**         | -        | Para clonar el repositorio.                                                                                      |

### 🗄️ Base de Datos (SQL Server)

-   **Microsoft SQL Server** (Express o Developer Edition 2019+).
-   **SQL Server Management Studio (SSMS)**: Para administrar la BD manualmente.

### 🔌 Drivers PHP para SQL Server

Para que PHP pueda comunicarse con SQL Server, necesitas instalar los drivers de Microsoft:

1.  Descarga los drivers desde [Microsoft Download Center](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server).
2.  Descomprime y copia los archivos `.dll` que coincidan con tu versión de PHP (ej. `php_sqlsrv_82_ts_x64.dll` y `php_pdo_sqlsrv_82_ts_x64.dll`) en la carpeta `ext` de tu instalación de PHP.
3.  Habilítalos en tu archivo `php.ini` añadiendo:
    ```ini
    extension=php_sqlsrv_82_ts_x64.dll
    extension=php_pdo_sqlsrv_82_ts_x64.dll
    ```
4.  Reinicia tu terminal o servidor para aplicar los cambios.

---

## ⚙️ 2. Configuración Inicial del Proyecto

Sigue estos pasos ordenados para poner en marcha la API.

### Paso 1: Clonar e Instalar Dependencias

Abrir una terminal en la carpeta deseada y ejecutar:

```bash
# Clonar repositorio
git clone <url-del-repositorio>
cd api_voluntariado_4v

# Instalar librerías PHP
composer install

# Crear carpeta de migraciones (necesario si está ignorada en git)
mkdir migrations
```

### Paso 2: Configuración del Entorno (.env)

Este proyecto utiliza variables de entorno.

1. Busca un archivo llamado `.env` en la raíz del proyecto.
2. Define tu conexión a base de datos. Ejemplo para **SQL Express** mira que la conexión esté escrita correctamente:

```bash
# .env
DATABASE_URL="sqlsrv://symfony_app:Symfony2025!@127.0.0.1/VoluntariadoDB?instance=SQLEXPRESS&trustServerCertificate=true&charset=UTF-8"
```

_Asegúrate de ajustar la instancia (`SQLEXPRESS`) si tu instalación tiene otro nombre._

### Paso 3: Configuración de SQL Server

Para que la API conecte, necesitamos configurar el servidor y crear el usuario dedicado.

**A. Configuración de Red (Solo primera vez)**

1. Abre **SQL Server Configuration Manager** (`SQLServerManager16.msc`).
2. Ve a _SQL Server Network Configuration > Protocols for [Instancia]_.
3. Habilita **TCP/IP**.
4. En propiedades de TCP/IP > IP Addresses > **IPAll**, asegura que el puerto TCP es **1433**.
5. **Reinicia el servicio SQL Server**.

**B. Crear Base de Datos y Usuario**
Puedes ejecutar este script SQL en **SSMS** para configurar todo automáticamente:

```sql
/* setup_dev_env.sql */
USE master;
GO
-- 1. Crear Login y Usuario
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = 'symfony_app')
BEGIN
    CREATE LOGIN symfony_app WITH PASSWORD = 'Symfony2025!';
    ALTER LOGIN symfony_app ENABLE;
END
GO
-- 2. Crear Base de Datos
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'VoluntariadoDB')
BEGIN
    CREATE DATABASE VoluntariadoDB;
END
GO
-- 3. Asignar permisos
USE VoluntariadoDB;
GO
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'symfony_app')
BEGIN
    CREATE USER symfony_app FOR LOGIN symfony_app;
    ALTER ROLE db_owner ADD MEMBER symfony_app;
END
GO
```

_Asegúrate de que la "Autenticación de SQL Server y Windows" (Modo Mixto) esté activada en las propiedades del servidor._

---

## 🗄️ 3. Crear Tablas y Datos de Prueba

Una vez configurada la conexión, inicializa la estructura de la base de datos siguiendo este orden estricto:

### 1. Generar y Ejecutar Migración Inicial (Tablas)

Como las migraciones no se incluyen en el repositorio (gitignore), créalas desde cero:

```bash
# Generar migración base
php bin/console make:migration

# Aplicar migración para crear tablas
php bin/console doctrine:migrations:migrate
```

### 2. Cargar Datos de Prueba (Fixtures)

Es necesario tener las tablas creadas.

```bash
php bin/console doctrine:fixtures:load
# Escribe 'yes' cuando te pida confirmación.
```

### 3. Añadir Triggers, Vistas y Procedimientos

La lógica avanzada de SQL Server se añade en una migración separada.

1.  Genera una migración vacía:
    ```bash
    php bin/console make:migration
    ```
2.  Abre el nuevo archivo generado en la carpeta `migrations/`.
3.  Copia el contenido del archivo `migracionTriggers.txt` (ubicado en la raíz del proyecto) y úsalo para reemplazar el contenido de la migración generada.
4.  Ejecuta esta migración:
    ```bash
    php bin/console doctrine:migrations:migrate
    ```

#### 👥 Usuarios Disponibles (Fixtures)

| Rol              | Email (Login)              | Password      | Notas                   |
| ---------------- | -------------------------- | ------------- | ----------------------- |
| **Coordinador**  | `maitesolam@gmail.com`     | (Google Auth) | Usuario Admin Principal |
| **Voluntario**   | `pepe.voluntario@test.com` | (Ficticio)    | -                       |
| **Organización** | `ayuda@ong.com`            | (Ficticio)    | -                       |

> **Nota:** Para desarrollo, puedes modificar los UIDs en `src/DataFixtures/AppFixtures.php` para que coincidan con tu usuario real de Google/Firebase si necesitas loguearte como admin.

---

## ▶️ 4. Ejecutar el Servidor

Inicia el servidor local de desarrollo:

**Opción A: Symfony CLI (Recomendado)**

```bash
symfony server:start
```

_Disponible en: https://127.0.0.1:8000_

**Opción B: PHP Built-in**

```bash
php bin/console server:start
```

_Disponible en: http://127.0.0.1:8000_

---

## 🧪 5. Testing

El proyecto tiene una suite de tests automatizados (Unitarios, Integración y E2E).

```bash
# Ejecutar todos los tests
php bin/phpunit --testdox

# Ejecutar solo tests de Controladores (API)
php bin/phpunit --testdox tests/Controller
```

---

## 📚 Documentación

-   **OpenAPI/Swagger**: Archivo `openapi.yaml` en la raíz. Importable en Postman.
-   **Interfaz Visual (Swagger UI)**: Consulta y prueba los endpoints interactivamente en [http://127.0.0.1:8000/doc](http://127.0.0.1:8000/doc).
-   **Rutas**: Puedes ver todas las rutas registradas con `php bin/console debug:router`.

---

## 🛠️ Solución de Problemas Comunes

**Error: `SQLSTATE[HY000] [2002]`**

-   **Causa:** Symfony intenta conectar a MySQL por defecto o no detecta el driver `sqlsrv`.
-   **Solución:** Verifica `config/packages/doctrine.yaml` y asegura `driver: 'sqlsrv'`. Limpia caché: `php bin/console cache:clear`.

**Error: `Login failed for user 'root'` o similar**

-   **Causa:** Configuración de `.env` incorrecta.
-   **Solución:** Revisa `.env.local` y asegura que usas el usuario `symfony_app` creado anteriormente.

**Error: `SSL Provider... certificate chain...`**

-   **Solución:** Falta confiar en el certificado autofirmado de SQL Server. Asegura `&trustServerCertificate=true` en tu `DATABASE_URL`.
