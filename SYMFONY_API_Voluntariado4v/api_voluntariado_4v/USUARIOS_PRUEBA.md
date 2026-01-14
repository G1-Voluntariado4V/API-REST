# Usuarios de Prueba - DataFixtures

Este documento lista todos los usuarios creados en los DataFixtures para facilitar las pruebas de la API, especialmente del AuthController.

## 📋 Índice

-   [Coordinador](#coordinador)
-   [Organizaciones](#organizaciones)
-   [Voluntarios Activos](#voluntarios-activos)
-   [Voluntarios de Prueba (Estados Especiales)](#voluntarios-de-prueba-estados-especiales)

---

## 👨‍💼 Coordinador

| Nombre     | Email                | Google ID        | Teléfono  | Estado Cuenta |
| ---------- | -------------------- | ---------------- | --------- | ------------- |
| Maite Sola | maitesolam@gmail.com | google_uid_maite | 948000000 | Activa        |

---

## 🏢 Organizaciones

| Nombre          | Email                 | Google ID      | CIF       | Teléfono  | Estado Cuenta |
| --------------- | --------------------- | -------------- | --------- | --------- | ------------- |
| Tech For Good   | info@techforgood.org  | uid_org_tech   | G31234567 | 948123456 | Activa        |
| EcoVida         | contacto@ecovida.org  | uid_org_eco    | G31234568 | 948234567 | Activa        |
| Animal Rescue   | help@animalrescue.org | uid_org_animal | G31234569 | 948345678 | Activa        |
| Cruz Roja Local | cruzroja@org.com      | uid_cr         | G31234570 | 948456789 | Activa        |

### Descripciones de Organizaciones

-   **Tech For Good**: ONG dedicada a promover la tecnología social y la alfabetización digital. Organizamos talleres y eventos para acercar la tecnología a colectivos vulnerables.
-   **EcoVida**: Asociación ecologista comprometida con la protección del medio ambiente. Realizamos actividades de limpieza, reforestación y educación ambiental.
-   **Animal Rescue**: Refugio de animales abandonados. Buscamos voluntarios para paseos, cuidados y eventos de adopción responsable.
-   **Cruz Roja Local**: Delegación local de Cruz Roja. Realizamos campañas de recogida de alimentos, ayuda a personas sin hogar y emergencias sociales.

---

## 👥 Voluntarios Activos

| Nombre      | Email           | Google ID  | Curso | Preferencias                 | Estado Cuenta |
| ----------- | --------------- | ---------- | ----- | ---------------------------- | ------------- |
| Pepe Pérez  | pepe@test.com   | uid_pepe   | DAM   | Tecnológico / Digital        | Activa        |
| Laura Gómez | laura@test.com  | uid_laura  | SMR   | Salud / Sanitario            | Activa        |
| Carlos Ruiz | carlos@test.com | uid_carlos | TL    | Deportivo, Protección Animal | Activa        |
| Ana López   | ana@test.com    | uid_ana    | GVEC  | Acción Social, Educación     | Activa        |

### Descripciones de Voluntarios

-   **Pepe Pérez**: Estudiante de DAM apasionado por la tecnología y el desarrollo de apps. Me encanta ayudar a otras personas a aprender programación.
-   **Laura Gómez**: Técnica en sistemas con interés en la salud digital. Busco experiencias de voluntariado en el sector sanitario.
-   **Carlos Ruiz**: Amante del deporte y los animales. Estudiante de Transporte y Logística con ganas de ayudar en refugios y eventos deportivos.
-   **Ana López**: Estudiante de Gestión de Ventas y Espacios Comerciales. Me motiva el trabajo social y la educación de jóvenes.

---

## 🧪 Voluntarios de Prueba (Estados Especiales)

Estos usuarios son específicamente para probar diferentes flujos del AuthController:

| Nombre            | Email              | Google ID     | Curso | Estado Cuenta                    | Propósito                                               |
| ----------------- | ------------------ | ------------- | ----- | -------------------------------- | ------------------------------------------------------- |
| Usuario Bloqueado | bloqueado@test.com | uid_bloqueado | DAM   | **Bloqueada**                    | Probar respuesta 403 por cuenta bloqueada               |
| Usuario Pendiente | pendiente@test.com | uid_pendiente | SMR   | **Pendiente**                    | Probar respuesta 403 por cuenta pendiente               |
| Usuario Rechazado | rechazado@test.com | uid_rechazado | GVEC  | **Rechazada**                    | Probar respuesta 403 por cuenta rechazada               |
| Usuario Eliminado | eliminado@test.com | uid_eliminado | TL    | Activa (pero deleted_at != null) | Probar respuesta 403 por cuenta eliminada (soft delete) |

---

## 🧪 Ejemplos de Pruebas para AuthController

### ✅ Login Exitoso (200)

```bash
# Voluntario activo
POST /auth/login
{
    "google_id": "uid_pepe",
    "email": "pepe@test.com"
}

# Organización activa
POST /auth/login
{
    "google_id": "uid_org_tech",
    "email": "info@techforgood.org"
}

# Coordinador activo
POST /auth/login
{
    "google_id": "google_uid_maite",
    "email": "maitesolam@gmail.com"
}
```

### ❌ Usuario No Registrado (404)

```bash
POST /auth/login
{
    "google_id": "uid_inexistente",
    "email": "noexiste@test.com"
}
```

### 🚫 Cuenta Bloqueada (403)

```bash
POST /auth/login
{
    "google_id": "uid_bloqueado",
    "email": "bloqueado@test.com"
}
```

### ⏳ Cuenta Pendiente (403)

```bash
POST /auth/login
{
    "google_id": "uid_pendiente",
    "email": "pendiente@test.com"
}
```

### 🚫 Cuenta Rechazada (403)

```bash
POST /auth/login
{
    "google_id": "uid_rechazado",
    "email": "rechazado@test.com"
}
```

### 🗑️ Cuenta Eliminada (403)

```bash
POST /auth/login
{
    "google_id": "uid_eliminado",
    "email": "eliminado@test.com"
}
```

### ⚠️ Datos Faltantes (400)

```bash
POST /auth/login
{
    # sin google_id ni email
}
```

---

## 📝 Notas

1. **Google ID vs Email**: El AuthController acepta ambos. Si se proporciona `google_id`, se busca primero por ese campo. Si no se encuentra, se busca por `email`.

2. **Actualización de Google ID**: Si un usuario se registra solo con email y luego hace login con Google, el sistema actualiza automáticamente su `google_id`.

3. **Grupos de Serialización**: Los voluntarios tienen el grupo `usuario:read` que incluye:

    - DNI
    - Nombre
    - Apellidos
    - Teléfono
    - Descripción (nuevo campo)
    - Idiomas
    - Inscripciones
    - Preferencias

4. **Respuesta del Login**: El AuthController devuelve diferentes campos según el rol:
    - **Voluntario**: id_usuario, google_id, correo, rol, estado_cuenta, nombre, apellidos, telefono, dni, curso
    - **Organización**: id_usuario, google_id, correo, rol, estado_cuenta, nombre, telefono, cif, descripcion
    - **Coordinador**: id_usuario, google_id, correo, rol, estado_cuenta, nombre, apellidos, telefono

---

## 🔄 Cómo Recargar los Fixtures

```bash
# En Windows PowerShell
php bin/console doctrine:fixtures:load

# Confirmar con: yes
```

**⚠️ Advertencia**: Este comando borrará TODOS los datos existentes en la base de datos y los reemplazará con los datos de los fixtures.
