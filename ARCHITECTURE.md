# Arquitectura del Sistema - App de Boda 💍

Este documento proporciona a futuros agentes e ingenieros la visión técnica de la aplicación, su estructura de datos y cómo interactúan las piezas.

---

## 🏗️ Estructura General

La aplicación está estructurada como un **Monorepo** que consolida el frontend y el backend para facilitar la compilación y la dockerización en un contenedor único y ligero.

```
wedding-app/
├── backend/                  # Servidor Express y Base de Datos SQLite
│   ├── db/                   # Almacenamiento persistente del archivo SQLite
│   ├── migrations/           # Scripts SQL ejecutados en orden secuencial
│   └── server.js             # Entrada del servidor y endpoints API REST
└── frontend/                 # Aplicación React construida con Vite
    └── src/                  # Componentes y Estilos visuales
```

---

## 🗄️ Estrategia de Base de Datos y Migraciones

La base de datos utiliza **SQLite** y reside en un archivo local (`backend/db/boda.db`).

### Sistema de Migraciones Automáticas
Para evitar la pérdida de datos al agregar tablas o columnas, implementamos una sincronización basada en archivos `.sql` ordenados numéricamente en `backend/migrations/`.

1.  **Ejecución**: Al iniciar el servidor Node.js (`backend/server.js`), se lee el directorio de migraciones.
2.  **Validación**: Se compara cada archivo con la tabla local `schema_migrations`.
3.  **Aplicación**: Si un archivo de migración no ha sido registrado, se ejecuta dentro de una transacción y se guarda su registro en `schema_migrations`.

---

## 🐳 Dockerización y Despliegue

La aplicación se despliega mediante un **Dockerfile multi-etapa** (Multi-stage Build):

1.  **Etapa de Compilación**:
    *   Utiliza una imagen base de Node para construir el frontend React (`npm run build`).
    *   Esto genera los archivos listos para producción en `frontend/dist/`.
2.  **Etapa Ejecutable**:
    *   Utiliza otra imagen base de Node para configurar el servidor de producción.
    *   Copia los archivos compilados del frontend a la carpeta pública del backend.
    *   Instala únicamente las dependencias necesarias de producción del backend.
    *   Expone el puerto `3000` y expone el volumen `/app/db/` para asegurar la persistencia de `boda.db` en el host.

---

## 🎨 Principios de Diseño Visual

La interfaz está construida siguiendo lineamientos prémium:
*   **Colores HSL Dinámicos**: Permiten transiciones limpias y contrastes elevados.
*   **Efecto de Cristal (Glassmorphism)**: Paneles con blur translúcido para tarjetas y modales.
*   **Animaciones Micro**: Transiciones en botones, cambios de estado y colapsado de menús con aceleraciones fluidas (`cubic-bezier`).
