import { useAuth } from '../hooks/useAuth';

export default function Scanner() {
  const { user, logout } = useAuth();

  return (
    <div style={{ maxWidth: 480, margin: '0 auto', padding: 16 }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <h1>Event Scanner</h1>
        <button onClick={logout} style={{ padding: '4px 12px' }}>Logout</button>
      </header>
      <p>Welcome, {user?.name}. Scanner UI will be added in Task 13.</p>
    </div>
  );
}
