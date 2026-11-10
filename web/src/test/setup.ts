import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';
import { resetDb } from '@/mock/db';

afterEach(() => {
  if (typeof localStorage === 'undefined') return;   // node-environment tests
  cleanup(); localStorage.clear(); resetDb();
});
