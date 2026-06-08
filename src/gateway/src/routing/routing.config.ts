export const VIA_HTTP = 'http' as const;
export const VIA_RABBITMQ = 'rabbitmq' as const;

export type Via = typeof VIA_HTTP | typeof VIA_RABBITMQ;

export interface RouteConfig {
  readonly method: string;
  readonly path: string;
  readonly module: string;
  readonly via: Via;
  readonly public?: boolean;
}

export const routes: RouteConfig[] = [
  { method: 'POST', path: '/auth/register', module: 'auth', via: VIA_HTTP, public: true },
  { method: 'POST', path: '/auth/login', module: 'auth', via: VIA_HTTP, public: true },
  { method: 'POST', path: '/auth/refresh', module: 'auth', via: VIA_HTTP, public: true },
  { method: 'POST', path: '/users', module: 'users', via: VIA_RABBITMQ },
  { method: 'GET', path: '/users/:id', module: 'users', via: VIA_HTTP },
  { method: 'POST', path: '/tenants', module: 'tenants', via: VIA_RABBITMQ },
  { method: 'GET', path: '/tenants/:id', module: 'tenants', via: VIA_HTTP },
];
