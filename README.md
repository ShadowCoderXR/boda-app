# App de Bodas 💍 - Planificador de Invitados

Herramienta modular, dockerizada y de diseño elegante para gestionar los invitados de tu boda, agruparlos en familias o parejas, y controlar sus confirmaciones y requerimientos especiales.

---

## 🚀 Cómo Iniciar en Local (Desarrollo)

### Requisitos previos:
*   Node.js (v18+)
*   npm

### Pasos para iniciar:

1.  **Instalar dependencias del Backend e iniciar**:
    ```bash
    cd backend
    npm install
    npm start
    ```
    *El backend iniciará en el puerto 3000 y ejecutará automáticamente las migraciones.*

2.  **Instalar dependencias del Frontend e iniciar**:
    ```bash
    cd ../frontend
    npm install
    npm run dev
    ```
    *El frontend Vite iniciará en su puerto por defecto (normalmente 5173).*

---

## 🐳 Cómo Desplegar con Docker

Para correr toda la aplicación compilada en producción (Frontend y Backend sirviendo en el puerto 3000):

```bash
docker build -t boda-app .
docker run -d -p 3000:3000 -v /ruta/en/tu/host/db:/app/db --name boda-planner boda-app
```

> [!IMPORTANT]
> Recuerda mapear el volumen `/app/db` a un directorio persistente en tu máquina o servidor para no perder la base de datos `boda.db` cuando actualices la imagen.

---

## 📁 Reportes de Incidencias e Imágenes (QA)
Para cualquier problema visual, bug o prueba de QA, puedes dejar tus notas y capturas de pantalla en la carpeta `reportes/`.
