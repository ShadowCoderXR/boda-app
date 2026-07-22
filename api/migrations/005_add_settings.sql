-- Crear tabla de configuración (settings) si no existe
CREATE TABLE IF NOT EXISTS settings (
  key TEXT UNIQUE PRIMARY KEY,
  value TEXT
);

-- Insertar valor por defecto para max_guests
INSERT INTO settings (key, value) VALUES ('max_guests', '100')
  ON CONFLICT(key) DO NOTHING;
