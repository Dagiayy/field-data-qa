import React, { createContext, useContext, useState, useEffect } from 'react';
import type { User, Role } from '../types';
import { MOCK_USERS } from '../api/mockData';

interface AuthContextType {
  user: User;
  token: string;
  isAuthenticated: boolean;
  login: (email: string, pass: string) => Promise<void>;
  logout: () => void;
  switchRole: (role: Role) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User>(() => {
    const saved = localStorage.getItem('metrix_qa_user');
    return saved ? JSON.parse(saved) : MOCK_USERS.admin;
  });

  const [token, setToken] = useState<string>(() => {
    return localStorage.getItem('metrix_qa_token') || 'mock-jwt-token-admin';
  });

  useEffect(() => {
    localStorage.setItem('metrix_qa_user', JSON.stringify(user));
  }, [user]);

  useEffect(() => {
    localStorage.setItem('metrix_qa_token', token);
  }, [token]);

  const login = async () => {
    // No-op since login is disabled/bypassed
  };

  const logout = () => {
    // Reset to default admin user instead of unauthenticating
    setUser(MOCK_USERS.admin);
    setToken('mock-jwt-token-admin');
  };

  const switchRole = (role: Role) => {
    const matched = Object.values(MOCK_USERS).find((u) => u.role === role) || {
      id: `usr-${role}`,
      name: `${role.toUpperCase().replace('_', ' ')} User`,
      email: `${role}@metrix.qa`,
      role,
    };
    setUser(matched);
    setToken(`mock-token-${role}`);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isAuthenticated: true,
        login,
        logout,
        switchRole,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
