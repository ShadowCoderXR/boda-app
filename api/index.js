const express = require('express');
const cors = require('cors');
const path = require('path');
const { getDb } = require('../backend/database');

const app = express();

app.use(cors());
app.use(express.json());

// --- CONFIGURACIÓN DE SEGURIDAD Y LOGIN ---
const ADMIN_TOKEN = process.env.ADMIN_TOKEN || 'boda2026-secret-admin-session-token';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'boda'; // Contraseña default 'boda'

function requireAdmin(req, res, next) {
  if (req.method === 'OPTIONS') {
    return next();
  }
  const authHeader = req.headers['authorization'];
  if (!authHeader) {
    return res.status(401).json({ error: 'Acceso no autorizado. Falta token.' });
  }
  const token = authHeader.startsWith('Bearer ') ? authHeader.substring(7) : authHeader;
  if (token !== ADMIN_TOKEN) {
    return res.status(403).json({ error: 'Acceso denegado. Token inválido.' });
  }
  next();
}

/**
 * POST /api/auth/login
 * Endpoint público de login.
 */
app.post('/api/auth/login', (req, res) => {
  const { username, password } = req.body;
  if (username === ADMIN_USER && password === ADMIN_PASSWORD) {
    return res.json({ token: ADMIN_TOKEN });
  }
  return res.status(401).json({ error: 'Usuario o contraseña incorrectos.' });
});

/**
 * GET /api/summary
 * Estadísticas generales para el dashboard.
 */
app.get('/api/summary', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    
    const stats = await db.get(`
      SELECT 
        COUNT(*) as totalGuests,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmedGuests,
        SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declinedGuests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendingGuests,
        SUM(CASE WHEN status = 'confirmed' AND is_child_menu = 1 THEN 1 ELSE 0 END) as confirmedChildMenus,
        SUM(CASE WHEN status = 'confirmed' AND is_child_menu = 0 THEN 1 ELSE 0 END) as confirmedAdults
      FROM guests
    `);

    const groupCount = await db.get('SELECT COUNT(*) as totalGroups FROM groups');

    let maxGuests = 100;
    try {
      const maxGuestsRow = await db.get("SELECT value FROM settings WHERE key = 'max_guests'");
      if (maxGuestsRow) {
        maxGuests = Number(maxGuestsRow.value);
      }
    } catch (dbErr) {
      console.warn('La tabla settings podría no estar lista aún:', dbErr.message);
    }

    const priorityCount = { A: 0, B: 0, C: 0 };
    try {
      const priorityStats = await db.all(`
        SELECT 
          COALESCE(g.priority, 'A') as priority,
          COUNT(u.id) as guestCount
        FROM groups g
        LEFT JOIN guests u ON g.id = u.group_id
        WHERE u.id IS NOT NULL
        GROUP BY COALESCE(g.priority, 'A')
      `);
      for (const row of priorityStats) {
        if (row.priority && priorityCount[row.priority] !== undefined) {
          priorityCount[row.priority] = row.guestCount || 0;
        }
      }
    } catch (dbErr) {
      console.warn('Error al calcular conteo por prioridades:', dbErr.message);
    }

    res.json({
      totalGroups: groupCount ? (groupCount.totalGroups || 0) : 0,
      totalGuests: stats ? (stats.totalGuests || 0) : 0,
      confirmedGuests: stats ? (stats.confirmedGuests || 0) : 0,
      declinedGuests: stats ? (stats.declinedGuests || 0) : 0,
      pendingGuests: stats ? (stats.pendingGuests || 0) : 0,
      confirmedChildMenus: stats ? (stats.confirmedChildMenus || 0) : 0,
      confirmedAdults: stats ? (stats.confirmedAdults || 0) : 0,
      maxGuests: maxGuests,
      priorityCount: priorityCount
    });
  } catch (error) {
    console.error('Error en GET /api/summary:', error);
    res.status(500).json({ error: 'Error al obtener el resumen estadístico.' });
  }
});

/**
 * GET /api/settings
 * Obtiene todas las configuraciones guardadas en bd.
 */
app.get('/api/settings', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    const rows = await db.all('SELECT * FROM settings');
    const settings = {};
    for (const row of rows) {
      settings[row.key] = row.value;
    }
    if (!settings.max_guests) {
      settings.max_guests = '100';
    }
    res.json(settings);
  } catch (error) {
    console.error('Error en GET /api/settings:', error);
    res.status(500).json({ error: 'Error al obtener la configuración.' });
  }
});

/**
 * POST /api/settings
 * Guarda o actualiza configuraciones.
 */
app.post('/api/settings', requireAdmin, async (req, res) => {
  const { max_guests } = req.body;
  if (max_guests === undefined || isNaN(Number(max_guests)) || Number(max_guests) < 1) {
    return res.status(400).json({ error: 'El máximo de invitados debe ser un número válido mayor a 0.' });
  }

  try {
    const db = await getDb();
    await db.run(
      'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
      ['max_guests', String(max_guests)]
    );
    res.json({ success: true, max_guests: Number(max_guests) });
  } catch (error) {
    console.error('Error en POST /api/settings:', error);
    res.status(500).json({ error: 'Error al guardar la configuración.' });
  }
});

/**
 * GET /api/groups
 * Obtiene todos los grupos con sus respectivos invitados agregados.
 */
app.get('/api/groups', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    
    const rows = await db.all(`
      SELECT 
        g.id as group_id, 
        g.name as group_name, 
        g.phone as group_phone,
        g.priority as group_priority,
        g.created_at as group_created_at,
        g.category_id as group_category_id,
        c.name as category_name,
        u.id as guest_id, 
        u.name as guest_name, 
        u.status, 
        u.is_child_menu, 
        u.is_anonymous
      FROM groups g 
      LEFT JOIN categories c ON g.category_id = c.id
      LEFT JOIN guests u ON g.id = u.group_id 
      ORDER BY g.created_at DESC, u.id ASC
    `);

    const groupsMap = {};
    for (const row of rows) {
      if (!groupsMap[row.group_id]) {
        groupsMap[row.group_id] = {
          id: row.group_id,
          name: row.group_name,
          phone: row.group_phone,
          priority: row.group_priority,
          created_at: row.group_created_at,
          category_id: row.group_category_id,
          category_name: row.category_name,
          guests: []
        };
      }
      if (row.guest_id) {
        groupsMap[row.group_id].guests.push({
          id: row.guest_id,
          group_id: row.group_id,
          name: row.guest_name,
          status: row.status,
          is_child_menu: row.is_child_menu === 1,
          is_anonymous: row.is_anonymous === 1
        });
      }
    }

    res.json(Object.values(groupsMap));
  } catch (error) {
    console.error('Error en GET /api/groups:', error);
    res.status(500).json({ error: 'Error al obtener la lista de grupos.' });
  }
});

/**
 * GET /api/groups/:id
 * Obtiene un grupo específico con sus invitados para la página de invitación.
 */
app.get('/api/groups/:id', async (req, res) => {
  const { id } = req.params;
  try {
    const db = await getDb();
    const rows = await db.all(`
      SELECT 
        g.id as group_id, 
        g.name as group_name, 
        g.phone as group_phone,
        g.priority as group_priority,
        g.created_at as group_created_at,
        g.category_id as group_category_id,
        c.name as category_name,
        u.id as guest_id, 
        u.name as guest_name, 
        u.status, 
        u.is_child_menu, 
        u.is_anonymous
      FROM groups g 
      LEFT JOIN categories c ON g.category_id = c.id
      LEFT JOIN guests u ON g.id = u.group_id 
      WHERE g.id = ?
      ORDER BY u.id ASC
    `, [id]);

    if (rows.length === 0) {
      return res.status(404).json({ error: 'Grupo no encontrado.' });
    }

    const group = {
      id: rows[0].group_id,
      name: rows[0].group_name,
      phone: rows[0].group_phone,
      priority: rows[0].group_priority,
      created_at: rows[0].group_created_at,
      category_id: rows[0].group_category_id,
      category_name: rows[0].category_name,
      guests: []
    };

    for (const row of rows) {
      if (row.guest_id) {
        group.guests.push({
          id: row.guest_id,
          group_id: row.group_id,
          name: row.guest_name,
          status: row.status,
          is_child_menu: row.is_child_menu === 1,
          is_anonymous: row.is_anonymous === 1
        });
      }
    }

    res.json(group);
  } catch (error) {
    console.error('Error en GET /api/groups/:id:', error);
    res.status(500).json({ error: 'Error al obtener el grupo de invitados.' });
  }
});

/**
 * POST /api/groups
 */
app.post('/api/groups', requireAdmin, async (req, res) => {
  const { name, guests, category_id, phone, priority } = req.body;
  if (!name || typeof name !== 'string' || name.trim() === '') {
    return res.status(400).json({ error: 'El nombre del grupo es obligatorio.' });
  }

  try {
    const db = await getDb();
    
    const groupResult = await db.run(
      'INSERT INTO groups (name, category_id, phone, priority) VALUES (?, ?, ?, ?)',
      [name.trim(), category_id || null, phone ? phone.trim() : null, priority || 'A']
    );
    const groupId = groupResult.lastID;
    
    const insertedGuests = [];
    if (Array.isArray(guests)) {
      for (const guest of guests) {
        const guestName = guest.name ? guest.name.trim() : (guest.is_anonymous ? 'Acompañante Anónimo' : '');
        if (!guestName) continue;

        const status = guest.status || 'pending';
        const isChildMenu = guest.is_child_menu ? 1 : 0;
        const isAnonymous = guest.is_anonymous ? 1 : 0;

        const guestResult = await db.run(
          `INSERT INTO guests (group_id, name, status, is_child_menu, is_anonymous) 
           VALUES (?, ?, ?, ?, ?)`,
          [groupId, guestName, status, isChildMenu, isAnonymous]
        );

        insertedGuests.push({
          id: guestResult.lastID,
          group_id: groupId,
          name: guestName,
          status,
          is_child_menu: isChildMenu === 1,
          is_anonymous: isAnonymous === 1
        });
      }
    }

    let categoryName = null;
    if (category_id) {
      const cat = await db.get('SELECT name FROM categories WHERE id = ?', [category_id]);
      if (cat) categoryName = cat.name;
    }

    res.status(201).json({
      id: groupId,
      name: name.trim(),
      category_id: category_id || null,
      category_name: categoryName,
      phone: phone ? phone.trim() : null,
      priority: priority || 'A',
      guests: insertedGuests
    });
  } catch (error) {
    console.error('Error en POST /api/groups:', error);
    res.status(500).json({ error: 'Error al crear el grupo de invitados.' });
  }
});

/**
 * PUT /api/groups/:id
 */
app.put('/api/groups/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  const { name, category_id, phone, priority } = req.body;

  try {
    const db = await getDb();
    
    const current = await db.get('SELECT * FROM groups WHERE id = ?', [id]);
    if (!current) {
      return res.status(404).json({ error: 'Grupo no encontrado.' });
    }

    const updatedName = name !== undefined ? name.trim() : current.name;
    const updatedCategoryId = category_id !== undefined ? category_id : current.category_id;
    const updatedPhone = phone !== undefined ? (phone ? phone.trim() : null) : current.phone;
    const updatedPriority = priority !== undefined ? priority.trim() : current.priority;

    if (!updatedName) {
      return res.status(400).json({ error: 'El nombre del grupo es obligatorio.' });
    }

    await db.run(
      'UPDATE groups SET name = ?, category_id = ?, phone = ?, priority = ? WHERE id = ?',
      [updatedName, updatedCategoryId, updatedPhone, updatedPriority, id]
    );

    let categoryName = null;
    if (updatedCategoryId) {
      const cat = await db.get('SELECT name FROM categories WHERE id = ?', [updatedCategoryId]);
      if (cat) categoryName = cat.name;
    }

    res.json({ 
      id: Number(id), 
      name: updatedName,
      category_id: updatedCategoryId,
      category_name: categoryName,
      phone: updatedPhone,
      priority: updatedPriority
    });
  } catch (error) {
    console.error('Error en PUT /api/groups/:id:', error);
    res.status(500).json({ error: 'Error al actualizar el grupo.' });
  }
});

/**
 * DELETE /api/groups/:id
 */
app.delete('/api/groups/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  try {
    const db = await getDb();
    const result = await db.run('DELETE FROM groups WHERE id = ?', [id]);

    if (result.changes === 0) {
      return res.status(404).json({ error: 'Grupo no encontrado.' });
    }

    res.json({ success: true, message: 'Grupo eliminado correctamente.' });
  } catch (error) {
    console.error('Error en DELETE /api/groups/:id:', error);
    res.status(500).json({ error: 'Error al eliminar el grupo.' });
  }
});

/**
 * POST /api/guests
 */
app.post('/api/guests', requireAdmin, async (req, res) => {
  const { group_id, name, status, is_child_menu, is_anonymous } = req.body;

  if (!group_id) {
    return res.status(400).json({ error: 'El id del grupo (group_id) es obligatorio.' });
  }

  const guestName = name ? name.trim() : (is_anonymous ? 'Acompañante Anónimo' : '');
  if (!guestName) {
    return res.status(400).json({ error: 'El nombre del invitado es requerido.' });
  }

  try {
    const db = await getDb();
    const groupExists = await db.get('SELECT 1 FROM groups WHERE id = ?', [group_id]);
    if (!groupExists) {
      return res.status(404).json({ error: 'El grupo especificado no existe.' });
    }

    const dbStatus = status || 'pending';
    const dbChildMenu = is_child_menu ? 1 : 0;
    const dbAnonymous = is_anonymous ? 1 : 0;

    const result = await db.run(
      `INSERT INTO guests (group_id, name, status, is_child_menu, is_anonymous) 
       VALUES (?, ?, ?, ?, ?)`,
      [group_id, guestName, dbStatus, dbChildMenu, dbAnonymous]
    );

    res.status(201).json({
      id: result.lastID,
      group_id,
      name: guestName,
      status: dbStatus,
      is_child_menu: dbChildMenu === 1,
      is_anonymous: dbAnonymous === 1
    });
  } catch (error) {
    console.error('Error en POST /api/guests:', error);
    res.status(500).json({ error: 'Error al agregar el invitado.' });
  }
});

/**
 * PUT /api/guests/:id
 */
app.put('/api/guests/:id', async (req, res) => {
  const { id } = req.params;
  const { name, status, is_child_menu, is_anonymous } = req.body;

  try {
    const db = await getDb();
    
    const current = await db.get('SELECT * FROM guests WHERE id = ?', [id]);
    if (!current) {
      return res.status(404).json({ error: 'Invitado no encontrado.' });
    }

    const updatedName = name !== undefined ? name.trim() : current.name;
    const updatedStatus = status !== undefined ? status : current.status;
    const updatedChildMenu = is_child_menu !== undefined ? (is_child_menu ? 1 : 0) : current.is_child_menu;
    const updatedAnonymous = is_anonymous !== undefined ? (is_anonymous ? 1 : 0) : current.is_anonymous;

    await db.run(
      `UPDATE guests 
       SET name = ?, status = ?, is_child_menu = ?, is_anonymous = ? 
       WHERE id = ?`,
      [updatedName, updatedStatus, updatedChildMenu, updatedAnonymous, id]
    );

    res.json({
      id: Number(id),
      group_id: current.group_id,
      name: updatedName,
      status: updatedStatus,
      is_child_menu: updatedChildMenu === 1,
      is_anonymous: updatedAnonymous === 1
    });
  } catch (error) {
    console.error('Error en PUT /api/guests/:id:', error);
    res.status(500).json({ error: 'Error al actualizar el invitado.' });
  }
});

/**
 * DELETE /api/guests/:id
 */
app.delete('/api/guests/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  try {
    const db = await getDb();
    const result = await db.run('DELETE FROM guests WHERE id = ?', [id]);

    if (result.changes === 0) {
      return res.status(404).json({ error: 'Invitado no encontrado.' });
    }

    res.json({ success: true, message: 'Invitado eliminado correctamente.' });
  } catch (error) {
    console.error('Error en DELETE /api/guests/:id:', error);
    res.status(500).json({ error: 'Error al eliminar el invitado.' });
  }
});

/**
 * GET /api/categories
 */
app.get('/api/categories', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    const categories = await db.all('SELECT * FROM categories ORDER BY name ASC');
    res.json(categories);
  } catch (error) {
    console.error('Error en GET /api/categories:', error);
    res.status(500).json({ error: 'Error al obtener las categorías.' });
  }
});

/**
 * POST /api/categories
 */
app.post('/api/categories', requireAdmin, async (req, res) => {
  const { name } = req.body;
  if (!name || typeof name !== 'string' || name.trim() === '') {
    return res.status(400).json({ error: 'El nombre de la categoría es obligatorio.' });
  }

  try {
    const db = await getDb();
    const existing = await db.get('SELECT * FROM categories WHERE name = ?', [name.trim()]);
    if (existing) {
      return res.status(400).json({ error: 'Esta categoría ya existe.' });
    }

    const result = await db.run(
      'INSERT INTO categories (name) VALUES (?)',
      [name.trim()]
    );

    res.status(201).json({
      id: result.lastID,
      name: name.trim()
    });
  } catch (error) {
    console.error('Error en POST /api/categories:', error);
    res.status(500).json({ error: 'Error al crear la categoría.' });
  }
});

/**
 * GET /api/support-concepts
 */
app.get('/api/support-concepts', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    const concepts = await db.all('SELECT * FROM support_concepts ORDER BY name ASC');
    res.json(concepts);
  } catch (error) {
    console.error('Error en GET /api/support-concepts:', error);
    res.status(500).json({ error: 'Error al obtener los conceptos de apoyo.' });
  }
});

/**
 * POST /api/support-concepts
 */
app.post('/api/support-concepts', requireAdmin, async (req, res) => {
  const { name } = req.body;
  if (!name || typeof name !== 'string' || name.trim() === '') {
    return res.status(400).json({ error: 'El nombre del concepto es obligatorio.' });
  }

  try {
    const db = await getDb();
    const existing = await db.get('SELECT * FROM support_concepts WHERE name = ?', [name.trim()]);
    if (existing) {
      return res.status(400).json({ error: 'Este concepto ya existe.' });
    }

    const result = await db.run(
      'INSERT INTO support_concepts (name) VALUES (?)',
      [name.trim()]
    );

    res.status(201).json({
      id: result.lastID,
      name: name.trim()
    });
  } catch (error) {
    console.error('Error en POST /api/support-concepts:', error);
    res.status(500).json({ error: 'Error al crear el concepto.' });
  }
});

/**
 * DELETE /api/support-concepts/:id
 */
app.delete('/api/support-concepts/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  try {
    const db = await getDb();
    const result = await db.run('DELETE FROM support_concepts WHERE id = ?', [id]);
    if (result.changes === 0) {
      return res.status(404).json({ error: 'Concepto no encontrado.' });
    }
    res.json({ success: true, message: 'Concepto eliminado correctamente.' });
  } catch (error) {
    console.error('Error en DELETE /api/support-concepts/:id:', error);
    res.status(500).json({ error: 'Error al eliminar el concepto.' });
  }
});

/**
 * GET /api/godparents
 */
app.get('/api/godparents', requireAdmin, async (req, res) => {
  try {
    const db = await getDb();
    const godparents = await db.all(`
      SELECT 
        g.id,
        g.name,
        g.concept_id,
        g.notes,
        s.name as concept_name
      FROM godparents g
      LEFT JOIN support_concepts s ON g.concept_id = s.id
      ORDER BY g.name ASC
    `);
    res.json(godparents);
  } catch (error) {
    console.error('Error en GET /api/godparents:', error);
    res.status(500).json({ error: 'Error al obtener los padrinos.' });
  }
});

/**
 * POST /api/godparents
 */
app.post('/api/godparents', requireAdmin, async (req, res) => {
  const { name, concept_id, notes } = req.body;
  if (!name || typeof name !== 'string' || name.trim() === '') {
    return res.status(400).json({ error: 'El nombre del padrino es obligatorio.' });
  }

  try {
    const db = await getDb();
    if (concept_id) {
      const concept = await db.get('SELECT 1 FROM support_concepts WHERE id = ?', [concept_id]);
      if (!concept) {
        return res.status(404).json({ error: 'El concepto de apoyo especificado no existe.' });
      }
    }

    const result = await db.run(
      'INSERT INTO godparents (name, concept_id, notes) VALUES (?, ?, ?)',
      [name.trim(), concept_id || null, notes ? notes.trim() : null]
    );

    let conceptName = null;
    if (concept_id) {
      const c = await db.get('SELECT name FROM support_concepts WHERE id = ?', [concept_id]);
      if (c) conceptName = c.name;
    }

    res.status(201).json({
      id: result.lastID,
      name: name.trim(),
      concept_id: concept_id || null,
      concept_name: conceptName,
      notes: notes ? notes.trim() : null
    });
  } catch (error) {
    console.error('Error en POST /api/godparents:', error);
    res.status(500).json({ error: 'Error al registrar el padrino.' });
  }
});

/**
 * PUT /api/godparents/:id
 */
app.put('/api/godparents/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  const { name, concept_id, notes } = req.body;

  try {
    const db = await getDb();
    const current = await db.get('SELECT * FROM godparents WHERE id = ?', [id]);
    if (!current) {
      return res.status(404).json({ error: 'Padrino no encontrado.' });
    }

    const updatedName = name !== undefined ? name.trim() : current.name;
    const updatedConceptId = concept_id !== undefined ? concept_id : current.concept_id;
    const updatedNotes = notes !== undefined ? (notes ? notes.trim() : null) : current.notes;

    if (!updatedName) {
      return res.status(400).json({ error: 'El nombre del padrino es obligatorio.' });
    }

    if (updatedConceptId) {
      const concept = await db.get('SELECT 1 FROM support_concepts WHERE id = ?', [updatedConceptId]);
      if (!concept) {
        return res.status(404).json({ error: 'El concepto de apoyo especificado no existe.' });
      }
    }

    await db.run(
      'UPDATE godparents SET name = ?, concept_id = ?, notes = ? WHERE id = ?',
      [updatedName, updatedConceptId || null, updatedNotes, id]
    );

    let conceptName = null;
    if (updatedConceptId) {
      const c = await db.get('SELECT name FROM support_concepts WHERE id = ?', [updatedConceptId]);
      if (c) conceptName = c.name;
    }

    res.json({
      id: Number(id),
      name: updatedName,
      concept_id: updatedConceptId || null,
      concept_name: conceptName,
      notes: updatedNotes
    });
  } catch (error) {
    console.error('Error en PUT /api/godparents/:id:', error);
    res.status(500).json({ error: 'Error al actualizar el padrino.' });
  }
});

/**
 * DELETE /api/godparents/:id
 */
app.delete('/api/godparents/:id', requireAdmin, async (req, res) => {
  const { id } = req.params;
  try {
    const db = await getDb();
    const result = await db.run('DELETE FROM godparents WHERE id = ?', [id]);
    if (result.changes === 0) {
      return res.status(404).json({ error: 'Padrino no encontrado.' });
    }
    res.json({ success: true, message: 'Padrino eliminado correctamente.' });
  } catch (error) {
    console.error('Error en DELETE /api/godparents/:id:', error);
    res.status(500).json({ error: 'Error al eliminar el padrino.' });
  }
});

module.exports = app;
