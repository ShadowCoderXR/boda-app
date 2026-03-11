# 🏗️ Arquitectura de Datos: Boda-App

## 1. Entidades y Relaciones Complejas

### A. Invitados (`guests`) y Grupos (`groups`)

- **Jerarquía de Grupos**: Los grupos deben permitir recursividad mediante un `parent_id` (Self-referencing). Ejemplo: "Tío Silva" -> Subgrupo "Familia Silva Fernández" -> Grupo "Familia Silva".
- **Identificación**: Uso obligatorio de UUID v7 y un `slug` amigable para la URL de invitación pública.
- **Acompañantes**: El sistema debe permitir invitados individuales que pertenezcan a un subgrupo grande o invitados que tengan un "límite de acompañantes" definible (plus_one_limit).

### B. Categorización Dinámica (Etiquetado)

- **Relación Muchos-a-Muchos**: Un invitado puede pertenecer a múltiples categorías (ej. Familia Novio + Amigos Comunes). Esto es crítico para la futura organización de mesas.

### C. Módulo de Padrinos

- **Roles**: Los padrinos son invitados con privilegios extra y roles asignados (Damas, Escoltas, etc.).
- **Tareas**: Relación 1:N entre Padrino y Tareas, con estados: `tentative`, `confirmed`, `done`.

### D. Muro de Inspiración

- **Tipos**: Soporte para imágenes (storage local), links (Pinterest, TikTok, YouTube) y paletas de color (HEX).
- **Favoritos**: Marcado de elementos favoritos para priorizar la visualización.

## 2. Lógica de Operación

- **RSVP Flexible**: El esquema debe usar campos JSON para menú y alergias, ya que los parámetros definitivos aún no se conocen.
- **Trazabilidad de Cambios**: Cada registro debe incluir `created_by` y `updated_by` vinculados al usuario que realizó la acción (Novio, Novia o Ayudantes).
- **Acceso Amigable**: Priorizar el uso de Slugs en las rutas de Folio para que los invitados menos técnicos puedan acceder fácilmente.
