import axios from 'axios'

const isProduction = process.env.NODE_ENV === 'production'
const baseURL = isProduction 
  ? '/api/v1' 
  : 'http://localhost/sistemafacturacion/api/public/api/v1'

const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '#/login'
    }
    return Promise.reject(err)
  }
)

export default api
