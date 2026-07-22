const { createClient } = require('@libsql/client');
const sqlite3 = require('sqlite3');
const { open } = require('sqlite');
const path = require('path');
const fs = require('fs');

const TURSO_URL = process.env.TURSO_DATABASE_URL || 'libsql://boda-db-shadowcoderxr.aws-us-east-1.turso.io';
const TURSO_TOKEN = process.env.TURSO_AUTH_TOKEN || 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJpYXQiOjE3ODQ2OTI4NDgsImlkIjoiMDE5Zjg3ZmEtZTgwMS03MmIyLWFmNWMtODhhYWJiNmM2YmI2Iiwia2lkIjoiUEpzV3p0azZKYVZnUUFXOEpoZHR4d0dqQ0w5ZnBYY3hkYmkwcFYycGxWVSIsInJpZCI6IjM4MjI5OTI4LTY1YzYtNDBjMC04YTk2LWM4YTRkNzVjZmY3OCJ9.DbCIJ2c-KHngitYTqGlGSEqPOWnP0VdlwAkSe7mKNAYdP7OJVlbKzqiBj0OjsIJYehAqEry2Y2TVeS2ANUciAQ';

async function migrate() {
  console.log('🚀 Iniciando migración de SQLite local a Turso (libSQL)...');
  console.log(`📡 Conectando a Turso: ${TURSO_URL}`);

  const localDbPath = path.join(__dirname, '..', 'backend', 'db', 'boda.db');
  if (!fs.existsSync(localDbPath)) {
    console.error(`❌ No se encontró la base de datos local en: ${localDbPath}`);
    process.exit(1);
  }

  // 1. Conectar a BD Local (SQLite)
  const localDb = await open({
    filename: localDbPath,
    driver: sqlite3.Database
  });
  console.log('✅ Conexión exitosa a SQLite local.');

  // 2. Conectar a Turso
  const tursoClient = createClient({
    url: TURSO_URL,
    authToken: TURSO_TOKEN
  });

  try {
    // 3. Crear esquema de tablas en Turso
    console.log('⚙️ Creando esquema de tablas en Turso...');
    await tursoClient.executeMultiple(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT UNIQUE NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
      );

      CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
      );

      CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category_id INTEGER,
        phone TEXT,
        priority TEXT DEFAULT 'A',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
      );

      CREATE TABLE IF NOT EXISTS guests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        is_child_menu INTEGER DEFAULT 0,
        is_anonymous INTEGER DEFAULT 0,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS settings (
        key TEXT UNIQUE PRIMARY KEY,
        value TEXT
      );

      CREATE TABLE IF NOT EXISTS support_concepts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
      );

      CREATE TABLE IF NOT EXISTS godparents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        concept_id INTEGER,
        notes TEXT,
        FOREIGN KEY (concept_id) REFERENCES support_concepts(id) ON DELETE SET NULL
      );
    `);
    console.log('✅ Esquema creado con éxito en Turso.');

    // 4. Migrar schema_migrations
    const migrations = await localDb.all('SELECT * FROM schema_migrations');
    for (const m of migrations) {
      await tursoClient.execute({
        sql: `INSERT INTO schema_migrations (id, filename, applied_at) VALUES (?, ?, ?)
              ON CONFLICT(filename) DO NOTHING`,
        args: [m.id, m.filename, m.applied_at]
      });
    }
    console.log(`📦 Migraciones históricas transferidas (${migrations.length} registros).`);

    // 5. Migrar categories
    const categories = await localDb.all('SELECT * FROM categories');
    for (const c of categories) {
      await tursoClient.execute({
        sql: `INSERT INTO categories (id, name) VALUES (?, ?)
              ON CONFLICT(id) DO UPDATE SET name = excluded.name`,
        args: [c.id, c.name]
      });
    }
    console.log(`🏷️ Categorías transferidas (${categories.length} registros).`);

    // 6. Migrar groups
    const groups = await localDb.all('SELECT * FROM groups');
    for (const g of groups) {
      await tursoClient.execute({
        sql: `INSERT INTO groups (id, name, category_id, phone, priority, created_at) VALUES (?, ?, ?, ?, ?, ?)
              ON CONFLICT(id) DO UPDATE SET 
                name = excluded.name, 
                category_id = excluded.category_id, 
                phone = excluded.phone, 
                priority = excluded.priority`,
        args: [g.id, g.name, g.category_id, g.phone, g.priority || 'A', g.created_at]
      });
    }
    console.log(`👨‍👩‍👧‍👦 Grupos/Familias transferidos (${groups.length} registros).`);

    // 7. Migrar guests
    const guests = await localDb.all('SELECT * FROM guests');
    for (const gu of guests) {
      await tursoClient.execute({
        sql: `INSERT INTO guests (id, group_id, name, status, is_child_menu, is_anonymous) VALUES (?, ?, ?, ?, ?, ?)
              ON CONFLICT(id) DO UPDATE SET 
                name = excluded.name, 
                status = excluded.status, 
                is_child_menu = excluded.is_child_menu, 
                is_anonymous = excluded.is_anonymous`,
        args: [gu.id, gu.group_id, gu.name, gu.status, gu.is_child_menu, gu.is_anonymous]
      });
    }
    console.log(`👥 Invitados transferidos (${guests.length} registros).`);

    // 8. Migrar settings
    const settings = await localDb.all('SELECT * FROM settings');
    for (const s of settings) {
      await tursoClient.execute({
        sql: `INSERT INTO settings (key, value) VALUES (?, ?)
              ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
        args: [s.key, s.value]
      });
    }
    console.log(`⚙️ Configuraciones transferidas (${settings.length} registros).`);

    // 9. Migrar support_concepts
    const concepts = await localDb.all('SELECT * FROM support_concepts');
    for (const sc of concepts) {
      await tursoClient.execute({
        sql: `INSERT INTO support_concepts (id, name) VALUES (?, ?)
              ON CONFLICT(id) DO UPDATE SET name = excluded.name`,
        args: [sc.id, sc.name]
      });
    }
    console.log(`🏷️ Conceptos de apoyo transferidos (${concepts.length} registros).`);

    // 10. Migrar godparents
    const godparents = await localDb.all('SELECT * FROM godparents');
    for (const gp of godparents) {
      await tursoClient.execute({
        sql: `INSERT INTO godparents (id, name, concept_id, notes) VALUES (?, ?, ?, ?)
              ON CONFLICT(id) DO UPDATE SET 
                name = excluded.name, 
                concept_id = excluded.concept_id, 
                notes = excluded.notes`,
        args: [gp.id, gp.name, gp.concept_id, gp.notes]
      });
    }
    console.log(`🎁 Padrinos transferidos (${godparents.length} registros).`);

    console.log('\n🎉 ¡MIGRACIÓN COMPLETADA CON ÉXITO A TURSO!');
    console.log('Todos tus datos reales se han replicado de forma intacta en la base de datos remota.');
  } catch (error) {
    console.error('❌ Error durante la migración a Turso:', error);
  } finally {
    await localDb.close();
  }
}

migrate();
