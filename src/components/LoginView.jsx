import React, { useState } from 'react';

function LoginView({ onLoginSuccess }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!username.trim() || !password.trim()) {
      setError('Por favor completa todos los campos.');
      return;
    }
    setError('');
    setLoading(true);

    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: username.trim(), password })
      });

      const data = await res.json();

      if (res.ok) {
        onLoginSuccess(data.token);
      } else {
        setError(data.error || 'Credenciales incorrectas.');
      }
    } catch (err) {
      console.error(err);
      setError('Error al conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-container" style={{ maxWidth: '480px', minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div className="bg-blobs">
        <div className="blob blob-1"></div>
        <div className="blob blob-2"></div>
      </div>

      <div className="card-panel" style={{ width: '100%', padding: '3rem 2rem' }}>
        <header className="header" style={{ marginBottom: '2rem' }}>
          <div className="cursive-text">Nuestra Boda</div>
          <h2 style={{ fontSize: '1.85rem', fontWeight: 300, color: 'var(--text-primary)', marginTop: '0.5rem' }}>Administración</h2>
          <div className="header-decor" style={{ marginTop: '1rem' }}>
            <span>⚜️</span>
          </div>
        </header>

        <form onSubmit={handleSubmit} className="form-group" style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {error && (
            <div style={{
              background: 'var(--color-declined-light)',
              color: 'var(--color-declined)',
              border: '1px solid rgba(198, 40, 40, 0.2)',
              padding: '0.75rem 1rem',
              borderRadius: '8px',
              fontSize: '0.85rem',
              textAlign: 'center',
              fontWeight: 500
            }}>
              {error}
            </div>
          )}

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            <label style={{ fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 600, color: 'var(--text-secondary)' }}>
              Usuario
            </label>
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Ej. admin"
              disabled={loading}
              style={{
                width: '100%',
                padding: '0.8rem 1rem',
                border: '1px solid var(--border-gold)',
                borderRadius: '8px',
                background: 'var(--bg-glass)',
                color: 'var(--text-primary)',
                outline: 'none',
                fontFamily: 'var(--font-sans)',
                fontSize: '0.95rem',
                transition: 'var(--transition-smooth)'
              }}
              onFocus={(e) => e.target.style.borderColor = 'var(--accent-gold)'}
              onBlur={(e) => e.target.style.borderColor = 'var(--border-gold)'}
            />
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            <label style={{ fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 600, color: 'var(--text-secondary)' }}>
              Contraseña
            </label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              disabled={loading}
              style={{
                width: '100%',
                padding: '0.8rem 1rem',
                border: '1px solid var(--border-gold)',
                borderRadius: '8px',
                background: 'var(--bg-glass)',
                color: 'var(--text-primary)',
                outline: 'none',
                fontFamily: 'var(--font-sans)',
                fontSize: '0.95rem',
                transition: 'var(--transition-smooth)'
              }}
              onFocus={(e) => e.target.style.borderColor = 'var(--accent-gold)'}
              onBlur={(e) => e.target.style.borderColor = 'var(--border-gold)'}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="btn-primary"
            style={{
              width: '100%',
              padding: '0.9rem',
              marginTop: '1rem',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontWeight: 600,
              letterSpacing: '0.05em'
            }}
          >
            {loading ? 'Validando...' : 'Acceder al Panel'}
          </button>
        </form>
      </div>
    </div>
  );
}

export default LoginView;
