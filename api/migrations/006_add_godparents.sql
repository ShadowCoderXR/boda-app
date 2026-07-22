-- Crear tabla de conceptos de apoyo / padrinos (p.ej. Anillos, Arras, Lazo)
CREATE TABLE IF NOT EXISTS support_concepts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL
);

-- Insertar algunos conceptos predeterminados de boda
INSERT INTO support_concepts (name) VALUES 
  ('Anillos'),
  ('Arras'),
  ('Lazo'),
  ('Velación'),
  ('Pastel'),
  ('Música / DJ'),
  ('Flores / Decoración'),
  ('Fotografía / Video'),
  ('Bebidas / Barra Libre'),
  ('Mesa de Dulces')
ON CONFLICT(name) DO NOTHING;

-- Crear tabla de padrinos
CREATE TABLE IF NOT EXISTS godparents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  concept_id INTEGER,
  notes TEXT,
  FOREIGN KEY (concept_id) REFERENCES support_concepts(id) ON DELETE SET NULL
);
