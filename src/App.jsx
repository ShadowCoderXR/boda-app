import React, { useState, useEffect } from 'react';
import LoginView from './components/LoginView';
import AdminPanel from './components/AdminPanel';
import GodparentsPanel from './components/GodparentsPanel';
import InvitationView from './components/InvitationView';
import GalleryView from './components/GalleryView';

function App() {
  // Enrutamiento básico basado en la URL
  const [route, setRoute] = useState(() => {
    const path = window.location.pathname;
    if (path.startsWith('/invitacion/')) {
      const m = path.match(/^\/invitacion\/(\d+)$/);
      return { page: 'invitacion', id: m ? m[1] : null };
    }
    if (path === '/padrinos') {
      return { page: 'padrinos' };
    }
    if (path === '/galeria') {
      return { page: 'galeria' };
    }
    return { page: 'dashboard' };
  });

  const navigateTo = (path) => {
    window.history.pushState({}, '', path);
    if (path.startsWith('/invitacion/')) {
      const m = path.match(/^\/invitacion\/(\d+)$/);
      setRoute({ page: 'invitacion', id: m ? m[1] : null });
    } else if (path === '/padrinos') {
      setRoute({ page: 'padrinos' });
    } else if (path === '/galeria') {
      setRoute({ page: 'galeria' });
    } else {
      setRoute({ page: 'dashboard' });
    }
  };

  useEffect(() => {
    const handlePopState = () => {
      const path = window.location.pathname;
      if (path.startsWith('/invitacion/')) {
        const m = path.match(/^\/invitacion\/(\d+)$/);
        setRoute({ page: 'invitacion', id: m ? m[1] : null });
      } else if (path === '/padrinos') {
        setRoute({ page: 'padrinos' });
      } else if (path === '/galeria') {
        setRoute({ page: 'galeria' });
      } else {
        setRoute({ page: 'dashboard' });
      }
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  // Estado de Autenticación
  const [token, setToken] = useState(() => {
    return localStorage.getItem('adminToken') || '';
  });

  const handleLoginSuccess = (newToken) => {
    setToken(newToken);
    localStorage.setItem('adminToken', newToken);
    navigateTo('/');
  };

  const handleLogout = () => {
    setToken('');
    localStorage.removeItem('adminToken');
    navigateTo('/');
  };

  const handleUnauthorized = () => {
    setToken('');
    localStorage.removeItem('adminToken');
    alert('Tu sesión ha expirado o no tienes permisos. Por favor inicia sesión de nuevo.');
    navigateTo('/');
  };

  // Modo Oscuro
  const [darkMode, setDarkMode] = useState(() => {
    return localStorage.getItem('darkMode') === 'true';
  });

  // Sincronizar Modo Oscuro con body
  useEffect(() => {
    if (darkMode) {
      document.body.classList.add('dark-mode');
    } else {
      document.body.classList.remove('dark-mode');
    }
    localStorage.setItem('darkMode', darkMode);
  }, [darkMode]);

  // RENDERIZADO CONDICIONAL DE VISTAS

  // 1. Vista Pública de Invitación (No requiere Login)
  if (route.page === 'invitacion') {
    return (
      <InvitationView 
        id={route.id} 
        navigateTo={navigateTo} 
      />
    );
  }

  // 1b. Vista Pública de Galería (No requiere Login)
  if (route.page === 'galeria') {
    return (
      <GalleryView 
        navigateTo={navigateTo} 
        darkMode={darkMode}
        setDarkMode={setDarkMode}
      />
    );
  }

  // 2. Si no tiene token y quiere acceder al Admin, mostrar Login
  if (!token) {
    return (
      <LoginView 
        onLoginSuccess={handleLoginSuccess} 
      />
    );
  }

  // 3. Panel de Administración de Padrinos (Requiere Login)
  if (route.page === 'padrinos') {
    return (
      <GodparentsPanel
        token={token}
        onUnauthorized={handleUnauthorized}
        onLogout={handleLogout}
        navigateTo={navigateTo}
        darkMode={darkMode}
        setDarkMode={setDarkMode}
      />
    );
  }

  // 4. Panel de Administración General de Invitados (Default, Requiere Login)
  return (
    <AdminPanel
      token={token}
      onUnauthorized={handleUnauthorized}
      onLogout={handleLogout}
      navigateTo={navigateTo}
      darkMode={darkMode}
      setDarkMode={setDarkMode}
    />
  );
}

export default App;
