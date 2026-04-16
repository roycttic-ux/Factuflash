import api from './client'

export const authApi = {
  login: (email: string, password: string) =>
    api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  register: (data: Record<string, string>) => api.post('/auth/register', data),
}

export const dashboardApi = {
  stats: () => api.get('/dashboard/stats'),
}

export const invoicesApi = {
  list: () => api.get('/invoices'),
  get: (id: number) => api.get(`/invoices/${id}`),
  create: (data: object) => api.post('/invoices', data),
  delete: (id: number) => api.delete(`/invoices/${id}`),
}

export const productsApi = {
  list: () => api.get('/products'),
  get: (id: number) => api.get(`/products/${id}`),
  create: (data: object | FormData) => api.post('/products', data),
  update: (id: number, data: object | FormData) => {
    if (data instanceof FormData) {
      return api.post(`/products/${id}`, data)
    }
    return api.put(`/products/${id}`, data)
  },
  delete: (id: number) => api.delete(`/products/${id}`),
  lowStock: () => api.get('/products/low-stock'),
}

export const customersApi = {
  list: () => api.get('/customers'),
  get: (id: number) => api.get(`/customers/${id}`),
  create: (data: object) => api.post('/customers', data),
  update: (id: number, data: object) => api.put(`/customers/${id}`, data),
  delete: (id: number) => api.delete(`/customers/${id}`),
}

export const settingsApi = {
  get: () => api.get('/settings'),
  update: (data: object) => api.put('/settings', data),
  uploadLogo: (formData: FormData) => api.post('/settings/logo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
}

export const arcaApi = {
  generateCsr: (cuit: string) => api.post('/arca/csr', { cuit }),
  uploadCert: (formData: FormData) => api.post('/arca/cert', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  reset: () => api.post('/arca/reset'),
}

export const catalogApi = {
  get: (userId: number) => api.get(`/public/catalog/${userId}`),
}

export const subscriptionApi = {
  plans: () => api.get('/subscription/plans'),
  checkout: (plan: string) => api.post('/subscription/checkout', { plan }),
  status: () => api.get('/subscription/status'),
  cancel: () => api.post('/subscription/cancel'),
}

export const mpApi = {
  createPreference: (data: object) => api.post('/mp/preference', data),
  createSubscription: (plan: string) => api.post('/mp/subscription-preference', { plan }),
}

export const cashierApi = {
  list: () => api.get('/cashiers'),
  create: (data: object) => api.post('/cashiers', data),
  delete: (id: number) => api.delete(`/cashiers/${id}`),
}
