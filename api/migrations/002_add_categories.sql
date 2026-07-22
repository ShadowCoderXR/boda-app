-- Creación de la tabla de categorías para los grupos
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insertar categorías predeterminadas
INSERT OR IGNORE INTO categories (name) VALUES ('Familia de la Novia');
INSERT OR IGNORE INTO categories (name) VALUES ('Familia del Novio');
INSERT OR IGNORE INTO categories (name) VALUES ('Amigos de la Novia');
INSERT OR IGNORE INTO categories (name) VALUES ('Amigos del Novio');

-- Agregar columna category_id a la tabla groups
ALTER TABLE groups ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
