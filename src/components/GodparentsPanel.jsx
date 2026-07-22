import React, { useState, useEffect, useRef } from 'react';

const fetchWithAuth = async (url, options = {}, token, onUnauthorized) => {
  const headers = {
    ...options.headers,
    'Authorization': `Bearer ${token}`
  };
  if (options.body && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }
  const res = await fetch(url, { ...options, headers });
  if (res.status === 401 || res.status === 403) {
    onUnauthorized();
    throw new Error('Sesión expirada o no autorizada.');
  }
  return res;
};

function GodparentsPanel({ token, onUnauthorized, onLogout, navigateTo, darkMode, setDarkMode }) {
  const [godparents, setGodparents] = useState([]);
  const [supportConcepts, setSupportConcepts] = useState([]);
  const [loadingGodparents, setLoadingGodparents] = useState(true);

  const [newGodparentName, setNewGodparentName] = useState('');
  const [newGodparentConceptId, setNewGodparentConceptId] = useState('');
  const [newGodparentNotes, setNewGodparentNotes] = useState('');
  const [editingGodparent, setEditingGodparent] = useState(null);

  const [newConceptNameText, setNewConceptNameText] = useState('');
  const [godparentMobileTab, setGodparentMobileTab] = useState('list'); // 'list' o 'form'

  // Referencia y efecto para el comportamiento de scroll pegajoso dinámico del sidebar
  const sidebarRef = useRef(null);

  useEffect(() => {
    const sidebar = sidebarRef.current;
    if (!sidebar) return;

    let lastScrollY = window.scrollY;
    let currentTop = 32; // 2rem en píxeles

    const handleScroll = () => {
      const scrollY = window.scrollY;
      const delta = scrollY - lastScrollY;
      lastScrollY = scrollY;

      const viewportHeight = window.innerHeight;
      const sidebarHeight = sidebar.offsetHeight;
      const topLimit = 32;
      const bottomLimit = viewportHeight - sidebarHeight - 32;

      // Si el sidebar es más pequeño que el viewport, se queda pegado arriba normalmente
      if (sidebarHeight + 64 <= viewportHeight) {
        sidebar.style.position = 'sticky';
        sidebar.style.top = `${topLimit}px`;
        return;
      }

      sidebar.style.position = 'sticky';
      currentTop -= delta;

      // Clampear entre los límites de arriba y abajo
      if (currentTop > topLimit) {
        currentTop = topLimit;
      } else if (currentTop < bottomLimit) {
        currentTop = bottomLimit;
      }

      sidebar.style.top = `${currentTop}px`;
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll);
    handleScroll();

    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', handleScroll);
    };
  }, []);

  const fetchGodparentsData = async () => {
    setLoadingGodparents(true);
    try {
      const [gpRes, scRes] = await Promise.all([
        fetchWithAuth('/api/godparents', {}, token, onUnauthorized),
        fetchWithAuth('/api/support-concepts', {}, token, onUnauthorized)
      ]);
      if (gpRes.ok && scRes.ok) {
        setGodparents(await gpRes.json());
        setSupportConcepts(await scRes.json());
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingGodparents(false);
    }
  };

  useEffect(() => {
    fetchGodparentsData();
  }, []);

  const handleCreateGodparent = async (e) => {
    e.preventDefault();
    if (!newGodparentName.trim()) {
      alert('El nombre del padrino es obligatorio.');
      return;
    }
    try {
      const res = await fetchWithAuth('/api/godparents', {
        method: 'POST',
        body: JSON.stringify({
          name: newGodparentName,
          concept_id: newGodparentConceptId ? Number(newGodparentConceptId) : null,
          notes: newGodparentNotes
        })
      }, token, onUnauthorized);
      if (res.ok) {
        const created = await res.json();
        setGodparents([...godparents, created].sort((a, b) => a.name.localeCompare(b.name)));
        setNewGodparentName('');
        setNewGodparentConceptId('');
        setNewGodparentNotes('');
      } else {
        alert('Error al registrar el padrino.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleUpdateGodparent = async (e) => {
    e.preventDefault();
    if (!editingGodparent.name.trim()) {
      alert('El nombre del padrino es obligatorio.');
      return;
    }
    try {
      const res = await fetchWithAuth(`/api/godparents/${editingGodparent.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          name: editingGodparent.name,
          concept_id: editingGodparent.concept_id ? Number(editingGodparent.concept_id) : null,
          notes: editingGodparent.notes
        })
      }, token, onUnauthorized);
      if (res.ok) {
        const updated = await res.json();
        setGodparents(godparents.map(gp => gp.id === editingGodparent.id ? updated : gp).sort((a, b) => a.name.localeCompare(b.name)));
        setEditingGodparent(null);
      } else {
        alert('Error al actualizar el padrino.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteGodparent = async (id, name) => {
    if (!confirm(`¿Eliminar al padrino "${name}"?`)) return;
    try {
      const res = await fetchWithAuth(`/api/godparents/${id}`, { method: 'DELETE' }, token, onUnauthorized);
      if (res.ok) {
        setGodparents(godparents.filter(gp => gp.id !== id));
      } else {
        alert('Error al eliminar.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleCreateConcept = async (e) => {
    e.preventDefault();
    if (!newConceptNameText.trim()) return;
    try {
      const res = await fetchWithAuth('/api/support-concepts', {
        method: 'POST',
        body: JSON.stringify({ name: newConceptNameText.trim() })
      }, token, onUnauthorized);
      if (res.ok) {
        const created = await res.json();
        setSupportConcepts([...supportConcepts, created].sort((a, b) => a.name.localeCompare(b.name)));
        setNewConceptNameText('');
      } else {
        const err = await res.json();
        alert(err.error || 'Error al guardar el concepto.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteConcept = async (id, name) => {
    if (!confirm(`¿Eliminar el concepto "${name}"? Esto removerá la vinculación de los padrinos asociados.`)) return;
    try {
      const res = await fetchWithAuth(`/api/support-concepts/${id}`, { method: 'DELETE' }, token, onUnauthorized);
      if (res.ok) {
        setSupportConcepts(supportConcepts.filter(c => c.id !== id));
        setGodparents(godparents.map(gp => gp.concept_id === id ? { ...gp, concept_id: null, concept_name: null } : gp));
      } else {
        alert('Error al eliminar el concepto.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const totalPadrinos = godparents.length;
  const coveredConceptsCount = supportConcepts.filter(c => 
    godparents.some(gp => gp.concept_id === c.id)
  ).length;
  const pendingConceptsCount = supportConcepts.length - coveredConceptsCount;

  return (
    <div className="app-container">
      {/* Blobs de Fondo */}
      <div className="bg-blobs">
        <div className="blob blob-1"></div>
        <div className="blob blob-2"></div>
      </div>

      {/* HEADER */}
      <header className="header">
        <div className="cursive-text">Boda & Planeación</div>
        <h1>Nuestros Padrinos 🎁</h1>
        <p>Organización de Apoyos y Agradecimientos Especiales</p>
        <div className="header-decor">
          <span>⚜️</span>
        </div>
      </header>

      {/* NAVEGACIÓN */}
      <nav className="nav-tabs" style={{ 
        display: 'flex', 
        justifyContent: 'center', 
        gap: '1rem', 
        flexWrap: 'wrap',
        rowGap: '0.5rem',
        marginBottom: '3rem' 
      }}>
        <button 
          type="button"
          className="theme-toggle-btn"
          onClick={() => navigateTo('/')}
        >
          💍 Invitados
        </button>
        <button 
          type="button"
          className="theme-toggle-btn"
          style={{
            borderColor: 'var(--accent-gold)',
            background: 'var(--accent-gold-light)',
            color: 'var(--accent-gold-hover)',
            fontWeight: '700'
          }}
          onClick={() => navigateTo('/padrinos')}
        >
          🎁 Padrinos
        </button>
        <button 
          type="button"
          className="theme-toggle-btn"
          onClick={() => navigateTo('/galeria')}
        >
          📸 Álbum
        </button>
      </nav>

      {/* CONTROLES GLOBALES */}
      <div className="view-controls">
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', justifyContent: 'center', width: '100%' }}>
          <button 
            className="theme-toggle-btn"
            onClick={() => setDarkMode(!darkMode)}
          >
            {darkMode ? '☀️ Modo Claro' : '🌙 Modo Oscuro'}
          </button>
          <button 
            className="theme-toggle-btn"
            style={{ borderColor: 'var(--color-declined)', color: 'var(--color-declined)' }}
            onClick={onLogout}
          >
            🚪 Cerrar Sesión
          </button>
        </div>
        <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: '600' }}>
          Organizando {totalPadrinos} Padrino(s)
        </div>
      </div>

      {/* DASHBOARD RESUMEN DE PADRINOS */}
      <section className="dashboard" style={{ marginBottom: '3rem' }}>
        <div className="stat-card total" style={{ background: 'radial-gradient(100% 100% at top right, rgba(194,159,104,0.06), var(--bg-glass))' }}>
          <div className="stat-label">Total Padrinos</div>
          <div className="stat-value">{totalPadrinos}</div>
        </div>
        <div className="stat-card confirmed" style={{ background: 'radial-gradient(100% 100% at top right, rgba(46,125,50,0.06), var(--bg-glass))', borderColor: 'var(--color-confirmed)' }}>
          <div className="stat-label" style={{ color: 'var(--color-confirmed)' }}>Apoyos Cubiertos</div>
          <div className="stat-value" style={{ color: 'var(--color-confirmed)' }}>{coveredConceptsCount}</div>
          <div style={{ fontSize: '0.65rem', textTransform: 'uppercase', letterSpacing: '0.05em', marginTop: '4px', opacity: 0.8 }}>
            de {supportConcepts.length} conceptos
          </div>
        </div>
        <div className="stat-card pending" style={{ background: 'radial-gradient(100% 100% at top right, rgba(223,122,0,0.06), var(--bg-glass))', borderColor: 'var(--color-pending)' }}>
          <div className="stat-label" style={{ color: 'var(--color-pending)' }}>Conceptos Pendientes</div>
          <div className="stat-value" style={{ color: 'var(--color-pending)' }}>{pendingConceptsCount}</div>
          <div style={{ fontSize: '0.65rem', textTransform: 'uppercase', letterSpacing: '0.05em', marginTop: '4px', opacity: 0.8 }}>
            sin padrino asignado
          </div>
        </div>
      </section>

      {/* SECCIÓN MÓVIL TABS */}
      <div className="mobile-tabs">
        <button 
          type="button"
          className={`mobile-tab-btn ${godparentMobileTab === 'list' ? 'active' : ''}`}
          onClick={() => setGodparentMobileTab('list')}
        >
          📋 Ver Padrinos ({godparents.length})
        </button>
        <button 
          type="button"
          className={`mobile-tab-btn ${godparentMobileTab === 'form' ? 'active' : ''}`}
          onClick={() => setGodparentMobileTab('form')}
        >
          ➕ Registrar Padrino
        </button>
      </div>

      {/* CONTENIDO PRINCIPAL */}
      <main className="main-content">
        
        {/* LISTADO DE PADRINOS Y CONCEPTOS (IZQUIERDA) */}
        <section className={godparentMobileTab === 'list' ? 'mobile-visible' : 'mobile-hidden'} style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
          
          {/* PANEL DE PADRINOS */}
          <div className="card-panel" style={{ padding: '2rem' }}>
            <h2 className="card-title">👥 Listado de Padrinos</h2>
            {loadingGodparents ? (
              <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-secondary)' }}>
                <span style={{ fontFamily: 'var(--font-serif)', fontSize: '1.2rem' }}>✨ Cargando listado de padrinos...</span>
              </div>
            ) : godparents.length === 0 ? (
              <div className="empty-state" style={{ padding: '3rem 1rem' }}>
                <div className="empty-state-icon">🎁</div>
                <h3>Aún no hay padrinos registrados</h3>
                <p>Completa el formulario de la derecha para registrar a tus padrinos y sus apoyos.</p>
              </div>
            ) : (
              <div className="group-cards-container" style={{ gap: '1.25rem' }}>
                {godparents.map(gp => (
                  <div className="group-card" key={gp.id} style={{ padding: '1.25rem 1.5rem' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
                      <div>
                        <div style={{ fontSize: '1.2rem', fontFamily: 'var(--font-serif)', fontWeight: '600' }}>
                          👤 {gp.name}
                        </div>
                        <div style={{ marginTop: '0.25rem', display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span className="guest-badge anonymous" style={{ fontSize: '0.7rem' }}>
                            💝 {gp.concept_name || 'Apoyo general / Sin asignar'}
                          </span>
                          {gp.notes && (
                            <span style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', fontStyle: 'italic' }}>
                              ({gp.notes})
                            </span>
                          )}
                        </div>
                      </div>
                      <div style={{ display: 'flex', gap: '6px' }}>
                        <button 
                          type="button"
                          className="btn-icon"
                          title="Editar Padrino"
                          onClick={() => {
                            setEditingGodparent(gp);
                            document.getElementById('padrinoFormContainer')?.scrollIntoView({ behavior: 'smooth' });
                          }}
                        >
                          ✏️
                        </button>
                        <button 
                          type="button"
                          className="btn-icon"
                          style={{ color: 'var(--color-declined)' }}
                          title="Eliminar Padrino"
                          onClick={() => handleDeleteGodparent(gp.id, gp.name)}
                        >
                          🗑️
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* PANEL DE CONCEPTOS DE APOYO */}
          <div className="card-panel" style={{ padding: '2rem' }}>
            <h2 className="card-title">🏷️ Conceptos Disponibles</h2>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', marginBottom: '1.5rem', textAlign: 'center' }}>
              Estos son los rubros de boda en los que tus padrinos te pueden apoyar. Puedes agregar más a la derecha.
            </p>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', justifyContent: 'center' }}>
              {supportConcepts.map(concept => {
                const sponsors = godparents.filter(gp => gp.concept_id === concept.id);
                const isAssigned = sponsors.length > 0;
                return (
                  <span 
                    key={concept.id} 
                    className={`status-badge ${isAssigned ? 'confirmed' : 'pending'}`}
                    style={{ 
                      padding: '0.5rem 1rem', 
                      cursor: 'default',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '6px'
                    }}
                    title={isAssigned ? `Padrino(s): ${sponsors.map(s => s.name).join(', ')}` : 'Sin asignar aún'}
                  >
                    {concept.name}
                    {isAssigned && <span style={{ fontSize: '0.7rem', opacity: 0.8 }}>({sponsors.length})</span>}
                    <button
                      type="button"
                      style={{
                        background: 'transparent',
                        border: 'none',
                        color: 'inherit',
                        cursor: 'pointer',
                        marginLeft: '4px',
                        fontSize: '0.75rem',
                        display: 'inline-flex',
                        alignItems: 'center'
                      }}
                      title="Eliminar este concepto"
                      onClick={() => handleDeleteConcept(concept.id, concept.name)}
                    >
                      ✖
                    </button>
                  </span>
                );
              })}
            </div>
          </div>

        </section>

        {/* COLUMNA FORMULARIO DE REGISTRO (DERECHA PEGAJOSA) */}
        <section ref={sidebarRef} id="padrinoFormContainer" className={godparentMobileTab === 'form' ? 'mobile-visible' : 'mobile-hidden'}>
          <div className="card-panel" style={{ marginBottom: '2rem' }}>
            <h2 className="card-title">
              {editingGodparent ? '✏️ Editar Padrino' : '✨ Registrar Padrino'}
            </h2>
            <form onSubmit={editingGodparent ? handleUpdateGodparent : handleCreateGodparent}>
              <div className="form-group">
                <label className="form-label" htmlFor="godparentName">Nombre del Padrino / Pareja</label>
                <input 
                  type="text" 
                  id="godparentName"
                  className="form-input" 
                  placeholder="Ej. Tíos Juan y Laura"
                  value={editingGodparent ? editingGodparent.name : newGodparentName}
                  onChange={(e) => {
                    if (editingGodparent) {
                      setEditingGodparent({ ...editingGodparent, name: e.target.value });
                    } else {
                      setNewGodparentName(e.target.value);
                    }
                  }}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label" htmlFor="godparentConcept">¿En qué les apoyará?</label>
                <select
                  id="godparentConcept"
                  className="filter-select"
                  style={{ width: '100%', padding: '0.8rem 1rem' }}
                  value={editingGodparent ? (editingGodparent.concept_id || '') : newGodparentConceptId}
                  onChange={(e) => {
                    const val = e.target.value ? Number(e.target.value) : '';
                    if (editingGodparent) {
                      setEditingGodparent({ ...editingGodparent, concept_id: val });
                    } else {
                      setNewGodparentConceptId(val);
                    }
                  }}
                >
                  <option value="">-- Apoyo General / Sin Concepto Específico --</option>
                  {supportConcepts.map(c => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label" htmlFor="godparentNotes">Notas adicionales (Opcional)</label>
                <input 
                  type="text" 
                  id="godparentNotes"
                  className="form-input" 
                  placeholder="Ej. Pagará directamente al proveedor"
                  value={editingGodparent ? (editingGodparent.notes || '') : newGodparentNotes}
                  onChange={(e) => {
                    if (editingGodparent) {
                      setEditingGodparent({ ...editingGodparent, notes: e.target.value });
                    } else {
                      setNewGodparentNotes(e.target.value);
                    }
                  }}
                />
              </div>

              <div style={{ display: 'flex', gap: '10px', marginTop: '2rem' }}>
                {editingGodparent && (
                  <button 
                    type="button" 
                    className="btn-secondary" 
                    style={{ flex: 1 }}
                    onClick={() => setEditingGodparent(null)}
                  >
                    Cancelar
                  </button>
                )}
                <button type="submit" className="btn-primary" style={{ flex: 2 }}>
                  💾 {editingGodparent ? 'Guardar Cambios' : 'Registrar Padrino'}
                </button>
              </div>
            </form>
          </div>

          {/* FORMULARIO AGREGAR CONCEPTOS */}
          <div className="card-panel">
            <h2 className="card-title">➕ Agregar Conceptos</h2>
            <form onSubmit={handleCreateConcept}>
              <div className="form-group">
                <label className="form-label" htmlFor="newConceptName">Nombre del Concepto / Apoyo</label>
                <input 
                  type="text" 
                  id="newConceptName"
                  className="form-input" 
                  placeholder="Ej. Alianzas, Ramo, Pastel..."
                  value={newConceptNameText}
                  onChange={(e) => setNewConceptNameText(e.target.value)}
                  required
                />
              </div>
              <button type="submit" className="btn-primary">
                💾 Guardar Concepto
              </button>
            </form>
          </div>

        </section>
      </main>
    </div>
  );
}

export default GodparentsPanel;
