-- Creación de la tabla de grupos (familias/parejas)
CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Creación de la tabla de invitados
CREATE TABLE IF NOT EXISTS guests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending', -- pending, confirmed, declined
    is_child_menu INTEGER NOT NULL DEFAULT 0, -- 0 = No, 1 = Sí
    is_anonymous INTEGER NOT NULL DEFAULT 0,  -- 0 = No, 1 = Sí (Acompañante sin nombre)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Agregar índices para mejorar el rendimiento de las consultas y búsquedas
CREATE INDEX IF NOT EXISTS idx_guests_group_id ON guests(group_id);
CREATE INDEX IF NOT EXISTS idx_guests_status ON guests(status);
