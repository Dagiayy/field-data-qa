import React from 'react';
import type { Role } from '../types';

interface RequireRoleProps {
  roles?: Role[];
  children: React.ReactNode;
}

export const RequireRole: React.FC<RequireRoleProps> = ({ children }) => {
  return <>{children}</>;
};
