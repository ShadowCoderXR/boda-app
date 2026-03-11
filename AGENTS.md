# 👰 Protocolo de Desarrollo: Boda-App MVP 2026

## 1. Contexto y Objetivos Críticos

- **Meta**: MVP funcional en <48h para la pareja.
- **Módulos Prioritarios**:
  1. **Gestión de Invitados**: Sistema con Categorías (Familia novio/novia, Amigos, etc.) y Grupos (Familias, Acompañantes).
  2. **Gestión de Padrinos**: Listado simple con roles y tareas tentativas.
  3. **Muro de Inspiración**: Compartir imágenes, paletas de color y links entre los novios.
  4. **Dashboard Estratégico**: Panel con analíticas de RSVP, Quórum (conteo total) y Mapa de Calor por categorías.

## 2. Stack Tecnológico (Mandatorio)

- **Framework**: Laravel 12 con PHP 8.4+.
- **Frontend**: Livewire 4 + Volt (SFC) y Laravel Folio para rutas automáticas.
- **UI/UX**: Flux UI (Tailwind 4) para diseño rápido, minimalista y elegante.
- **Base de Datos**: SQLite con UUID v7 para los invitados.
- **Testing**: Pest (Generar test para cada nueva funcionalidad).

## 3. Flujo de Trabajo y Autonomía de los Agentes

- **Base de Datos**: DEBE soportar Categorías Múltiples (Pivot tables) y Grupos Jerárquicos (Self-referencing parent_id).
- **Roles y Permisos**: Implementar lógica de roles básica (Admin, Ayudante, Invitado) para el Dashboard.
- **Trazabilidad**: Cada cambio en tablas críticas debe registrar el `user_id` que realizó la acción.

## 4. Reglas de Diseño y Funcionalidad

- **UX Amigable**: Usar slugs descriptivos para el acceso de invitados.
- **Flexibilidad RSVP**: Los campos de menú y alergias deben ser extensibles (JSON preferido).
