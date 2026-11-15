import React, { useState, useEffect } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMapEvents, Circle, Polygon } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import './App.css';
import { apiRequest, setCsrfToken } from './api/api';
import icon from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';

let DefaultIcon = L.icon({
    iconUrl: icon,
    shadowUrl: iconShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41]
});
L.Marker.prototype.options.icon = DefaultIcon;

function App() {
  const [user, setUser] = useState(null);
  const [authLoading, setAuthLoading] = useState(true);
  const [loginError, setLoginError] = useState('');
  const [isDarkMode, setIsDarkMode] = useState(false);
  const [language, setLanguage] = useState('fr');
  const [view, setView] = useState('map'); 
  const [pois, setPois] = useState([]);
  
  const [selectionMode, setSelectionMode] = useState('radius');
  const [selectedLocation, setSelectedLocation] = useState(null);
  const [polygonPoints, setPolygonPoints] = useState([]);
  const [radiusValue, setRadiusValue] = useState(100);
  
  const [showAddPanel, setShowAddPanel] = useState(false);
  const [formData, setFormData] = useState({ name: '', type: 'Kiosk', company: 'lumiere' });

  const fetchPois = async () => {
    const response = await apiRequest('get_pois.php');
    if (response.status === 'success') setPois(response.data);
  };

  useEffect(() => {
    apiRequest('session.php').then(response => {
      if (response.status === 'success') {
        setCsrfToken(response.csrf_token);
        setUser(response.user);
      }
    }).finally(() => setAuthLoading(false));
  }, []);

  useEffect(() => {
    if (user) fetchPois();
  }, [user]);

  const handleLogin = async (username, password) => {
    const response = await apiRequest('login.php', 'POST', { username, password });
    if (response.status === 'success') {
      setCsrfToken(response.csrf_token);
      setUser(response.user);
      setLoginError('');
    } else {
      setLoginError(response.message || 'Sign-in failed.');
    }
    return response;
  };

  const handleLogout = async () => {
    const response = await apiRequest('logout.php', 'POST', {});
    if (response.status === 'success') {
      setUser(null);
      setCsrfToken('');
    }
  };

  if (authLoading) return <div className="login-container"><p>Loading…</p></div>;

  const handleSave = async (e) => {
    e.preventDefault();
    
    if (selectionMode === 'radius' && !selectedLocation) {
      alert("Veuillez sélectionner une position sur la carte.");
      return;
    }
    
    if (selectionMode === 'polygon' && polygonPoints.length < 3) {
      alert("Veuillez sélectionner au moins 3 points pour créer un polygone.");
      return;
    }

    const payload = {
      ...formData,
      geometry_type: selectionMode,
    };

    if (selectionMode === 'radius') {
      payload.lat = selectedLocation.lat;
      payload.lng = selectedLocation.lng;
      payload.radius = radiusValue;
    } else if (selectionMode === 'polygon') {
      payload.coordinates = polygonPoints;
    }

    const response = await apiRequest('add_poi.php', 'POST', payload);
    if (response.status === 'success') {
      alert("Enregistré avec succès !");
      setShowAddPanel(false);
      setFormData({ name: '', type: 'Kiosk', company: 'lumiere' });
      setSelectedLocation(null);
      setPolygonPoints([]);
      fetchPois();
    } else {
      alert("Erreur: " + response.message);
    }
  };

  if (!user) return <Login onLogin={handleLogin} error={loginError} />;

  return (
    <div className={`app-container ${isDarkMode ? 'dark' : 'light'}`}>
      <Sidebar user={user} setView={setView} currentView={view} onLogout={handleLogout} />
      
      <main className="main-content">
        {view === 'map' && (
          <>
            <div className="map-controls">
              <button className={`btn ${selectionMode === 'radius' ? 'btn-primary' : 'btn-outline'}`} onClick={() => { setSelectionMode('radius'); setPolygonPoints([]); }}>📍 Point</button>
              <button className={`btn ${selectionMode === 'polygon' ? 'btn-primary' : 'btn-outline'}`} onClick={() => { setSelectionMode('polygon'); setSelectedLocation(null); }}>⬢ Zone</button>
            </div>
            <MapView pois={pois} selectionMode={selectionMode} selectedLocation={selectedLocation} setSelectedLocation={setSelectedLocation} polygonPoints={polygonPoints} setPolygonPoints={setPolygonPoints} radiusValue={radiusValue} />
          </>
        )}
        
        {view === 'users' && user.role === 'admin' && <UsersManager />}
        {view === 'activity' && user.role === 'admin' && <ActivityTracker />}
        {view === 'settings' && <Settings isDarkMode={isDarkMode} setIsDarkMode={setIsDarkMode} language={language} setLanguage={setLanguage} />}

        {(selectedLocation || (selectionMode === 'polygon' && polygonPoints.length >= 3)) && !showAddPanel && (
          <div className="bottom-alert">
            <span>Enregistrer cette {selectionMode === 'radius' ? 'position' : 'zone'} ?</span>
            <button className="btn btn-success" onClick={() => setShowAddPanel(true)}>Oui</button>
            <button className="btn btn-danger" onClick={() => { setSelectedLocation(null); setPolygonPoints([]); }}>Non</button>
          </div>
        )}

        {showAddPanel && (
          <div className="side-panel">
            <h3>Nouveau Point d'Intérêt</h3>
            <form onSubmit={handleSave}>
              <div className="form-group">
                <label>Nom *</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} placeholder="Entrez le nom du point" />
              </div>
              <div className="form-group">
                <label>Entreprise</label>
                <select value={formData.company} onChange={e => setFormData({...formData, company: e.target.value})}>
                  <option value="lumiere">Lumière</option>
                  <option value="gias">GIAS</option>
                </select>
              </div>
              <div className="form-group">
                <label>Type</label>
                <select value={formData.type} onChange={e => setFormData({...formData, type: e.target.value})}>
                  <option>Kiosk</option><option>Magasin</option><option>Mécanicien</option>
                </select>
              </div>
              {selectionMode === 'radius' && (
                <div className="form-group">
                  <label>Rayon: {radiusValue}m</label>
                  <input type="range" min="10" max="1000" value={radiusValue} onChange={e => setRadiusValue(parseInt(e.target.value))} />
                </div>
              )}
              {selectionMode === 'polygon' && (
                <div className="form-group">
                  <label>Polygone: {polygonPoints.length} points</label>
                  <p style={{fontSize: '0.9em', color: '#666'}}>Zone créée avec {polygonPoints.length} points</p>
                </div>
              )}
              <div style={{display: 'flex', gap: '10px', marginTop: '20px'}}>
                <button type="submit" className="btn btn-success w-100">Enregistrer</button>
                <button type="button" className="btn btn-danger w-100" onClick={() => { setShowAddPanel(false); setFormData({ name: '', type: 'Kiosk', company: 'lumiere' }); }}>Annuler</button>
              </div>
            </form>
          </div>
        )}
      </main>
    </div>
  );
}

function MapView({ pois, selectionMode, selectedLocation, setSelectedLocation, polygonPoints, setPolygonPoints, radiusValue }) {
  function InteractionHandler() {
    useMapEvents({ click(e) { selectionMode === 'radius' ? setSelectedLocation(e.latlng) : setPolygonPoints([...polygonPoints, [e.latlng.lat, e.latlng.lng]]); } });
    return null;
  }
  return (
    <div id="map" style={{ height: '100%', width: '100%' }}>
      <MapContainer center={[36.8065, 10.1815]} zoom={13} style={{ height: '100%', width: '100%' }}>
        <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
        {pois.map((poi, idx) => {
          const color = poi.company === 'gias' ? 'orange' : 'blue';
          if (poi.geometry_type === 'polygon' && poi.coordinates) {
            try {
              const coords = JSON.parse(poi.coordinates);
              return <Polygon key={idx} positions={coords} color={color}><Popup>{poi.name} ({poi.company})</Popup></Polygon>;
            } catch (e) {
              console.error('Erreur parsing coordinates:', e);
              return null;
            }
          } else {
            return (
              <React.Fragment key={idx}>
                <Marker position={[poi.lat, poi.lng]}><Popup>{poi.name} <br/> {poi.company}</Popup></Marker>
                {poi.radius && <Circle center={[poi.lat, poi.lng]} radius={poi.radius} color={color} />}
              </React.Fragment>
            );
          }
        })}
        <InteractionHandler />
        {selectionMode === 'radius' && selectedLocation && <><Marker position={selectedLocation} /><Circle center={selectedLocation} radius={radiusValue} color="green" /></>}
        {selectionMode === 'polygon' && polygonPoints.length > 0 && <Polygon positions={polygonPoints} color="green" />}
      </MapContainer>
    </div>
  );
}

function UsersManager() {
  const [users, setUsers] = useState([]);
  const [newUser, setNewUser] = useState({ username: '', password: '', role: 'worker' });
  const [showAdd, setShowAdd] = useState(false);

  const fetchUsers = async () => {
    const res = await apiRequest('get_users.php');
    if (res.status === 'success') setUsers(res.data);
  };

  useEffect(() => { fetchUsers(); }, []);

  const handleAdd = async (e) => {
    e.preventDefault();
    const res = await apiRequest('manage_user.php?action=add', 'POST', newUser);
    if (res.status === 'success') { 
      setShowAdd(false); 
      setNewUser({ username: '', password: '', role: 'worker' });
      fetchUsers(); 
    } else alert(res.message);
  };

  const handleDelete = async (id) => {
    if (window.confirm("Supprimer cet utilisateur ?")) {
      const res = await apiRequest('manage_user.php?action=delete', 'POST', { id });
      if (res.status === 'success') fetchUsers();
    }
  };

  return (
    <div className="p-4">
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
        <h2>👥 Gestion des Comptes</h2>
        <button className="btn btn-primary" onClick={() => setShowAdd(true)}>+ Ajouter</button>
      </div>
      {showAdd && (
        <div className="side-panel" style={{right: '20px', top: '80px', height: 'auto'}}>
          <h3>Nouvel Utilisateur</h3>
          <form onSubmit={handleAdd}>
            <div className="form-group"><label>Nom</label><input type="text" required maxLength={32} onChange={e => setNewUser({...newUser, username: e.target.value})} /></div>
            <div className="form-group"><label>Mot de passe</label><input type="password" required minLength={12} maxLength={72} autoComplete="new-password" onChange={e => setNewUser({...newUser, password: e.target.value})} /></div>
            <div className="form-group">
              <label>Rôle</label>
              <select onChange={e => setNewUser({...newUser, role: e.target.value})}>
                <option value="worker">Worker</option><option value="admin">Admin</option>
              </select>
            </div>
            <button type="submit" className="btn btn-success w-100">Créer</button>
            <button type="button" className="btn btn-danger w-100" style={{marginTop: '5px'}} onClick={() => setShowAdd(false)}>Annuler</button>
          </form>
        </div>
      )}
      <table className="custom-table" style={{marginTop: '20px'}}>
        <thead><tr><th>Nom</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          {users.map(u => (
            <tr key={u.id}>
              <td>{u.username}</td>
              <td><span className={`badge ${u.role}`}>{u.role}</span></td>
              <td>{u.status}</td>
              <td>{u.role !== 'admin' && <button className="btn btn-danger" onClick={() => handleDelete(u.id)}>Supprimer</button>}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ActivityTracker() {
  const [logs, setLogs] = useState([]);
  useEffect(() => {
    apiRequest('get_activity.php').then(res => { if(res.status === 'success') setLogs(res.data); });
  }, []);

  return (
    <div className="p-4">
      <h2> Activité Récente</h2>
      <table className="custom-table" style={{marginTop: '20px'}}>
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Description</th></tr></thead>
        <tbody>
          {logs.map(log => (
            <tr key={log.id}>
              <td>{log.created_at}</td>
              <td><strong>{log.username}</strong></td>
              <td>{log.action_type}</td>
              <td>{log.description}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Settings({ isDarkMode, setIsDarkMode, language, setLanguage }) {
  return (
    <div className="p-4">
      <h2>⚙️ Paramètres</h2>
      <div className="form-group" style={{marginTop: '20px'}}>
        <label>Mode d'affichage</label>
        <button className="btn btn-outline" onClick={() => setIsDarkMode(!isDarkMode)}>
          {isDarkMode ? 'Passer au Mode Clair ☀️' : 'Passer au Mode Sombre 🌙'}
        </button>
      </div>
      <div className="form-group">
        <label>Langue</label>
        <select value={language} onChange={e => setLanguage(e.target.value)}>
          <option value="fr">Français</option>
          <option value="ar">العربية</option>
        </select>
      </div>
    </div>
  );
}

function Login({ onLogin, error }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  return (
    <div className="login-container">
      <div className="login-box">
        <h2>Connexion</h2>
        <form onSubmit={async e => { e.preventDefault(); setSubmitting(true); try { await onLogin(username, password); } finally { setSubmitting(false); } }}>
          <div className="form-group"><label>Utilisateur</label><input type="text" autoComplete="username" required maxLength={32} value={username} onChange={e => setUsername(e.target.value)} /></div>
          <div className="form-group"><label>Mot de passe</label><input type="password" autoComplete="current-password" required maxLength={72} value={password} onChange={e => setPassword(e.target.value)} /></div>
          {error && <p role="alert" className="login-error">{error}</p>}
          <button type="submit" className="btn btn-primary w-100" disabled={submitting}>{submitting ? 'Connexion…' : 'Se connecter'}</button>
        </form>
      </div>
    </div>
  );
}

function Sidebar({ user, setView, currentView, onLogout }) {
  return (
    <div className="sidebar">
      <h2>POI Manager</h2>
      <div className={`nav-item ${currentView === 'map' ? 'active' : ''}`} onClick={() => setView('map')}> POI </div>
      {user.role === 'admin' && (
        <>
          <div className={`nav-item ${currentView === 'users' ? 'active' : ''}`} onClick={() => setView('users')}>👥 Comptes</div>
          <div className={`nav-item ${currentView === 'activity' ? 'active' : ''}`} onClick={() => setView('activity')}>📈 Activité</div>
        </>
      )}
      <div className={`nav-item ${currentView === 'settings' ? 'active' : ''}`} onClick={() => setView('settings')}>⚙️ Paramètres</div>
      <div className="nav-item logout" onClick={onLogout}> Déconnexion</div>
    </div>
  );
}

export default App;