# Estado del Proyecto: App de Boda 💍

## MVP - Módulo de Invitados y Confirmaciones (Con Categorías, Celulares e Invitación Personalizada)

### Progreso General: ✅ Completado

---

## 📋 Control de Tareas

### 🛠️ Backend (Express + SQLite)
- [x] Inicializar proyecto de Node.js y dependencias (`express`, `sqlite3`, `sqlite`, `cors`).
- [x] Configurar base de datos SQLite con cargador de migraciones automático.
- [x] Crear migración inicial (`001_init.sql` con tablas `groups` y `guests`).
- [x] Crear servidor Express con endpoints API REST:
  - `GET /api/summary`: Estadísticas del dashboard (Confirmados, Niños, Pendientes, etc.).
  - `GET /api/groups`: Obtener todos los grupos con sus invitados y categorías.
  - `GET /api/groups/:id`: Obtener un grupo único con sus invitados para la invitación.
  - `POST /api/groups`: Crear un nuevo grupo con invitados, categoría y número de celular.
  - `PUT /api/groups/:id`: Editar nombre, categoría y celular de un grupo.
  - `DELETE /api/groups/:id`: Eliminar un grupo y sus invitados.
  - `POST /api/guests`: Agregar un invitado a un grupo existente.
  - `PUT /api/guests/:id`: Actualizar datos de un invitado (nombre, confirmación, menú de niños, etc.).
  - `DELETE /api/guests/:id`: Eliminar un invitado.
- [x] Añadir soporte de categorías:
  - Crear tabla `categories` con migración `002_add_categories.sql`.
- [x] Añadir columna de teléfono celular en grupos:
  - Modificar base de datos con migración `003_add_phone_to_groups.sql`.
- [x] Configurar servidor Express para servir archivos estáticos del frontend.

### 🎨 Frontend (React + Vite + CSS)
- [x] Inicializar proyecto de React con Vite.
- [x] Configurar sistema de diseño CSS moderno (variables, paleta Champagne/Burgundy/Emerald, fuentes elegantes).
- [x] Crear componente `Dashboard`: Tarjetas con estadísticas animadas.
- [x] Crear componente `GuestList`: Lista de grupos con desglose de sus invitados.
- [x] Crear componente `GroupForm`: Modal o formulario para agregar grupos e invitados.
- [x] Implementar gestión visual de categorías y celulares:
  - Desplegable de selección en formulario y modal de edición.
  - Campo obligatorio de "Celular" en la creación y edición.
  - Badge visual de categoría en la tarjeta del grupo.
  - Filtro por categoría junto al buscador.
- [x] Implementar enlaces y envío de invitaciones:
  - Botón "🔗 Copiar Enlace" para copiar la URL de invitación al portapapeles.
  - Botón "💬 Enviar por WhatsApp" que abre la API de WhatsApp con un mensaje predeterminado y el enlace.
- [x] Crear la Vista de Invitación Personalizada (`/invitacion/:id`):
  - Detección de ruta en el cliente para cambiar la pantalla al modo invitado.
  - Mostrar detalles específicos del grupo (nombre y miembros) de forma privada.
  - Permitir a los invitados confirmar o declinar de forma individual (uno a uno).
  - Permitir a los invitados renombrar a los Acompañantes Anónimos (+1) directamente en la tarjeta.
  - Permitir a los invitados seleccionar menú infantil 👶 si están confirmados.
  - Guardado en tiempo real con indicador dinámico (Guardando... / Guardado con éxito).

### 🐳 DevOps y Dockerización
- [x] Configurar `Dockerfile` multi-etapa para construir frontend y empaquetarlo en el backend.
- [x] Configurar `docker-compose.yml` para facilitar pruebas y desarrollo local.
- [x] Crear volumen persistente para la base de datos en `/app/db`.

### 📝 Documentación y QA
- [x] Crear carpeta `reportes/` para incidencias y QA.
- [x] Redactar `ARCHITECTURE.md` para futuras referencias de IA y desarrollo.
- [x] Redactar `README.md` con instrucciones precisas de arranque y despliegue.
- [x] Realizar pruebas de control de calidad (QA) y documentarlas en `reportes/qa_test_report.md`.
