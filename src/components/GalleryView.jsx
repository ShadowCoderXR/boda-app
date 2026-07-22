import React, { useState, useEffect } from 'react';
import imageCompression from 'browser-image-compression';

function GalleryView({ navigateTo, darkMode, setDarkMode }) {
  const [photos, setPhotos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [guestName, setGuestName] = useState('');
  const [selectedFile, setSelectedFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [uploadStatus, setUploadStatus] = useState(''); // 'comprimiendo', 'obteniendo-url', 'subiendo-r2', 'guardando-db', 'completado'

  const fetchPhotos = async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/photos');
      if (res.ok) {
        const data = await res.json();
        setPhotos(data);
      } else {
        console.error('Error al obtener fotos');
      }
    } catch (err) {
      console.error('Error de red al obtener fotos:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPhotos();
  }, []);

  const handleFileChange = (e) => {
    if (e.target.files && e.target.files[0]) {
      setSelectedFile(e.target.files[0]);
    }
  };

  const handleUploadSubmit = async (e) => {
    e.preventDefault();
    if (!selectedFile || !guestName.trim()) {
      alert('Por favor selecciona una foto y escribe tu nombre.');
      return;
    }

    setUploading(true);
    try {
      // 1. Comprimir imagen
      setUploadStatus('comprimiendo');
      const compressionOptions = {
        maxSizeMB: 1,
        maxWidthOrHeight: 1920,
        useWebWorker: true,
      };
      console.log('Comprimiendo imagen...');
      const compressedBlob = await imageCompression(selectedFile, compressionOptions);

      // 2. Pedir upload-url al backend
      setUploadStatus('obteniendo-url');
      const filename = selectedFile.name;
      const fileType = compressedBlob.type;
      console.log(`Obteniendo URL de subida para: ${filename} (${fileType})...`);
      const urlRes = await fetch(`/api/upload-url?name=${encodeURIComponent(filename)}&type=${encodeURIComponent(fileType)}`);
      if (!urlRes.ok) {
        throw new Error('Error al obtener la URL de subida del servidor.');
      }
      const { uploadUrl, filename: finalFilename } = await urlRes.json();

      // 3. Hacer fetch PUT directo a R2
      setUploadStatus('subiendo-r2');
      console.log('Subiendo a Cloudflare R2...');
      const putRes = await fetch(uploadUrl, {
        method: 'PUT',
        headers: {
          'Content-Type': fileType,
        },
        body: compressedBlob,
      });

      if (!putRes.ok) {
        throw new Error('Fallo al subir la imagen al almacenamiento R2.');
      }

      // 4. Hacer POST a /api/photos para guardar el registro
      setUploadStatus('guardando-db');
      console.log('Registrando foto en la base de datos...');
      const postRes = await fetch('/api/photos', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          guest_name: guestName.trim(),
          filename: finalFilename,
        }),
      });

      if (!postRes.ok) {
        throw new Error('Fallo al registrar la foto en la base de datos.');
      }

      setUploadStatus('completado');
      console.log('Subida completada con éxito!');
      
      // Reiniciar estado
      setGuestName('');
      setSelectedFile(null);
      setModalOpen(false);
      
      // Actualizar galería
      await fetchPhotos();
    } catch (err) {
      console.error('Error durante la subida:', err);
      alert(`Ocurrió un error al subir la foto: ${err.message}`);
    } finally {
      setUploading(false);
      setUploadStatus('');
    }
  };

  return (
    <div className="app-container">
      {/* HEADER DE LA GALERÍA */}
      <header className="header" style={{ marginBottom: '3rem', position: 'relative' }}>
        <div style={{ position: 'absolute', top: 0, right: 0, display: 'flex', gap: '8px' }}>
          <button 
            type="button" 
            className="theme-toggle-btn"
            style={{ padding: '0.5rem 1rem', fontSize: '0.8rem' }}
            onClick={() => setDarkMode(!darkMode)}
          >
            {darkMode ? '☀️ Modo Claro' : '🌙 Modo Oscuro'}
          </button>
          <button 
            type="button" 
            className="theme-toggle-btn"
            style={{ padding: '0.5rem 1rem', fontSize: '0.8rem', borderColor: 'var(--accent-gold)', color: 'var(--accent-gold)' }}
            onClick={() => navigateTo('/')}
          >
            💍 Panel Principal
          </button>
        </div>

        <h1 className="header-title">Álbum de Recuerdos</h1>
        <p className="header-subtitle">Comparte tus momentos favoritos de nuestra boda</p>
        <div className="header-decor">
          <span className="decor-flower">❀</span>
        </div>
      </header>

      {/* ACCIÓN DE SUBIDA */}
      <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '3rem' }}>
        <button 
          type="button" 
          className="btn-primary" 
          style={{ maxWidth: '300px' }}
          onClick={() => setModalOpen(true)}
        >
          📸 Compartir una Foto
        </button>
      </div>

      {/* ÁLBUM DE FOTOS */}
      {loading ? (
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', margin: '4rem 0' }}>
          <div className="loading-spinner" style={{ border: '4px solid var(--border-subtle)', borderTop: '4px solid var(--accent-gold)', borderRadius: '50%', width: '40px', height: '40px', animation: 'spin 1s linear infinite' }}></div>
          <p style={{ marginTop: '1rem', color: 'var(--text-secondary)', fontStyle: 'italic' }}>Cargando galería de fotos...</p>
        </div>
      ) : photos.length === 0 ? (
        <div className="card-panel" style={{ textAlign: 'center', padding: '4rem 2rem' }}>
          <p style={{ fontSize: '1.25rem', color: 'var(--text-secondary)', fontFamily: 'var(--font-serif)', fontStyle: 'italic' }}>
            Aún no hay fotos compartidas. ¡Sé el primero en subir una!
          </p>
        </div>
      ) : (
        <div className="gallery-masonry" style={{ 
          columnCount: 'auto', 
          columnWidth: '320px', 
          columnGap: '1.5rem', 
          width: '100%',
          margin: '0 auto'
        }}>
          {photos.map((photo) => (
            <div 
              key={photo.id} 
              className="card-panel" 
              style={{ 
                breakInside: 'avoid', 
                marginBottom: '1.5rem', 
                padding: '0.75rem', 
                display: 'flex', 
                flexDirection: 'column',
                transition: 'transform 0.3s ease',
                cursor: 'pointer'
              }}
              onMouseEnter={(e) => e.currentTarget.style.transform = 'translateY(-4px)'}
              onMouseLeave={(e) => e.currentTarget.style.transform = 'translateY(0)'}
            >
              <img 
                src={photo.image_url} 
                alt={`Compartida por ${photo.guest_name}`} 
                loading="lazy" 
                style={{ 
                  width: '100%', 
                  borderRadius: 'var(--radius-sm)', 
                  objectFit: 'cover',
                  display: 'block'
                }} 
              />
              <div style={{ 
                marginTop: '0.75rem', 
                padding: '0.25rem 0.5rem',
                borderTop: '1px dashed var(--border-gold)',
                paddingTop: '0.5rem',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center'
              }}>
                <span style={{ 
                  fontWeight: '600', 
                  fontSize: '0.85rem', 
                  color: 'var(--text-primary)' 
                }}>
                  👤 {photo.guest_name}
                </span>
                <span style={{ 
                  fontSize: '0.7rem', 
                  color: 'var(--text-secondary)',
                  fontStyle: 'italic'
                }}>
                  {new Date(photo.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* MODAL DE SUBIDA */}
      {modalOpen && (
        <div 
          className="modal-overlay" 
          style={{ 
            position: 'fixed', 
            top: 0, 
            left: 0, 
            right: 0, 
            bottom: 0, 
            backgroundColor: 'rgba(10, 4, 5, 0.75)', 
            backdropFilter: 'blur(8px)',
            display: 'flex', 
            justifyContent: 'center', 
            alignItems: 'center', 
            zIndex: 1000 
          }}
        >
          <div 
            className="card-panel" 
            style={{ 
              maxWidth: '500px', 
              width: '90%', 
              padding: '2.5rem',
              position: 'relative'
            }}
          >
            <button 
              type="button"
              className="btn-danger-icon"
              style={{ position: 'absolute', top: '1.25rem', right: '1.25rem', fontSize: '1.25rem' }}
              onClick={() => !uploading && setModalOpen(false)}
              disabled={uploading}
            >
              ✕
            </button>

            <h2 className="card-title" style={{ marginBottom: '1.5rem', fontFamily: 'var(--font-serif)', fontSize: '1.6rem' }}>
              ✨ Comparte un Momento
            </h2>

            <form onSubmit={handleUploadSubmit}>
              <div className="form-group" style={{ marginBottom: '1.25rem' }}>
                <label className="form-label" htmlFor="guestNameInput">
                  Tu nombre o familia
                </label>
                <input 
                  type="text" 
                  id="guestNameInput"
                  className="form-input"
                  placeholder="Ej. Familia López o María y Juan"
                  value={guestName}
                  onChange={(e) => setGuestName(e.target.value)}
                  required
                  disabled={uploading}
                />
              </div>

              <div className="form-group" style={{ marginBottom: '1.75rem' }}>
                <label className="form-label" htmlFor="photoFileInput">
                  Selecciona la foto
                </label>
                <input 
                  type="file" 
                  id="photoFileInput"
                  className="form-input"
                  accept="image/*"
                  onChange={handleFileChange}
                  required
                  disabled={uploading}
                  style={{ padding: '0.6rem' }}
                />
              </div>

              {uploading && (
                <div style={{ marginBottom: '1.5rem', textAlign: 'center' }}>
                  <div className="loading-spinner" style={{ border: '3px solid var(--border-subtle)', borderTop: '3px solid var(--accent-gold)', borderRadius: '50%', width: '24px', height: '24px', animation: 'spin 1s linear infinite', margin: '0 auto' }}></div>
                  <p style={{ marginTop: '0.75rem', fontSize: '0.85rem', color: 'var(--accent-gold)', fontWeight: '600' }}>
                    {uploadStatus === 'comprimiendo' && '⚙️ Optimizando imagen...'}
                    {uploadStatus === 'obteniendo-url' && '📡 Solicitando enlace seguro...'}
                    {uploadStatus === 'subiendo-r2' && '🚀 Subiendo foto a Cloudflare R2...'}
                    {uploadStatus === 'guardando-db' && '💾 Guardando en el álbum...'}
                    {uploadStatus === 'completado' && '✅ ¡Listo!'}
                  </p>
                </div>
              )}

              <div style={{ display: 'flex', gap: '1rem', marginTop: '2rem' }}>
                <button 
                  type="button" 
                  className="btn-secondary" 
                  style={{ flex: 1 }}
                  onClick={() => setModalOpen(false)}
                  disabled={uploading}
                >
                  Cancelar
                </button>
                <button 
                  type="submit" 
                  className="btn-primary" 
                  style={{ flex: 1 }}
                  disabled={uploading}
                >
                  {uploading ? 'Subiendo...' : 'Subir Foto'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ESTILO DEL LOADING SPIN EN LÍNEA */}
      <style>{`
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}

export default GalleryView;
