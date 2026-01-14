# Resumen de Cambios - Campo "descripcion" para Voluntario

## 📝 Descripción General

Se ha añadido el campo `descripcion` a la entidad **Voluntario** para permitir que los voluntarios puedan incluir una descripción personal en su perfil. Este campo es de tipo `TEXT` (nullable) y está configurado con el grupo de serialización `usuario:read`.

---

## 🔧 Cambios Realizados

### 1. **Entidad Voluntario** ✅

**Archivo**: `src/Entity/Voluntario.php`

-   ✅ Añadido campo `descripcion` (tipo `TEXT`, nullable)
-   ✅ Añadido grupo de serialización `#[Groups(['usuario:read'])]`
-   ✅ Añadidos métodos getter y setter: `getDescripcion()` y `setDescripcion()`

```php
#[ORM\Column(type: Types::TEXT, nullable: true)]
#[Groups(['usuario:read'])]
private ?string $descripcion = null;
```

---

### 2. **DTOs Actualizados** ✅

#### **VoluntarioCreateDTO**

**Archivo**: `src/Model/Voluntario/VoluntarioCreateDTO.php`

-   ✅ Añadido parámetro `descripcion` (opcional) al constructor
-   ✅ Añadida validación de longitud máxima (500 caracteres)

```php
#[Assert\Length(max: 500, maxMessage: "La descripción no puede tener más de 500 caracteres")]
public ?string $descripcion = null,
```

#### **VoluntarioUpdateDTO**

**Archivo**: `src/Model/Voluntario/VoluntarioUpdateDTO.php`

-   ✅ Añadido campo `descripcion` (opcional)
-   ✅ Añadida validación de longitud máxima (500 caracteres)

```php
#[Assert\Length(max: 500, maxMessage: "La descripción no puede tener más de 500 caracteres")]
public ?string $descripcion = null;
```

#### **VoluntarioResponseDTO**

**Archivo**: `src/Model/Voluntario/VoluntarioResponseDTO.php`

-   ✅ Añadido campo `descripcion` al constructor
-   ✅ Actualizado método `fromEntity()` para mapear la descripción

```php
public ?string $descripcion,   // Descripción personal
```

---

### 3. **VoluntarioController Actualizado** ✅

**Archivo**: `src/Controller/VoluntarioController.php`

#### **Método `registrar()`**

-   ✅ Añadida asignación de descripción al crear un nuevo voluntario:

```php
$voluntario->setDescripcion($dto->descripcion);
```

#### **Método `actualizar()`**

-   ✅ Añadida actualización de descripción (solo si se proporciona):

```php
if ($dto->descripcion !== null) {
    $voluntario->setDescripcion($dto->descripcion);
}
```

---

### 4. **DataFixtures Mejorados** ✅

**Archivo**: `src/DataFixtures/AppFixtures.php`

Se han mejorado significativamente los fixtures para trabajar mejor con el AuthController:

#### **Voluntarios Activos**

-   ✅ Añadidas descripciones personalizadas para cada voluntario de prueba
-   ✅ Implementada la lógica de preferencias (que estaba comentada)
-   ✅ Actualizado el método `createOrUpdatePerfilVoluntario()` para aceptar `descripcion`

#### **Voluntarios de Prueba (Estados Especiales)**

Se añadieron 4 nuevos voluntarios para probar todos los flujos del AuthController:

1. **Usuario Bloqueado** (`bloqueado@test.com`) - Estado: Bloqueada
2. **Usuario Pendiente** (`pendiente@test.com`) - Estado: Pendiente
3. **Usuario Rechazado** (`rechazado@test.com`) - Estado: Rechazada
4. **Usuario Eliminado** (`eliminado@test.com`) - Soft Delete activo

#### **Organizaciones Mejoradas**

-   ✅ Descripciones más detalladas y realistas
-   ✅ Teléfonos y CIFs específicos asignados

#### **Coordinador Mejorado**

-   ✅ Añadido teléfono al coordinador

---

## 📋 Formato del Campo

| Propiedad               | Valor          |
| ----------------------- | -------------- |
| **Nombre**              | `descripcion`  |
| **Tipo BD**             | `TEXT`         |
| **Tipo PHP**            | `?string`      |
| **Nullable**            | Sí             |
| **Longitud Máx**        | 500 caracteres |
| **Grupo Serialización** | `usuario:read` |

---

## 🧪 Ejemplos de Uso

### **Crear un Voluntario con Descripción**

```json
POST /voluntarios
{
  "google_id": "uid_nuevo",
  "correo": "nuevo@test.com",
  "nombre": "Juan",
  "apellidos": "García",
  "dni": "12345678X",
  "telefono": "600123456",
  "fecha_nac": "2000-01-15",
  "carnet_conducir": true,
  "id_curso_actual": 1,
  "descripcion": "Estudiante de DAM interesado en voluntariado tecnológico. Me apasiona ayudar a los demás.",
  "preferencias_ids": [1, 2],
  "idiomas": [
    {"id_idioma": 1, "nivel": "Nativo"},
    {"id_idioma": 2, "nivel": "B2"}
  ]
}
```

### **Actualizar Descripción de un Voluntario**

```json
PUT /voluntarios/5
Headers: X-User-Id: 5
{
  "nombre": "Pepe",
  "apellidos": "Pérez",
  "telefono": "600111222",
  "fechaNac": "1999-05-20",
  "descripcion": "Actualicé mi descripción: ahora busco experiencias en educación digital.",
  "preferencias_ids": [1, 3]
}
```

### **Respuesta GET de un Voluntario (con descripción)**

```json
GET /voluntarios/1

{
  "id_usuario": 1,
  "nombre_completo": "Pepe Pérez",
  "correo": "pepe@test.com",
  "curso": "DAM",
  "estado_cuenta": "Activa",
  "descripcion": "Estudiante de DAM apasionado por la tecnología y el desarrollo de apps.",
  "preferencias": ["Tecnológico / Digital"],
  "idiomas": [
    {"idioma": "Español", "nivel": "Nativo"},
    {"idioma": "Inglés", "nivel": "B2"}
  ]
}
```

---

## ✅ Checklist de Verificación

-   [x] Campo añadido a la entidad `Voluntario`
-   [x] Getters y setters implementados
-   [x] Campo añadido a `VoluntarioCreateDTO`
-   [x] Campo añadido a `VoluntarioUpdateDTO`
-   [x] Campo añadido a `VoluntarioResponseDTO`
-   [x] Método `fromEntity()` actualizado
-   [x] Controlador `registrar()` actualizado
-   [x] Controlador `actualizar()` actualizado
-   [x] DataFixtures mejorados con descripciones
-   [x] Validaciones de longitud añadidas
-   [x] Documento de usuarios de prueba creado (`USUARIOS_PRUEBA.md`)

---

## 🗄️ Migración de Base de Datos

Para aplicar estos cambios en la base de datos, ejecuta:

```bash
# Generar la migración
php bin/console make:migration

# Revisar el archivo de migración generado en migrations/

# Aplicar la migración
php bin/console doctrine:migrations:migrate
```

---

## 🔄 Recargar Fixtures

Para probar los nuevos datos de fixtures:

```bash
php bin/console doctrine:fixtures:load
```

**⚠️ Advertencia**: Este comando borrará todos los datos existentes.

---

## 📚 Documentación Adicional

-   Ver `USUARIOS_PRUEBA.md` para una lista completa de usuarios de prueba con sus credenciales
-   Los fixtures ahora incluyen voluntarios con diferentes estados de cuenta para probar todos los flujos del AuthController

---

## 🎉 Resumen

Todos los componentes han sido actualizados correctamente para soportar el nuevo campo `descripcion`:

-   ✅ **Entity Layer**: Voluntario
-   ✅ **DTO Layer**: Create, Update, Response
-   ✅ **Controller Layer**: Crear y Actualizar
-   ✅ **Data Layer**: Fixtures mejorados
-   ✅ **Documentation**: Usuarios de prueba documentados
