# Nuevos Endpoints - Estadísticas y Rankings

## 📊 Resumen

Se han añadido **2 nuevos endpoints** a la API para obtener estadísticas de voluntarios y organizaciones:

1. **GET `/voluntarios/{id}/horas-totales`** - Total de horas de voluntariado de un voluntario
2. **GET `/organizaciones/top-voluntarios`** - Top 3 de organizaciones con más voluntarios

---

## 🎯 Endpoint 1: Horas Totales del Voluntario

### **Información General**

| Campo           | Valor                                                                                 |
| --------------- | ------------------------------------------------------------------------------------- |
| **Método**      | GET                                                                                   |
| **Ruta**        | `/voluntarios/{id}/horas-totales`                                                     |
| **Seguridad**   | Requiere header `X-User-Id` (solo el propio voluntario puede consultar)               |
| **Descripción** | Calcula el total de horas que un voluntario ha dedicado a actividades de voluntariado |

### **Parámetros**

-   **`{id}`** (path) - ID del voluntario
-   **`X-User-Id`** (header) - ID del usuario autenticado (debe coincidir con `{id}`)

### **Lógica de Cálculo**

El endpoint suma las horas de todas las actividades donde el voluntario tiene una inscripción con estado:

-   ✅ **Aceptada**
-   ✅ **Finalizada**

❌ **NO** cuenta inscripciones con estado: `Pendiente`, `Rechazada`, `Cancelada`

### **Respuesta Exitosa (200)**

```json
{
    "id_voluntario": 1,
    "nombre_completo": "Pepe Pérez",
    "horas_totales": 45,
    "actividades_completadas": 8,
    "nivel_experiencia": "Intermedio",
    "detalles": [
        {
            "titulo_actividad": "Taller de Alfabetización Digital",
            "duracion_horas": 5,
            "fecha_inicio": "2026-02-15 17:00:00",
            "estado": "Aceptada",
            "organizacion": "Tech For Good"
        },
        {
            "titulo_actividad": "Limpieza del Río Arga",
            "duracion_horas": 3,
            "fecha_inicio": "2026-01-20 09:00:00",
            "estado": "Finalizada",
            "organizacion": "EcoVida"
        }
    ]
}
```

### **Campos de la Respuesta**

| Campo                     | Tipo    | Descripción                                                                   |
| ------------------------- | ------- | ----------------------------------------------------------------------------- |
| `id_voluntario`           | integer | ID del voluntario                                                             |
| `nombre_completo`         | string  | Nombre y apellidos                                                            |
| `horas_totales`           | integer | Total de horas acumuladas                                                     |
| `actividades_completadas` | integer | Número de actividades con estado Aceptada/Finalizada                          |
| `nivel_experiencia`       | string  | Clasificación: `Principiante` (<20h), `Intermedio` (20-50h), `Experto` (>50h) |
| `detalles`                | array   | Lista de actividades con información detallada                                |

### **Códigos de Respuesta**

| Código | Descripción                             |
| ------ | --------------------------------------- |
| 200    | Horas calculadas correctamente          |
| 403    | Acceso denegado (X-User-Id no coincide) |
| 404    | Voluntario no encontrado o eliminado    |

### **Ejemplo de Uso (cURL)**

```bash
curl -X GET "http://localhost:8000/voluntarios/1/horas-totales" \
  -H "X-User-Id: 1" \
  -H "Content-Type: application/json"
```

### **Ejemplo de Uso (JavaScript)**

```javascript
async function obtenerHorasTotales(idVoluntario) {
    const response = await fetch(
        `http://localhost:8000/voluntarios/${idVoluntario}/horas-totales`,
        {
            method: "GET",
            headers: {
                "X-User-Id": idVoluntario.toString(),
                "Content-Type": "application/json",
            },
        }
    );

    const data = await response.json();
    console.log(`Total de horas: ${data.horas_totales}`);
    console.log(`Nivel: ${data.nivel_experiencia}`);
    return data;
}

// Uso
obtenerHorasTotales(1);
```

---

## 🏆 Endpoint 2: Top 3 Organizaciones por Voluntarios

### **Información General**

| Campo           | Valor                                                                                                        |
| --------------- | ------------------------------------------------------------------------------------------------------------ |
| **Método**      | GET                                                                                                          |
| **Ruta**        | `/organizaciones/top-voluntarios`                                                                            |
| **Seguridad**   | Público (no requiere autenticación)                                                                          |
| **Descripción** | Obtiene el ranking de las 3 organizaciones con más voluntarios únicos que han participado en sus actividades |

### **Lógica de Cálculo**

El ranking se calcula contando:

-   **Voluntarios únicos** (`DISTINCT`) que han participado en actividades de cada organización
-   Solo se cuentan inscripciones con estado `Aceptada` o `Finalizada`
-   Solo organizaciones con estado de cuenta `Activa`
-   Ordenado por número de voluntarios (descendente), luego por número de actividades

### **Respuesta Exitosa (200)**

```json
[
    {
        "posicion": 1,
        "id_organizacion": 4,
        "nombre": "Cruz Roja Local",
        "cif": "G31234570",
        "total_voluntarios": 156,
        "total_actividades": 45,
        "descripcion": "Delegación local de Cruz Roja. Realizamos campañas de recogida de alimentos...",
        "telefono": "948456789",
        "sitio_web": "https://www.cruzroja.es"
    },
    {
        "posicion": 2,
        "id_organizacion": 2,
        "nombre": "EcoVida",
        "cif": "G31234568",
        "total_voluntarios": 89,
        "total_actividades": 32,
        "descripcion": "Asociación ecologista comprometida con la protección del medio ambiente...",
        "telefono": "948234567",
        "sitio_web": "https://www.ecovida.org"
    },
    {
        "posicion": 3,
        "id_organizacion": 1,
        "nombre": "Tech For Good",
        "cif": "G31234567",
        "total_voluntarios": 67,
        "total_actividades": 28,
        "descripcion": "ONG dedicada a promover la tecnología social y la alfabetización digital...",
        "telefono": "948123456",
        "sitio_web": "https://www.techforgood.org"
    }
]
```

### **Campos de la Respuesta**

| Campo               | Tipo    | Descripción                                         |
| ------------------- | ------- | --------------------------------------------------- |
| `posicion`          | integer | Posición en el ranking (1, 2, 3)                    |
| `id_organizacion`   | integer | ID de la organización                               |
| `nombre`            | string  | Nombre de la organización                           |
| `cif`               | string  | CIF de la organización                              |
| `total_voluntarios` | integer | Número de voluntarios únicos que han participado    |
| `total_actividades` | integer | Número total de actividades creadas (no eliminadas) |
| `descripcion`       | string  | Descripción de la organización                      |
| `telefono`          | string  | Teléfono de contacto                                |
| `sitio_web`         | string  | URL del sitio web                                   |

### **Códigos de Respuesta**

| Código | Descripción                    |
| ------ | ------------------------------ |
| 200    | Ranking obtenido correctamente |
| 500    | Error interno del servidor     |

### **Ejemplo de Uso (cURL)**

```bash
curl -X GET "http://localhost:8000/organizaciones/top-voluntarios" \
  -H "Content-Type: application/json"
```

### **Ejemplo de Uso (JavaScript)**

```javascript
async function obtenerTopOrganizaciones() {
    const response = await fetch(
        "http://localhost:8000/organizaciones/top-voluntarios",
        {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        }
    );

    const ranking = await response.json();

    ranking.forEach((org) => {
        console.log(
            `${org.posicion}. ${org.nombre} - ${org.total_voluntarios} voluntarios`
        );
    });

    return ranking;
}

// Uso
obtenerTopOrganizaciones();
```

### **Ejemplo para Mostrar en UI**

```html
<div class="ranking-container">
    <h2>🏆 Top Organizaciones</h2>
    <div id="top-organizaciones"></div>
</div>

<script>
    async function mostrarRanking() {
        const response = await fetch("/organizaciones/top-voluntarios");
        const ranking = await response.json();

        const container = document.getElementById("top-organizaciones");

        ranking.forEach((org) => {
            const medalla =
                org.posicion === 1 ? "🥇" : org.posicion === 2 ? "🥈" : "🥉";

            container.innerHTML += `
      <div class="org-card">
        <span class="medalla">${medalla}</span>
        <h3>${org.nombre}</h3>
        <p>${org.descripcion}</p>
        <div class="stats">
          <span>👥 ${org.total_voluntarios} voluntarios</span>
          <span>📅 ${org.total_actividades} actividades</span>
        </div>
      </div>
    `;
        });
    }

    mostrarRanking();
</script>
```

---

## 🎨 Casos de Uso

### **Para Voluntarios**

1. **Dashboard Personal**: Mostrar al voluntario sus horas acumuladas y nivel de experiencia
2. **Gamificación**: Crear insignias o logros basados en horas completadas
3. **Exportar Certificado**: Generar un certificado con las horas de voluntariado

```javascript
// Ejemplo: Mostrar badge según nivel
async function mostrarBadgeVoluntario(idVoluntario) {
    const data = await obtenerHorasTotales(idVoluntario);

    const badges = {
        Principiante: "🌱 Iniciando tu camino",
        Intermedio: "⭐ Voluntario comprometido",
        Experto: "🏆 Voluntario experimentado",
    };

    console.log(badges[data.nivel_experiencia]);
}
```

### **Para Organizaciones**

1. **Ranking Público**: Mostrar las organizaciones más populares en la home
2. **Marketing**: Las organizaciones pueden destacar su posición en el ranking
3. **Análisis**: Identificar qué organizaciones atraen más voluntarios

```javascript
// Ejemplo: Mostrar si la organización está en el top
async function verificarTopOrganizacion(idOrganizacion) {
    const top3 = await obtenerTopOrganizaciones();
    const enTop = top3.find((org) => org.id_organizacion === idOrganizacion);

    if (enTop) {
        console.log(`¡Felicidades! Estás en la posición ${enTop.posicion}`);
    }
}
```

---

## 📝 Notas Técnicas

### **Optimización SQL**

Ambos endpoints utilizan queries SQL optimizadas:

-   **Horas Totales**: Utiliza Doctrine ORM con `findBy()` y filtrado por estado
-   **Top Organizaciones**: Query SQL nativa con `COUNT(DISTINCT)` y `GROUP BY` para máximo rendimiento

### **Seguridad**

-   **Horas Totales**: Protegido con `checkOwner()` - solo el voluntario puede ver sus propias horas
-   **Top Organizaciones**: Público - cualquiera puede consultar el ranking

### **Escalabilidad**

Si el número de inscripciones crece mucho, considera:

1. Crear un campo `horas_totales` en la tabla `VOLUNTARIO` que se actualice con triggers
2. Cachear el resultado del TOP 3 durante 1 hora
3. Crear índices en las columnas `estado_solicitud` y `deleted_at`

---

## 🧪 Testing

### **Test para Horas Totales**

```php
public function testHorasTotalesVoluntario(): void
{
    $this->client->request(
        'GET',
        '/voluntarios/1/horas-totales',
        [],
        [],
        ['HTTP_X-User-Id' => '1']
    );

    $this->assertResponseIsSuccessful();
    $data = json_decode($this->client->getResponse()->getContent(), true);

    $this->assertArrayHasKey('horas_totales', $data);
    $this->assertArrayHasKey('nivel_experiencia', $data);
    $this->assertIsInt($data['horas_totales']);
}
```

### **Test para Top Organizaciones**

```php
public function testTopOrganizacionesVoluntarios(): void
{
    $this->client->request('GET', '/organizaciones/top-voluntarios');

    $this->assertResponseIsSuccessful();
    $data = json_decode($this->client->getResponse()->getContent(), true);

    $this->assertCount(3, $data); // Máximo 3 resultados
    $this->assertEquals(1, $data[0]['posicion']);
    $this->assertGreaterThanOrEqual($data[1]['total_voluntarios'], $data[0]['total_voluntarios']);
}
```

---

## 📊 Métricas que se Pueden Derivar

Con estos endpoints puedes crear:

### **Para Voluntarios**

-   ⏱️ Promedio de horas por actividad
-   📈 Evolución de horas a lo largo del tiempo
-   🎯 Distancia para alcanzar el siguiente nivel
-   🏅 Comparación con otros voluntarios (percentil)

### **Para Organizaciones**

-   📊 Tasa de retención de voluntarios
-   💡 Popularidad relativa (vs otras organizaciones)
-   🎯 Efectividad en atraer voluntarios
-   📈 Tendencias de crecimiento

---

## ✅ Checklist de Implementación

-   [x] Endpoint GET `/voluntarios/{id}/horas-totales` creado
-   [x] Endpoint GET `/organizaciones/top-voluntarios` creado
-   [x] Documentación OpenAPI añadida
-   [x] Validación de permisos implementada
-   [x] Queries SQL optimizadas
-   [x] Manejo de errores implementado
-   [x] Ejemplos de respuesta documentados
-   [ ] Tests unitarios creados (pendiente)
-   [ ] Cacheo implementado (opcional)

---

## 🚀 ¿Qué sigue?

Posibles mejoras futuras:

1. **Paginación** en el ranking (TOP 10, TOP 50, etc.)
2. **Filtros** por categoría de voluntariado o ODS
3. **Gráficos** de evolución temporal
4. **Exportación** a PDF/Excel
5. **Notificaciones** cuando se alcanza un nuevo nivel
