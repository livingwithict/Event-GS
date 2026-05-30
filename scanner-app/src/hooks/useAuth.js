import { useState, useCallback } from 'react';
import api from '../utils/api';

export function useAuth() {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('user');
    return saved ? JSON.parse(saved) : null;
  });

  const login = useCallback(async (email, password) => {
    const { data } = await api.post('/login', { email, password });
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    try { await api.post('/logout'); } catch {}
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    setUser(null);
  }, []);

  return { user, login, logout, isAuthenticated: !!user };
}
