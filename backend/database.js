const { createClient } = require('@libsql/client');
const fs = require('fs');
const path = require('path');

let dbClient = null;

function getClient() {
  if (dbClient) return dbClient;

  const url = process.env.TURSO_DATABASE_URL || 'libsql://boda-db-shadowcoderxr.aws-us-east-1.turso.io';
  const authToken = process.env.TURSO_AUTH_TOKEN || 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJpYXQiOjE3ODQ2OTI4NDgsImlkIjoiMDE5Zjg3ZmEtZTgwMS03MmIyLWFmNWMtODhhYWJiNmM2YmI2Iiwia2lkIjoiUEpzV3p0azZKYVZnUUFXOEpoZHR4d0dqQ0w5ZnBYY3hkYmkwcFYycGxWVSIsInJpZCI6IjM4MjI5OTI4LTY1YzYtNDBjMC04YTk2LWM4YTRkNzVjZmY3OCJ9.DbCIJ2c-KHngitYTqGlGSEqPOWnP0VdlwAkSe7mKNAYdP7OJVlbKzqiBj0OjsIJYehAqEry2Y2TVeS2ANUciAQ';

  dbClient = createClient({ url, authToken });
  return dbClient;
}

/**
 * Adaptador compatible con SQLite (get, all, run, exec) respaldado por @libsql/client (Turso).
 */
const dbAdapter = {
  async get(sql, args = []) {
    const client = getClient();
    const result = await client.execute({ sql, args });
    return result.rows.length > 0 ? result.rows[0] : undefined;
  },

  async all(sql, args = []) {
    const client = getClient();
    const result = await client.execute({ sql, args });
    return result.rows;
  },

  async run(sql, args = []) {
    const client = getClient();
    const result = await client.execute({ sql, args });
    return {
      lastID: result.lastInsertRowid ? Number(result.lastInsertRowid) : undefined,
      changes: result.rowsAffected
    };
  },

  async exec(sql) {
    const client = getClient();
    await client.executeMultiple(sql);
  }
};

let initialized = false;

/**
 * Obtiene el adaptador de base de datos Turso de forma singleton.
 */
async function getDb() {
  if (!initialized) {
    await runMigrations(dbAdapter);
    initialized = true;
    console.log('✅ Conexión a Turso (libSQL) activa y migraciones verificadas.');
  }
  return dbAdapter;
}

/**
 * Escanea y ejecuta archivos .sql de migración pendientes directamente en Turso.
 */
async function runMigrations(db) {
  await db.run(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      filename TEXT UNIQUE NOT NULL,
      applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
  `);

  const migrationsDir = path.join(__dirname, 'migrations');
  if (!fs.existsSync(migrationsDir)) return;

  const files = fs.readdirSync(migrationsDir)
    .filter(file => file.endsWith('.sql'))
    .sort();

  for (const file of files) {
    const row = await db.get(
      'SELECT 1 FROM schema_migrations WHERE filename = ?',
      [file]
    );

    if (!row) {
      console.log(`Ejecutando migración pendiente en Turso: ${file}...`);
      const script = fs.readFileSync(path.join(migrationsDir, file), 'utf8');

      try {
        await db.exec(script);
        await db.run(
          'INSERT INTO schema_migrations (filename) VALUES (?)',
          [file]
        );
        console.log(`Migración ${file} aplicada con éxito en Turso.`);
      } catch (err) {
        console.error(`Fallo crítico al aplicar migración ${file} en Turso.`, err);
        throw err;
      }
    }
  }
}

module.exports = { getDb };
