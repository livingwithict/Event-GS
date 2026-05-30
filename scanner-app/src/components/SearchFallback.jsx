import { useState } from 'react';
import api from '../utils/api';

export default function SearchFallback({ onSelect }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);

  const search = async () => {
    if (query.length < 2) return;
    setSearching(true);
    try {
      const { data } = await api.get('/guest/search', { params: { q: query } });
      setResults(data.data);
    } catch {
      setResults([]);
    } finally {
      setSearching(false);
    }
  };

  return (
    <div style={{ marginTop: 16 }}>
      <div style={{ display: 'flex', gap: 8 }}>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && search()}
          placeholder="Search by name, email, or phone"
          style={{ flex: 1, padding: 10, fontSize: 16, boxSizing: 'border-box' }}
        />
        <button onClick={search} disabled={searching} style={{ padding: '10px 16px' }}>
          {searching ? '...' : 'Search'}
        </button>
      </div>
      {results.map((r) => (
        <div
          key={r.id}
          onClick={() => onSelect(r)}
          style={{ padding: 12, borderBottom: '1px solid #e5e7eb', cursor: 'pointer' }}
        >
          <strong>{r.name}</strong> — {r.organization || 'N/A'}
        </div>
      ))}
    </div>
  );
}
