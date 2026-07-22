-- Agregar columna priority (prioridad de grupo: A, B, C) a la tabla groups
ALTER TABLE groups ADD COLUMN priority TEXT DEFAULT 'A';
