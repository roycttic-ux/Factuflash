import axios from 'axios'

const baseURL = '/api/v1/'

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
  async (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      try {
        const { useAuthStore } = await import('@/stores/auth')
        const { default: router } = await import('@/router')
        const auth = useAuthStore()
        auth.token = null
        auth.user = null
        router.replace('/login')
      } catch (e) {
        window.location.replace('#/login')
      }
    } else if (err.response?.status === 402) {
      try {
        const { default: router } = await import('@/router')
        router.replace('/suscripcion')
      } catch (e) {
        window.location.replace('#/suscripcion')
      }
    }
    return Promise.reject(err)
  }
)

export default api
