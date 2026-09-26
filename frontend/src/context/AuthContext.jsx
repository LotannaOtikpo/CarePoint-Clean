import { createContext, useContext, useState } from 'react';
import client from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    localStorage.removeItem('hms_user');
    localStorage.removeItem('hms_token');
    const raw = sessionStorage.getItem('hms_user');
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch {
      sessionStorage.removeItem('hms_user');
      sessionStorage.removeItem('hms_token');
      return null;
    }
  });

  const login = async (email, password) => {
    const { data } = await client.post('/auth/login', { email, password });
    sessionStorage.setItem('hms_token', data.token);
    sessionStorage.setItem('hms_user', JSON.stringify(data.user));
    setUser(data.user);
    return data.user;
  };

  const register = async (payload) => {
    const { data } = await client.post('/auth/register', payload);
    sessionStorage.setItem('hms_token', data.token);
    sessionStorage.setItem('hms_user', JSON.stringify(data.user));
    setUser(data.user);
    return data.user;
  };

  const logout = async () => {
    try { await client.post('/auth/logout'); } catch { /* ignore */ }
    sessionStorage.removeItem('hms_token');
    sessionStorage.removeItem('hms_user');
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
