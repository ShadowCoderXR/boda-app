# Reporte de QA (Control de Calidad) - MVP App de Boda 💍

Este documento detalla las pruebas realizadas para el Producto Mínimo Viable (MVP) y valida el correcto funcionamiento de los módulos de la aplicación, incluyendo la gestión de categorías, teléfonos, prioridades e invitaciones digitales personalizadas de confirmación.

---

## 🧪 Resumen de Pruebas Realizadas

| ID Prueba | Caso de Prueba | Entrada de Prueba | Comportamiento Esperado | Resultado | Estado |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-001** | Inicialización de BD y Migraciones | Arranque inicial del servidor backend | Creación de base de datos SQLite y ejecución automática del script `001_init.sql` | Base de datos creada en `backend/db/boda.db`, tablas `groups` y `guests` configuradas con índices. | **APROBADO** |
| **TC-002** | Creación de Grupo e Invitados | POST a `/api/groups` con invitados nombrados y anónimos | Creación del grupo, vinculación de invitados y asignación automática del nombre "Acompañante Anónimo" para `is_anonymous = true`. | Grupo guardado. Los invitados se insertaron y enlazaron correctamente. | **APROBADO** |
| **TC-003** | Cálculo de Métricas (Dashboard) | GET a `/api/summary` | Conteo dinámico de confirmados, rechazados, pendientes, adultos y niños (menús infantiles). | Métricas calculadas con precisión (Niños se cuentan solo si están confirmados). | **APROBADO** |
| **TC-004** | Ciclo de Confirmación e Interactividad | PUT a `/api/guests/:id` alternando estados | Cambio inmediato del estado de confirmación e impacto en tiempo real en las métricas. | Estatus actualizado sin afectar el resto de los campos. | **APROBADO** |
| **TC-005** | Eliminación en Cascada | DELETE a `/api/groups/:id` | Eliminación de un grupo y de todos los invitados enlazados en la base de datos de forma automática. | El grupo y sus invitados se eliminaron por completo (integridad referencial activa). | **APROBADO** |
| **TC-006** | Migración de Categorías sin Pérdida de Datos | Aplicación automática de `002_add_categories.sql` en base existente | Creación de tabla `categories` con datos por defecto y columna `category_id` en `groups` sin alterar los registros existentes. | Los datos anteriores se conservaron intactos con `category_id = null`. Categorías por defecto insertadas con éxito. | **APROBADO** |
| **TC-007** | Creación de Categoría Dinámica | POST a `/api/categories` con nombre de categoría | Creación e inserción de la nueva categoría en el sistema para estar disponible de inmediato. | Categoría creada con id y nombre correspondiente, integrada al listado dinámico. | **APROBADO** |
| **TC-008** | Asignación y Edición de Categoría | POST o PUT a `/api/groups/:id` enviando `category_id` | Almacenamiento y actualización de la categoría asignada al grupo de invitados. | El grupo vincula la categoría y devuelve el nombre de la categoría resuelto al consultarlo. | **APROBADO** |
| **TC-009** | Filtro por Categorías | Selección de categoría en menú de listado | Filtrar los grupos mostrados en pantalla en base a su categoría asignada. | El frontend filtra dinámicamente mostrando únicamente los grupos de la categoría seleccionada. | **APROBADO** |
| **TC-010** | Registro de Celular por Grupo | Creación de grupo con input celular | Almacenar el número de celular del grupo para futuros envíos en la columna `phone` en SQLite. | El celular se almacena correctamente y se muestra en la tarjeta del grupo. | **APROBADO** |
| **TC-011** | Endpoint de Invitación Unitaria | GET a `/api/groups/:id` | Retorna los detalles y la lista de invitados únicamente de ese grupo específico, manteniendo la privacidad general. | Datos del grupo e invitados enlazados devueltos correctamente en JSON. | **APROBADO** |
| **TC-012** | Enrutamiento de Invitaciones | Acceder a `/invitacion/:id` en el navegador | Detectar la ruta en el frontend y renderizar la vista de confirmación en lugar de la consola de administración. | La vista de invitación se renderiza y lee los datos específicos de la API del grupo de forma aislada. | **APROBADO** |
| **TC-013** | Confirmación Uno a Uno en Invitación | Clic en "Asistiré" / "No podré ir" desde la invitación | Ejecutar peticiones PUT a la API actualizando individualmente la confirmación de cada invitado. | Estado guardado en tiempo real en la base de datos local SQLite. | **APROBADO** |
| **TC-014** | Personalización de Invitados Anónimos | Input de texto sobre el acompañante anónimo en la invitación | Guardar el nombre real ingresado por el invitado en lugar del comodín "Acompañante Anónimo". | Al perder foco el input (onBlur), la API recibe el nuevo nombre y actualiza la base de datos manteniendo la marca `is_anonymous`. | **APROBADO** |
| **TC-015** | Integración de WhatsApp y Enlaces | Clic en "Enviar por WhatsApp" | Abrir ventana externa a WhatsApp Web pre-cargando el mensaje con la URL de la invitación respectiva. | Enlace y mensaje formateados de forma directa y listos para enviar. | **APROBADO** |
| **TC-016** | Migración y Gestión de Prioridades | Aplicación automática de `004_add_priority_to_groups.sql` | Agregar columna `priority` a grupos en base existente y rellenar con valor por defecto 'A'. | Columna agregada y grupos anteriores asignados con Prioridad A de forma correcta. | **APROBADO** |
| **TC-017** | Edición y Filtrado de Prioridad | PUT a `/api/groups/:id` con prioridad 'B' o 'C' | Permitir cambiar la fase de invitación (prioridad) del grupo y filtrar por ella en el listado. | Prioridad actualizada. El filtro del dashboard funciona de forma instantánea. | **APROBADO** |
| **TC-018** | Tarjeta de Lugares Restantes | Cálculo de lugares disponibles en base a capacidad de 100 | Calcular dinámicamente: `100 - (Confirmados + Pendientes)`. | La métrica refleja cuántos lugares quedan disponibles para enviar nuevas invitaciones. | **APROBADO** |

---

## 🛠️ Validación de Endpoints (Pruebas API)

### 1. Obtener Grupos con Prioridades (`GET /api/groups`)
*   **Comando:** `Invoke-RestMethod -Uri "http://localhost:3000/api/groups" -Method Get`
*   **Respuesta:** Cada grupo incluye la llave `"priority": "A"`.

### 2. Modificación de Prioridad a "B" (`PUT /api/groups/3`)
*   **Comando:** `Invoke-RestMethod -Uri "http://localhost:3000/api/groups/3" -Method Put -ContentType "application/json" -Body '{"priority": "B"}'`
*   **Respuesta:** Campo `"priority": "B"` retornado en JSON y persistido en SQLite.

---

## 🎨 Validación Visual y Modo Oscuro
*   **Badges de Prioridad**: Cada tarjeta de grupo muestra un badge distintivo: verde para Prioridad A (Fase 1), naranja para Prioridad B (Fase 2) y gris para Prioridad C (Fase 3), facilitando el control de los envíos.
*   **Tarjeta Lugares Restantes**: Se implementó una tarjeta especial en el dashboard con borde Borgoña intenso y fondo degradado, alertando visualmente cuántos lugares libres quedan en el salón para invitar a las fases secundarias.
