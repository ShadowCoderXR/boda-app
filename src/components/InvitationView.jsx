import React, { useState, useEffect } from 'react';

function InvitationView({ id, navigateTo }) {
  const [inviteGroup, setInviteGroup] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saveStatus, setSaveStatus] = useState(''); // 'saving', 'saved', 'error', ''
  const [inviteError, setInviteError] = useState(null);

  useEffect(() => {
    const fetchInviteData = async () => {
      setLoading(true);
      try {
        const res = await fetch(`/api/groups/${id}`);
        if (res.ok) {
          const data = await res.json();
          setInviteGroup(data);
        } else {
          setInviteError('No pudimos encontrar tu invitación. Por favor verifica el enlace.');
        }
      } catch (err) {
        console.error(err);
        setInviteError('Hubo un error de red. Intenta de nuevo más tarde.');
      } finally {
        setLoading(false);
      }
    };

    if (id) {
      fetchInviteData();
    }
  }, [id]);

  const handleInviteGuestStatus = async (guestId, newStatus) => {
    setSaveStatus('saving');
    try {
      const res = await fetch(`/api/guests/${guestId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: newStatus })
      });

      if (!res.ok) throw new Error();

      const updated = await res.json();
      
      setInviteGroup(prev => ({
        ...prev,
        guests: prev.guests.map(g => g.id === guestId ? updated : g)
      }));
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus(''), 3000);
    } catch (err) {
      setSaveStatus('error');
    }
  };

  const handleInviteGuestChildMenu = async (guestId, currentVal) => {
    setSaveStatus('saving');
    try {
      const res = await fetch(`/api/guests/${guestId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_child_menu: !currentVal })
      });

      if (!res.ok) throw new Error();

      const updated = await res.json();
      
      setInviteGroup(prev => ({
        ...prev,
        guests: prev.guests.map(g => g.id === guestId ? updated : g)
      }));
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus(''), 3000);
    } catch (err) {
      setSaveStatus('error');
    }
  };

  const handleInviteGuestNameChange = async (guestId, newName) => {
    if (!newName.trim()) return;
    setSaveStatus('saving');
    try {
      const res = await fetch(`/api/guests/${guestId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newName.trim() })
      });

      if (!res.ok) throw new Error();

      const updated = await res.json();
      
      setInviteGroup(prev => ({
        ...prev,
        guests: prev.guests.map(g => g.id === guestId ? updated : g)
      }));
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus(''), 3000);
    } catch (err) {
      setSaveStatus('error');
    }
  };

  return (
    <div className="app-container" style={{ maxWidth: '600px', padding: '2rem 1rem 4rem 1rem' }}>
      
      {/* Blobs de Fondo */}
      <div className="bg-blobs">
        <div className="blob blob-1"></div>
        <div className="blob blob-2"></div>
      </div>

      {/* Encabezado Decorativo de la Boda */}
      <div className="header" style={{ marginBottom: '2.5rem' }}>
        <div className="cursive-text" style={{ marginBottom: '0.25rem' }}>Nuestra Boda</div>
        <h1 style={{ fontSize: '2.5rem', fontFamily: 'var(--font-serif)', fontWeight: '300' }}>¡Estás Invitado!</h1>
        <div className="header-decor">
          <span>⚜️</span>
        </div>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', padding: '4rem', color: 'var(--text-secondary)' }}>
          <span style={{ fontFamily: 'var(--font-serif)', fontSize: '1.25rem' }}>✨ Preparando tu pase digital...</span>
        </div>
      ) : inviteError ? (
        <div className="invite-envelope" style={{ textAlign: 'center', padding: '3.5rem 2rem' }}>
          <span style={{ fontSize: '3rem' }}>✉️</span>
          <h2 style={{ margin: '1rem 0 0.5rem 0', fontFamily: 'var(--font-serif)' }}>Enlace no Válido</h2>
          <p style={{ color: 'var(--text-secondary)', marginBottom: '2.5rem' }}>{inviteError}</p>
          <button className="btn-primary" onClick={() => navigateTo('/')}>
            Ir a la Página de Inicio
          </button>
        </div>
      ) : (
        <div className="invite-envelope">
          
          {/* Indicador de Estado de Guardado en tiempo real */}
          {saveStatus && (
            <div style={{
              position: 'absolute',
              top: '25px',
              right: '25px',
              fontSize: '0.75rem',
              padding: '5px 12px',
              borderRadius: '20px',
              fontWeight: '700',
              letterSpacing: '0.05em',
              textTransform: 'uppercase',
              background: saveStatus === 'saving' ? 'var(--color-pending-light)' : 
                          saveStatus === 'saved' ? 'var(--color-confirmed-light)' : 'var(--color-declined-light)',
              color: saveStatus === 'saving' ? 'var(--color-pending)' : 
                     saveStatus === 'saved' ? 'var(--color-confirmed)' : 'var(--color-declined)',
              transition: 'var(--transition-smooth)',
              zIndex: 10
            }}>
              {saveStatus === 'saving' && '⌛ Guardando...'}
              {saveStatus === 'saved' && '✅ Guardado'}
              {saveStatus === 'error' && '⚠️ Error'}
            </div>
          )}

          <div style={{ textAlign: 'center', marginBottom: '2.5rem' }}>
            <div className="cursive-text" style={{ fontSize: '3rem', margin: '0.5rem 0' }}>
              {inviteGroup?.name}
            </div>
            <div className="floral-divider">
              <span>🌹</span>
            </div>
            <p style={{ color: 'var(--text-secondary)', marginTop: '0.75rem', fontFamily: 'var(--font-serif)', fontSize: '1.2rem', fontWeight: '300', fontStyle: 'italic' }}>
              Queremos compartir contigo este día inolvidable. Por favor, confirma la asistencia individual de cada miembro:
            </p>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            {inviteGroup?.guests.map((guest, idx) => (
              <div key={guest.id} className="invite-guest-card">
                
                {/* Nombre del Invitado */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                  <span className="form-label" style={{ fontSize: '0.75rem', opacity: 0.7 }}>
                    Invitado {idx + 1} {guest.is_anonymous && '(Acompañante)'}
                  </span>
                  {guest.is_anonymous ? (
                    <input 
                      type="text" 
                      className="form-input"
                      placeholder="Escribe aquí el nombre del acompañante..."
                      defaultValue={guest.name === 'Acompañante Anónimo' ? '' : guest.name}
                      onBlur={(e) => handleInviteGuestNameChange(guest.id, e.target.value)}
                      style={{ borderStyle: 'dashed', borderColor: 'var(--accent-gold)' }}
                    />
                  ) : (
                    <div style={{ fontSize: '1.35rem', fontFamily: 'var(--font-serif)', fontWeight: '500', color: 'var(--text-primary)' }}>
                      {guest.name}
                    </div>
                  )}
                </div>

                {/* Selección de Asistencia (Uno a Uno) */}
                <div className="rsvp-buttons-container">
                  <button
                    type="button"
                    className="status-badge confirmed"
                    style={{
                      flex: 1,
                      justifyContent: 'center',
                      border: guest.status === 'confirmed' ? '1px solid var(--color-confirmed)' : '1px solid transparent',
                      opacity: guest.status === 'confirmed' ? 1 : 0.45,
                      transform: guest.status === 'confirmed' ? 'scale(1.02)' : 'none',
                      boxShadow: guest.status === 'confirmed' ? '0 4px 10px rgba(46,125,50,0.15)' : 'none'
                    }}
                    onClick={() => handleInviteGuestStatus(guest.id, 'confirmed')}
                  >
                    Asistiré ✅
                  </button>

                  <button
                    type="button"
                    className="status-badge declined"
                    style={{
                      flex: 1,
                      justifyContent: 'center',
                      border: guest.status === 'declined' ? '1px solid var(--color-declined)' : '1px solid transparent',
                      opacity: guest.status === 'declined' ? 1 : 0.45,
                      transform: guest.status === 'declined' ? 'scale(1.02)' : 'none',
                      boxShadow: guest.status === 'declined' ? '0 4px 10px rgba(198,40,40,0.15)' : 'none'
                    }}
                    onClick={() => handleInviteGuestStatus(guest.id, 'declined')}
                  >
                    No podré ir ❌
                  </button>
                </div>

                {/* Menú de Niños */}
                {guest.status === 'confirmed' && (
                  <div 
                    className={`child-menu-switch ${guest.is_child_menu ? 'active' : ''}`}
                    onClick={() => handleInviteGuestChildMenu(guest.id, guest.is_child_menu)}
                    style={{ alignSelf: 'flex-start', marginTop: '0.25rem' }}
                  >
                    <div className="switch-track">
                      <div className="switch-thumb"></div>
                    </div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>¿Requiere menú infantil? 👶</span>
                  </div>
                )}

              </div>
            ))}
          </div>

          <div style={{ textAlign: 'center', marginTop: '3rem', borderTop: '1px solid var(--border-gold)', paddingTop: '1.5rem' }}>
            <p style={{ fontStyle: 'italic', fontSize: '0.85rem', color: 'var(--text-secondary)', fontFamily: 'var(--font-serif)' }}>
              Tus confirmaciones se guardan al instante. ¡Nos vemos pronto!
            </p>
          </div>

        </div>
      )}

      {/* Enlace sutil para volver al Panel */}
      <div style={{ textAlign: 'center', marginTop: '2.5rem' }}>
        <button 
          onClick={() => navigateTo('/')}
          style={{
            background: 'transparent',
            border: 'none',
            color: 'var(--accent-gold)',
            textDecoration: 'underline',
            cursor: 'pointer',
            fontSize: '0.85rem',
            letterSpacing: '0.05em',
            textTransform: 'uppercase'
          }}
        >
          ← Volver al Panel de Bodas
        </button>
      </div>

    </div>
  );
}

export default InvitationView;
