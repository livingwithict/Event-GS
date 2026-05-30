import { useState, useCallback } from 'react';
import { useAuth } from '../hooks/useAuth';
import QrScanner from '../components/QrScanner';
import GuestCard from '../components/GuestCard';
import ActionButtons from '../components/ActionButtons';
import SearchFallback from '../components/SearchFallback';
import api from '../utils/api';

export default function Scanner() {
  const { user, logout } = useAuth();
  const [guest, setGuest] = useState(null);
  const [error, setError] = useState('');

  const handleScan = useCallback(async (code) => {
    setError('');
    try {
      const { data } = await api.post('/scan', { code });
      setGuest(data.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Scan failed');
      setGuest(null);
    }
  }, []);

  const refreshGuest = useCallback(async () => {
    if (!guest) return;
    try {
      const { data } = await api.post('/scan', { code: guest.unique_code || guest.id });
      setGuest(data.data);
    } catch {}
  }, [guest]);

  return (
    <div style={{ maxWidth: 480, margin: '0 auto', padding: 16 }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <h1>Event Scanner</h1>
        <button onClick={logout} style={{ padding: '4px 12px' }}>Logout</button>
      </header>

      <QrScanner onScan={handleScan} />

      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', padding: 12, borderRadius: 6, marginTop: 12 }}>
          {error}
        </div>
      )}

      {guest && (
        <>
          <GuestCard guest={guest} />
          <ActionButtons guest={guest} onUpdate={refreshGuest} />
        </>
      )}

      <details style={{ marginTop: 16 }}>
        <summary style={{ cursor: 'pointer', color: '#1a56db' }}>
          Manual Search (if QR damaged)
        </summary>
        <SearchFallback onSelect={(g) => setGuest(g)} />
      </details>
    </div>
  );
}
