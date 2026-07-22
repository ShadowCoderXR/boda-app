import React, { useState, useEffect, useRef } from 'react';
import QRCode from 'qrcode';

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

function AdminPanel({ token, onUnauthorized, onLogout, navigateTo, darkMode, setDarkMode }) {
  const [groups, setGroups] = useState([]);
  const [categories, setCategories] = useState([]);
  const [summary, setSummary] = useState({
    totalGroups: 0,
    totalGuests: 0,
    confirmedGuests: 0,
    declinedGuests: 0,
    pendingGuests: 0,
    confirmedChildMenus: 0,
    confirmedAdults: 0
  });

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filtros y Búsqueda
  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [filterCategory, setFilterCategory] = useState('all');
  const [filterPriority, setFilterPriority] = useState('all');

  // Estado del Formulario para Nuevo Grupo
  const [newGroupName, setNewGroupName] = useState('');
  const [newGroupPhone, setNewGroupPhone] = useState('');
  const [newGroupCategoryId, setNewGroupCategoryId] = useState('');
  const [newGroupPriority, setNewGroupPriority] = useState('A');
  const [newGroupGuests, setNewGroupGuests] = useState([
    { name: '', is_child_menu: false, is_anonymous: false }
  ]);

  // Agregar Nueva Categoría Dinámicamente
  const [isAddingCategory, setIsAddingCategory] = useState(false);
  const [newCategoryName, setNewCategoryName] = useState('');

  // Estado para Edición de Grupo
  const [editingGroup, setEditingGroup] = useState(null);
  const [editingGroupNameText, setEditingGroupNameText] = useState('');
  const [editingGroupPhoneText, setEditingGroupPhoneText] = useState('');
  const [editingGroupCategoryId, setEditingGroupCategoryId] = useState('');
  const [editingGroupPriority, setEditingGroupPriority] = useState('A');

  // Estado para Agregar Invitado a un Grupo Existente
  const [addingGuestToGroupId, setAddingGuestToGroupId] = useState(null);
  const [quickGuestName, setQuickGuestName] = useState('');
  const [quickGuestIsChild, setQuickGuestIsChild] = useState(false);
  const [quickGuestIsAnonymous, setQuickGuestIsAnonymous] = useState(false);

  // Configuración de Capacidad Máxima de Invitados
  const [isEditingMaxGuests, setIsEditingMaxGuests] = useState(false);
  const [tempMaxGuests, setTempMaxGuests] = useState('100');

  // Estados de Pestañas Móviles
  const [mobileTab, setMobileTab] = useState('list'); // 'list' o 'form'

  // Control de colapsado/expandido de grupos en el dashboard
  const [expandedGroups, setExpandedGroups] = useState({});

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

  const fetchSummaryOnly = async () => {
    try {
      const res = await fetchWithAuth('/api/summary', {}, token, onUnauthorized);
      if (res.ok) {
        setSummary(await res.json());
      }
    } catch (err) {
      console.error(err);
    }
  };

  const fetchInitialData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [groupsRes, categoriesRes, summaryRes] = await Promise.all([
        fetchWithAuth('/api/groups', {}, token, onUnauthorized),
        fetchWithAuth('/api/categories', {}, token, onUnauthorized),
        fetchWithAuth('/api/summary', {}, token, onUnauthorized)
      ]);

      if (groupsRes.ok && categoriesRes.ok && summaryRes.ok) {
        setGroups(await groupsRes.json());
        setCategories(await categoriesRes.json());
        setSummary(await summaryRes.json());
      } else {
        setError('Error al obtener datos del servidor.');
      }
    } catch (err) {
      console.error(err);
      setError('Error de conexión con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInitialData();
  }, []);

  const toggleGroupExpanded = (groupId) => {
    setExpandedGroups(prev => ({
      ...prev,
      [groupId]: !prev[groupId]
    }));
  };

  const handleSaveMaxGuests = async () => {
    const val = Number(tempMaxGuests);
    if (isNaN(val) || val < 1) {
      alert('Por favor ingresa un número válido mayor a 0.');
      setIsEditingMaxGuests(false);
      return;
    }
    try {
      const res = await fetchWithAuth('/api/settings', {
        method: 'POST',
        body: JSON.stringify({ max_guests: val })
      }, token, onUnauthorized);
      if (res.ok) {
        setSummary(prev => ({ ...prev, maxGuests: val }));
      } else {
        alert('Error al guardar la configuración.');
      }
    } catch (error) {
      console.error(error);
      alert('Error de conexión.');
    } finally {
      setIsEditingMaxGuests(false);
    }
  };

  const handleCreateGroupSubmit = async (e) => {
    e.preventDefault();
    if (!newGroupName.trim()) {
      alert('El nombre del grupo es obligatorio.');
      return;
    }
    try {
      const res = await fetchWithAuth('/api/groups', {
        method: 'POST',
        body: JSON.stringify({
          name: newGroupName,
          phone: newGroupPhone,
          priority: newGroupPriority,
          category_id: newGroupCategoryId ? Number(newGroupCategoryId) : null,
          guests: newGroupGuests.filter(g => g.name.trim() !== '' || g.is_anonymous)
        })
      }, token, onUnauthorized);

      if (res.ok) {
        const created = await res.json();
        setGroups([created, ...groups]);
        setNewGroupName('');
        setNewGroupPhone('');
        setNewGroupPriority('A');
        setNewGroupCategoryId('');
        setNewGroupGuests([{ name: '', is_child_menu: false, is_anonymous: false }]);
        fetchSummaryOnly();
        alert('¡Grupo e invitados creados con éxito!');
      } else {
        const err = await res.json();
        alert(err.error || 'Error al guardar el grupo.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleCreateCategory = async () => {
    if (!newCategoryName.trim()) {
      alert('Escribe el nombre de la categoría.');
      return;
    }
    try {
      const res = await fetchWithAuth('/api/categories', {
        method: 'POST',
        body: JSON.stringify({ name: newCategoryName.trim() })
      }, token, onUnauthorized);

      if (res.ok) {
        const created = await res.json();
        setCategories([...categories, created].sort((a, b) => a.name.localeCompare(b.name)));
        setNewGroupCategoryId(created.id);
        setNewCategoryName('');
        setIsAddingCategory(false);
      } else {
        const err = await res.json();
        alert(err.error || 'Error al guardar la categoría.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const startEditingGroup = (group) => {
    setEditingGroup(group);
    setEditingGroupNameText(group.name);
    setEditingGroupPhoneText(group.phone || '');
    setEditingGroupCategoryId(group.category_id || '');
    setEditingGroupPriority(group.priority || 'A');
  };

  const handleUpdateGroupNameSubmit = async (e) => {
    e.preventDefault();
    if (!editingGroupNameText.trim()) return;

    try {
      const res = await fetchWithAuth(`/api/groups/${editingGroup.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          name: editingGroupNameText,
          phone: editingGroupPhoneText,
          priority: editingGroupPriority,
          category_id: editingGroupCategoryId ? Number(editingGroupCategoryId) : null
        })
      }, token, onUnauthorized);

      if (res.ok) {
        const updated = await res.json();
        setGroups(groups.map(g => g.id === editingGroup.id ? { ...g, ...updated } : g));
        setEditingGroup(null);
        fetchSummaryOnly();
      } else {
        alert('Error al actualizar el grupo.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteGroup = async (groupId, groupName) => {
    if (!confirm(`¿Eliminar por completo el grupo "${groupName}" y todos sus invitados asociados? Esta acción no se puede deshacer.`)) return;

    try {
      const res = await fetchWithAuth(`/api/groups/${groupId}`, {
        method: 'DELETE'
      }, token, onUnauthorized);

      if (res.ok) {
        setGroups(groups.filter(g => g.id !== groupId));
        fetchSummaryOnly();
      } else {
        alert('Error al eliminar el grupo.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleQuickAddGuestSubmit = async (e) => {
    e.preventDefault();
    const guestName = quickGuestIsAnonymous ? 'Acompañante Anónimo' : quickGuestName.trim();
    if (!guestName) {
      alert('Ingresa el nombre del invitado.');
      return;
    }

    try {
      const res = await fetchWithAuth('/api/guests', {
        method: 'POST',
        body: JSON.stringify({
          group_id: addingGuestToGroupId,
          name: guestName,
          is_child_menu: quickGuestIsChild,
          is_anonymous: quickGuestIsAnonymous,
          status: 'pending'
        })
      }, token, onUnauthorized);

      if (res.ok) {
        const created = await res.json();
        setGroups(groups.map(g => {
          if (g.id === addingGuestToGroupId) {
            return { ...g, guests: [...g.guests, created].sort((a, b) => a.id - b.id) };
          }
          return g;
        }));

        setAddingGuestToGroupId(null);
        setQuickGuestName('');
        setQuickGuestIsChild(false);
        setQuickGuestIsAnonymous(false);
        fetchSummaryOnly();
      } else {
        alert('Error al registrar el invitado.');
      }
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteGuest = async (guestId, groupId, guestName) => {
    if (!confirm(`¿Eliminar al invitado "${guestName}" del grupo?`)) return;

    try {
      const res = await fetchWithAuth(`/api/guests/${guestId}`, {
        method: 'DELETE'
      }, token, onUnauthorized);

      if (res.ok) {
        setGroups(groups.map(g => {
          if (g.id === groupId) {
            return { ...g, guests: g.guests.filter(guestItem => guestItem.id !== guestId) };
          }
          return g;
        }));

        fetchSummaryOnly();
      } else {
        alert('Error al eliminar el invitado.');
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleCycleStatus = async (guest) => {
    let nextStatus = 'pending';
    if (guest.status === 'pending') nextStatus = 'confirmed';
    else if (guest.status === 'confirmed') nextStatus = 'declined';
    else if (guest.status === 'declined') nextStatus = 'pending';

    try {
      const res = await fetchWithAuth(`/api/guests/${guest.id}`, {
        method: 'PUT',
        body: JSON.stringify({ status: nextStatus })
      }, token, onUnauthorized);

      if (res.ok) {
        const updated = await res.json();
        setGroups(groups.map(g => {
          if (g.id === guest.group_id) {
            return { ...g, guests: g.guests.map(item => item.id === guest.id ? updated : item) };
          }
          return g;
        }));
        fetchSummaryOnly();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleToggleChildMenu = async (guest) => {
    try {
      const res = await fetchWithAuth(`/api/guests/${guest.id}`, {
        method: 'PUT',
        body: JSON.stringify({ is_child_menu: !guest.is_child_menu })
      }, token, onUnauthorized);

      if (res.ok) {
        const updated = await res.json();
        setGroups(groups.map(g => {
          if (g.id === guest.group_id) {
            return { ...g, guests: g.guests.map(item => item.id === guest.id ? updated : item) };
          }
          return g;
        }));
        fetchSummaryOnly();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleAddGuestRow = () => {
    setNewGroupGuests([...newGroupGuests, { name: '', is_child_menu: false, is_anonymous: false }]);
  };

  const handleRemoveGuestRow = (index) => {
    setNewGroupGuests(newGroupGuests.filter((_, i) => i !== index));
  };

  const handleGuestFieldChange = (index, field, value) => {
    const updated = newGroupGuests.map((guest, i) => {
      if (i === index) {
        const updatedGuest = { ...guest, [field]: value };
        if (field === 'is_anonymous' && value === true) {
          updatedGuest.name = 'Acompañante Anónimo';
        } else if (field === 'is_anonymous' && value === false) {
          updatedGuest.name = '';
        }
        return updatedGuest;
      }
      return guest;
    });
    setNewGroupGuests(updated);
  };

  // Copiar Enlace de Invitación
  const handleCopyLink = (groupId) => {
    const inviteUrl = `${window.location.origin}/invitacion/${groupId}`;
    navigator.clipboard.writeText(inviteUrl);
    alert(`¡Enlace copiado con éxito!:\n${inviteUrl}`);
  };

  // Enviar por WhatsApp
  const handleSendWhatsApp = (group) => {
    if (!group.phone) {
      alert('Este grupo no tiene celular registrado.');
      return;
    }
    const inviteUrl = `${window.location.origin}/invitacion/${group.id}`;
    const message = `¡Hola! Te invitamos a nuestra boda. Puedes confirmar tu asistencia y la de tus acompañantes ingresando a tu invitación digital en este enlace:\n\n${inviteUrl}`;
    
    const whatsappUrl = `https://api.whatsapp.com/send?phone=${group.phone.replace(/[^0-9+]/g, '')}&text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
  };

  // DESCARGAR CÓDIGO QR PERSONALIZADO
  const handleDownloadQR = async (group) => {
    try {
      const inviteUrl = `${window.location.origin}/invitacion/${group.id}`;
      const qrDataUrl = await QRCode.toDataURL(inviteUrl, {
        width: 400,
        margin: 2,
        color: {
          dark: '#6b1d2f', // Color Borgoña elegante
          light: '#ffffff'
        }
      });
      const link = document.createElement('a');
      link.href = qrDataUrl;
      link.download = `invitacion_${group.name.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err) {
      console.error('Error al generar el código QR:', err);
      alert('No se pudo generar el código QR');
    }
  };

  // EXPORTAR EXCEL (CSV)
  const handleExportCSV = () => {
    const headers = ['Grupo', 'Nombre Invitado', 'Estatus', 'Menu Infantil', 'Tipo'];
    const rows = [];
    
    groups.forEach(group => {
      group.guests.forEach(guest => {
        let statusText = 'Pendiente';
        if (guest.status === 'confirmed') statusText = 'Confirmado';
        if (guest.status === 'declined') statusText = 'No asistira';
        
        const row = [
          group.name,
          guest.name,
          statusText,
          guest.is_child_menu ? 'Si' : 'No',
          guest.is_anonymous ? 'Acompanante' : 'Invitado Directo'
        ];
        rows.push(row);
      });
    });
    
    const csvContent = "\uFEFF" // UTF-8 BOM para soporte de acentos en Excel
      + [headers.join(','), ...rows.map(e => e.map(val => `"${val.replace(/"/g, '""')}"`).join(','))].join('\n');
      
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `lista_invitados_boda_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // Filtrado de grupos en frontend
  const filteredGroups = groups.map(g => {
    const matchedGuests = g.guests.filter(guest => {
      const matchesSearch = searchQuery === '' || 
        guest.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        g.name.toLowerCase().includes(searchQuery.toLowerCase());
      
      const matchesStatus = filterStatus === 'all' || guest.status === filterStatus;
      
      return matchesSearch && matchesStatus;
    });

    return { ...g, guests: matchedGuests };
  }).filter(g => {
    const matchesCategory = filterCategory === 'all' || String(g.category_id) === String(filterCategory);
    const matchesPriority = filterPriority === 'all' || g.priority === filterPriority;
    const hasVisibleGuests = g.guests.length > 0;
    const matchesSearchEmpty = searchQuery === '' && filterStatus === 'all';
    
    return matchesCategory && matchesPriority && (hasVisibleGuests || (matchesSearchEmpty && matchesCategory && matchesPriority));
  });

  const remainingSpots = (summary.maxGuests || 100) - summary.confirmedGuests;

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
        <h1>Nuestra Boda 💍</h1>
        <p>Gala de Invitaciones, Confirmaciones & Banquetes</p>
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
          style={{
            borderColor: 'var(--accent-gold)',
            background: 'var(--accent-gold-light)',
            color: 'var(--accent-gold-hover)',
            fontWeight: '700'
          }}
          onClick={() => navigateTo('/')}
        >
          💍 Invitados
        </button>
        <button 
          type="button"
          className="theme-toggle-btn"
          onClick={() => navigateTo('/padrinos')}
        >
          🎁 Padrinos
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
            style={{ borderColor: 'var(--accent-gold)', color: 'var(--accent-gold-hover)' }}
            onClick={handleExportCSV}
          >
            📥 Exportar Excel (CSV)
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
          Organizando {summary.totalGroups} Grupos / Familias
        </div>
      </div>

      {/* DASHBOARD */}
      <section className="dashboard">
        <div className="stat-card total">
          <div className="stat-label">Total Invitados</div>
          <div className="stat-value">{summary.totalGuests}</div>
        </div>
        <div className="stat-card confirmed">
          <div className="stat-label">Confirmados</div>
          <div className="stat-value">{summary.confirmedGuests}</div>
        </div>
        <div className="stat-card confirmed-adults">
          <div className="stat-label">Adultos Conf.</div>
          <div className="stat-value">{summary.confirmedAdults}</div>
        </div>
        <div className="stat-card children">
          <div className="stat-label">Menús Niños Conf.</div>
          <div className="stat-value">{summary.confirmedChildMenus}</div>
        </div>
        <div className="stat-card pending">
          <div className="stat-label">Pendientes</div>
          <div className="stat-value">{summary.pendingGuests}</div>
        </div>
        
        {/* LEYENDA LUGARES RESTANTES */}
        <div className="stat-card remaining" style={{ 
          background: 'radial-gradient(100% 100% at top right, rgba(107, 29, 47, 0.08), var(--bg-glass))', 
          borderColor: 'var(--accent-emerald)'
        }}>
          <div className="stat-label" style={{ color: 'var(--accent-emerald)', fontWeight: '700' }}>Lugares Restantes</div>
          <div className="stat-value" style={{ color: remainingSpots < 10 ? 'var(--color-declined)' : 'var(--text-primary)' }}>
            {remainingSpots}
          </div>
          <div style={{ fontSize: '0.65rem', textTransform: 'uppercase', letterSpacing: '0.05em', marginTop: '4px', opacity: 0.8, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '4px' }}>
            de 
            {isEditingMaxGuests ? (
              <input
                type="number"
                value={tempMaxGuests}
                onChange={(e) => setTempMaxGuests(e.target.value)}
                onBlur={handleSaveMaxGuests}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') handleSaveMaxGuests();
                  if (e.key === 'Escape') setIsEditingMaxGuests(false);
                }}
                style={{
                  width: '50px',
                  padding: '2px 4px',
                  fontSize: '0.75rem',
                  border: '1px solid var(--accent-gold)',
                  borderRadius: '4px',
                  background: 'var(--bg-secondary)',
                  color: 'var(--text-primary)',
                  textAlign: 'center'
                }}
                autoFocus
              />
            ) : (
              <span 
                style={{ fontWeight: 'bold', cursor: 'pointer', borderBottom: '1px dashed var(--text-primary)' }}
                onClick={() => {
                  setTempMaxGuests(summary.maxGuests || 100);
                  setIsEditingMaxGuests(true);
                }}
                title="Haz clic para editar el límite"
              >
                {summary.maxGuests || 100}
              </span>
            )}
            lugares
          </div>
        </div>

        {/* CONTADOR POR PRIORIDADES */}
        <div className="stat-card priorities-card" style={{ 
          gridColumn: '1 / -1',
          display: 'flex',
          justifyContent: 'space-around',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '1rem',
          padding: '1.25rem 2rem',
          background: 'var(--bg-cream)',
          border: '1px solid var(--border-gold)',
        }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 'bold', textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--text-secondary)' }}>
            Fases de Invitación / Prioridades:
          </div>
          <div style={{ display: 'flex', gap: '2rem', flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <span className="guest-badge" style={{ background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald)', border: '1px solid var(--accent-emerald)' }}>Prioridad A</span>
              <span style={{ fontSize: '0.95rem', fontWeight: '500' }}>
                <strong>{summary.priorityCount?.A || 0}</strong> {summary.priorityCount?.A === 1 ? 'invitado' : 'invitados'}
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <span className="guest-badge" style={{ background: 'var(--color-pending-light)', color: 'var(--color-pending)', border: '1px solid var(--color-pending)' }}>Prioridad B</span>
              <span style={{ fontSize: '0.95rem', fontWeight: '500' }}>
                <strong>{summary.priorityCount?.B || 0}</strong> {summary.priorityCount?.B === 1 ? 'invitado' : 'invitados'}
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <span className="guest-badge" style={{ background: 'var(--border-subtle)', color: 'var(--text-secondary)', border: '1px solid var(--text-secondary)' }}>Prioridad C</span>
              <span style={{ fontSize: '0.95rem', fontWeight: '500' }}>
                <strong>{summary.priorityCount?.C || 0}</strong> {summary.priorityCount?.C === 1 ? 'invitado' : 'invitados'}
              </span>
            </div>
          </div>
        </div>
      </section>

      {error && (
        <div style={{ background: 'var(--color-declined-light)', color: 'var(--color-declined)', padding: '1rem', borderRadius: '8px', marginBottom: '2rem', textAlign: 'center', fontWeight: '500', border: '1px solid var(--color-declined)' }}>
          ⚠️ {error}
        </div>
      )}

      {/* SECCIÓN MÓVIL TABS */}
      <div className="mobile-tabs">
        <button 
          type="button"
          className={`mobile-tab-btn ${mobileTab === 'list' ? 'active' : ''}`}
          onClick={() => setMobileTab('list')}
        >
          📋 Ver Lista ({filteredGroups.length})
        </button>
        <button 
          type="button"
          className={`mobile-tab-btn ${mobileTab === 'form' ? 'active' : ''}`}
          onClick={() => setMobileTab('form')}
        >
          ➕ Registrar Familia
        </button>
      </div>

      {/* CONTENIDO PRINCIPAL */}
      <main className="main-content">
        
        {/* LISTADO DE INVITADOS (IZQUIERDA) */}
        <section className={mobileTab === 'list' ? 'mobile-visible' : 'mobile-hidden'}>
          <div className="card-panel" style={{ padding: '2rem', marginBottom: '2rem' }}>
            <div className="guest-list-controls">
              <div className="search-input-wrapper">
                <input 
                  type="text" 
                  className="form-input"
                  placeholder="🔍 Buscar invitado o grupo..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
              
              <select 
                className="filter-select"
                value={filterStatus}
                onChange={(e) => setFilterStatus(e.target.value)}
              >
                <option value="all">Estado: Todos</option>
                <option value="pending">⏳ Pendiente</option>
                <option value="confirmed">✅ Confirmado</option>
                <option value="declined">❌ Rechazado</option>
              </select>

              <select 
                className="filter-select"
                value={filterCategory}
                onChange={(e) => setFilterCategory(e.target.value)}
              >
                <option value="all">Categoría: Todas</option>
                {categories.map(cat => (
                  <option key={cat.id} value={cat.id}>{cat.name}</option>
                ))}
              </select>

              <select 
                className="filter-select"
                value={filterPriority}
                onChange={(e) => setFilterPriority(e.target.value)}
              >
                <option value="all">Prioridad: Todas</option>
                <option value="A">Prioridad A</option>
                <option value="B">Prioridad B</option>
                <option value="C">Prioridad C</option>
              </select>
            </div>

            {loading ? (
              <div style={{ textAlign: 'center', padding: '4rem', color: 'var(--text-secondary)' }}>
                <span style={{ fontFamily: 'var(--font-serif)', fontSize: '1.2rem' }}>✨ Cargando tu lista de invitados...</span>
              </div>
            ) : filteredGroups.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state-icon">🕊️</div>
                <h3>No se encontraron invitados</h3>
                <p>Prueba buscando con otro término, cambia el filtro o crea un grupo nuevo.</p>
              </div>
            ) : (
              <div className="group-cards-container">
                {filteredGroups.map(group => {
                  const isSearchOrFilterActive = searchQuery !== '' || filterStatus !== 'all' || filterCategory !== 'all' || filterPriority !== 'all';
                  const isExpanded = isSearchOrFilterActive || !!expandedGroups[group.id];
                  return (
                    <div className="group-card" key={group.id}>
                      <div className="group-header" style={{ flexDirection: 'column', alignItems: 'stretch', gap: '0.75rem' }}>
                        
                        {/* Cabecera Nombre, Categoría, Prioridad y Botones de Acción */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
                          <div 
                            className="group-title" 
                            onClick={() => toggleGroupExpanded(group.id)}
                            style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '8px', cursor: 'pointer', userSelect: 'none' }}
                          >
                            <span style={{ 
                              display: 'inline-block', 
                              fontSize: '0.8rem',
                              color: 'var(--accent-gold)',
                              marginRight: '4px',
                              transition: 'transform 0.2s', 
                              transform: isExpanded ? 'rotate(90deg)' : 'none' 
                            }}>▶</span>
                            <span style={{ fontSize: '1.35rem', fontFamily: 'var(--font-serif)', fontWeight: '500' }}>👨‍👩‍👧‍👦 {group.name}</span>
                            
                            {/* Categoría Badge */}
                            {group.category_name && (
                              <span className="guest-badge anonymous" style={{ fontSize: '0.65rem' }}>
                                {group.category_name}
                              </span>
                            )}

                            {/* Prioridad Badge */}
                            <span className="guest-badge" style={{ 
                              fontSize: '0.65rem', 
                              background: group.priority === 'A' ? 'var(--accent-emerald-light)' : 
                                          group.priority === 'B' ? 'var(--color-pending-light)' : 'var(--border-subtle)',
                              color: group.priority === 'A' ? 'var(--accent-emerald)' : 
                                     group.priority === 'B' ? 'var(--color-pending)' : 'var(--text-secondary)',
                              border: `1px solid ${group.priority === 'A' ? 'var(--accent-emerald)' : 
                                                   group.priority === 'B' ? 'var(--color-pending)' : 'var(--text-secondary)'}`
                            }}>
                              Prioridad {group.priority}
                            </span>

                            <span style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', fontWeight: 'normal' }}>
                              ({group.guests.length} {group.guests.length === 1 ? 'invitado' : 'invitados'})
                            </span>
                          </div>
                          
                          <div className="group-actions">
                            <button 
                              className="btn-icon" 
                              title="Editar Grupo, Celular y Prioridad"
                              onClick={() => startEditingGroup(group)}
                            >
                              ✏️
                            </button>
                            <button 
                              className="btn-icon" 
                              title="Añadir Invitado a este Grupo"
                              onClick={() => setAddingGuestToGroupId(group.id)}
                            >
                              ➕
                            </button>
                            <button 
                              className="btn-icon" 
                              title="Eliminar Grupo por Completo"
                              style={{ color: 'var(--color-declined)' }}
                              onClick={() => handleDeleteGroup(group.id, group.name)}
                            >
                              🗑️
                            </button>
                          </div>
                        </div>

                        {/* Información de Celular y Link de Invitación */}
                        {isExpanded && (
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px', fontSize: '0.85rem', color: 'var(--text-secondary)', borderTop: '1px dashed var(--border-gold)', paddingTop: '0.65rem' }}>
                            <div>
                              📱 Celular: <strong>{group.phone || 'No registrado'}</strong>
                            </div>
                            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                              <button 
                                type="button" 
                                className="btn-secondary"
                                style={{ padding: '0.45rem 0.9rem', fontSize: '0.78rem' }}
                                onClick={() => handleCopyLink(group.id)}
                              >
                                🔗 Enlace
                              </button>
                              <button 
                                type="button" 
                                className="btn-whatsapp"
                                onClick={() => handleSendWhatsApp(group)}
                              >
                                💬 WhatsApp
                              </button>
                              <button 
                                type="button" 
                                className="btn-secondary"
                                style={{ padding: '0.45rem 0.9rem', fontSize: '0.78rem', borderColor: 'var(--accent-gold)', color: 'var(--accent-gold)' }}
                                onClick={() => handleDownloadQR(group)}
                              >
                                📱 Descargar QR
                              </button>
                            </div>
                          </div>
                        )}

                      </div>

                      {isExpanded && (
                        group.guests.length === 0 ? (
                          <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', fontStyle: 'italic', padding: '0.5rem' }}>
                            Sin invitados que coincidan con los filtros.
                          </p>
                        ) : (
                          <table className="guests-table">
                            <thead>
                              <tr>
                                <th>Nombre</th>
                                <th>Asistencia (Clic)</th>
                                <th>Menú Niños</th>
                                <th></th>
                              </tr>
                            </thead>
                            <tbody>
                              {group.guests.map(guest => (
                                <tr key={guest.id}>
                                  <td>
                                    <div className="guest-name-cell">
                                      <span style={{ fontWeight: '500' }}>{guest.name}</span>
                                      {guest.is_anonymous && (
                                        <span className="guest-badge anonymous" style={{ marginLeft: '8px', fontSize: '0.65rem' }}>
                                          Acompañante (+1)
                                        </span>
                                      )}
                                    </div>
                                  </td>
                                  <td>
                                    <span 
                                      className={`status-badge ${guest.status}`}
                                      onClick={() => handleCycleStatus(guest)}
                                      title="Haz clic para alternar: Pendiente -> Confirmado -> Rechazado"
                                    >
                                      {guest.status === 'confirmed' ? '✅ Conf.' : 
                                       guest.status === 'declined' ? '❌ Rech.' : '⏳ Pend.'}
                                    </span>
                                  </td>
                                  <td>
                                    <div 
                                      className={`child-menu-switch ${guest.is_child_menu ? 'active' : ''}`}
                                      onClick={() => handleToggleChildMenu(guest)}
                                    >
                                      <div className="switch-track">
                                        <div className="switch-thumb"></div>
                                      </div>
                                      <span>{guest.is_child_menu ? '👶 Sí' : 'No'}</span>
                                    </div>
                                  </td>
                                  <td style={{ textAlign: 'right' }}>
                                    <button 
                                      className="btn-danger-icon"
                                      title="Eliminar Invitado"
                                      onClick={() => handleDeleteGuest(guest.id, group.id, guest.name)}
                                    >
                                      🗑️
                                    </button>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </section>

        {/* FORMULARIO AGREGAR GRUPO (DERECHA) */}
        <section ref={sidebarRef} className={mobileTab === 'form' ? 'mobile-visible' : 'mobile-hidden'}>
          <div className="card-panel">
            <h2 className="card-title">✨ Registrar Familia o Pareja</h2>
            <form onSubmit={handleCreateGroupSubmit}>
              <div className="form-group">
                <label className="form-label" htmlFor="groupName">
                  Nombre del Grupo / Familia
                </label>
                <input 
                  type="text" 
                  id="groupName"
                  className="form-input" 
                  placeholder="Ej. Familia Martínez o Tía Sofía y Acompañante"
                  value={newGroupName}
                  onChange={(e) => setNewGroupName(e.target.value)}
                  required
                />
              </div>

              {/* CELULAR */}
              <div className="form-group">
                <label className="form-label" htmlFor="groupPhone">
                  Número de Celular (para envío)
                </label>
                <input 
                  type="tel" 
                  id="groupPhone"
                  className="form-input" 
                  placeholder="Ej. +52 55 1234 5678"
                  value={newGroupPhone}
                  onChange={(e) => setNewGroupPhone(e.target.value)}
                  required
                />
              </div>

              {/* PRIORIDAD */}
              <div className="form-group">
                <label className="form-label" htmlFor="groupPriority">
                  Prioridad / Fase de Invitación
                </label>
                <select
                  id="groupPriority"
                  className="filter-select"
                  value={newGroupPriority}
                  onChange={(e) => setNewGroupPriority(e.target.value)}
                  style={{ width: '100%', padding: '0.8rem 1rem' }}
                >
                  <option value="A">Prioridad A (Fase 1 - Alta)</option>
                  <option value="B">Prioridad B (Fase 2 - Media)</option>
                  <option value="C">Prioridad C (Fase 3 - Baja)</option>
                </select>
              </div>

              {/* CATEGORÍAS */}
              <div className="form-group">
                <label className="form-label" htmlFor="groupCategory">
                  Categoría del Grupo
                </label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <select
                    id="groupCategory"
                    className="filter-select"
                    value={newGroupCategoryId}
                    onChange={(e) => setNewGroupCategoryId(e.target.value)}
                    style={{ flex: 1, padding: '0.8rem 1rem' }}
                  >
                    <option value="">-- Seleccionar Categoría (Opcional) --</option>
                    {categories.map(cat => (
                      <option key={cat.id} value={cat.id}>{cat.name}</option>
                    ))}
                  </select>
                  <button
                    type="button"
                    className="btn-secondary"
                    onClick={() => setIsAddingCategory(!isAddingCategory)}
                    style={{ whiteSpace: 'nowrap', padding: '0.5rem 0.8rem' }}
                  >
                    {isAddingCategory ? 'Cancelar' : '➕ Nueva'}
                  </button>
                </div>
              </div>

              {isAddingCategory && (
                <div className="form-guests-section" style={{ marginBottom: '1.25rem', padding: '1rem' }}>
                  <label className="form-label" style={{ fontSize: '0.8rem' }}>Nombre de la Categoría</label>
                  <div style={{ display: 'flex', gap: '8px' }}>
                    <input
                      type="text"
                      className="form-input"
                      placeholder="Ej. Compañeros de Trabajo"
                      value={newCategoryName}
                      onChange={(e) => setNewCategoryName(e.target.value)}
                    />
                    <button
                      type="button"
                      className="btn-primary"
                      onClick={handleCreateCategory}
                      style={{ width: 'auto', padding: '0 1rem' }}
                    >
                      Guardar
                    </button>
                  </div>
                </div>
              )}

              <div className="form-guests-section">
                <div className="form-guests-section-title">
                  <span>Integrantes del Grupo</span>
                  <button 
                    type="button" 
                    className="btn-secondary"
                    onClick={handleAddGuestRow}
                  >
                    ➕ Agregar Miembro
                  </button>
                </div>

                {newGroupGuests.map((guest, index) => (
                  <div className="subguest-row" key={index}>
                    <div style={{ flex: 1 }}>
                      <label className="form-label" style={{ fontSize: '0.75rem', opacity: 0.8 }}>
                        Nombre del Invitado {index + 1}
                      </label>
                      <input 
                        type="text" 
                        className="form-input" 
                        placeholder={guest.is_anonymous ? 'Acompañante Anónimo' : 'Nombre Completo'}
                        value={guest.name}
                        disabled={guest.is_anonymous}
                        onChange={(e) => handleGuestFieldChange(index, 'name', e.target.value)}
                        required={!guest.is_anonymous}
                      />
                    </div>
                    
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', paddingBottom: '8px' }}>
                      <label className="child-menu-switch" style={{ fontSize: '0.75rem' }}>
                        <input 
                          type="checkbox"
                          checked={guest.is_anonymous}
                          onChange={(e) => handleGuestFieldChange(index, 'is_anonymous', e.target.checked)}
                          style={{ cursor: 'pointer' }}
                        />
                        <span>¿Es Anónimo? (+1)</span>
                      </label>
                      
                      <label className="child-menu-switch" style={{ fontSize: '0.75rem' }}>
                        <input 
                          type="checkbox"
                          checked={guest.is_child_menu}
                          onChange={(e) => handleGuestFieldChange(index, 'is_child_menu', e.target.checked)}
                          style={{ cursor: 'pointer' }}
                        />
                        <span>Menú Niños 👶</span>
                      </label>
                    </div>

                    {newGroupGuests.length > 1 && (
                      <button 
                        type="button"
                        className="btn-danger-icon"
                        style={{ marginBottom: '8px' }}
                        onClick={() => handleRemoveGuestRow(index)}
                      >
                        ✖️
                      </button>
                    )}
                  </div>
                ))}
              </div>

              <button type="submit" className="btn-primary">
                💾 Guardar Grupo de Invitados
              </button>
            </form>
          </div>
        </section>
      </main>

      {/* MODAL: EDITAR GRUPO */}
      {editingGroup && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3 className="card-title">✏️ Editar Grupo de Invitados</h3>
            <form onSubmit={handleUpdateGroupNameSubmit}>
              <div className="form-group">
                <label className="form-label">Nombre del Grupo</label>
                <input 
                  type="text" 
                  className="form-input"
                  value={editingGroupNameText}
                  onChange={(e) => setEditingGroupNameText(e.target.value)}
                  required
                  autoFocus
                />
              </div>

              <div className="form-group">
                <label className="form-label">Número de Celular</label>
                <input 
                  type="tel" 
                  className="form-input"
                  value={editingGroupPhoneText}
                  onChange={(e) => setEditingGroupPhoneText(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Prioridad</label>
                <select
                  className="form-input"
                  value={editingGroupPriority}
                  onChange={(e) => setEditingGroupPriority(e.target.value)}
                >
                  <option value="A">Prioridad A (Fase 1 - Alta)</option>
                  <option value="B">Prioridad B (Fase 2 - Media)</option>
                  <option value="C">Prioridad C (Fase 3 - Baja)</option>
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Categoría</label>
                <select
                  className="form-input"
                  value={editingGroupCategoryId}
                  onChange={(e) => setEditingGroupCategoryId(e.target.value)}
                >
                  <option value="">-- Sin Categoría --</option>
                  {categories.map(cat => (
                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                  ))}
                </select>
              </div>

              <div className="modal-actions">
                <button 
                  type="button" 
                  className="btn-secondary" 
                  onClick={() => setEditingGroup(null)}
                >
                  Cancelar
                </button>
                <button type="submit" className="btn-primary">
                  Guardar Cambios
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: AGREGAR INVITADO RAPIDO A GRUPO */}
      {addingGuestToGroupId && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3 className="card-title">➕ Añadir Invitado a {groups.find(g => g.id === addingGuestToGroupId)?.name}</h3>
            <form onSubmit={handleQuickAddGuestSubmit}>
              <div className="form-group">
                <label className="form-label">Nombre del Invitado</label>
                <input 
                  type="text" 
                  className="form-input"
                  placeholder={quickGuestIsAnonymous ? 'Acompañante Anónimo' : 'Nombre Completo'}
                  value={quickGuestName}
                  disabled={quickGuestIsAnonymous}
                  onChange={(e) => setQuickGuestName(e.target.value)}
                  required={!quickGuestIsAnonymous}
                  autoFocus
                />
              </div>

              <div className="form-group" style={{ display: 'flex', gap: '1.5rem', marginTop: '1rem' }}>
                <label className="child-menu-switch">
                  <input 
                    type="checkbox"
                    checked={quickGuestIsAnonymous}
                    onChange={(e) => {
                      setQuickGuestIsAnonymous(e.target.checked);
                      if (e.target.checked) setQuickGuestName('');
                    }}
                    style={{ cursor: 'pointer' }}
                  />
                  <span>¿Es Anónimo? (+1)</span>
                </label>

                <label className="child-menu-switch">
                  <input 
                    type="checkbox"
                    checked={quickGuestIsChild}
                    onChange={(e) => setQuickGuestIsChild(e.target.checked)}
                    style={{ cursor: 'pointer' }}
                  />
                  <span>Menú Infantil 👶</span>
                </label>
              </div>

              <div className="modal-actions">
                <button 
                  type="button" 
                  className="btn-secondary" 
                  onClick={() => setAddingGuestToGroupId(null)}
                >
                  Cancelar
                </button>
                <button type="submit" className="btn-primary">
                  Añadir Invitado
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export default AdminPanel;
