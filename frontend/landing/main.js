// This page is served both from the production root and Vite's /landing/ entry.
const appBase = location.pathname.includes('/landing/')
  ? location.pathname.slice(0, location.pathname.indexOf('/landing/')) + '/'
  : '/app/'
document.querySelectorAll('[data-app]').forEach(link => {
  link.href = `${appBase}#/${link.dataset.app}`
})
const toggle = document.querySelector('.menu-toggle')
const navigation = document.querySelector('.nav-links')
function closeMenu() {
  toggle.setAttribute('aria-expanded', 'false')
  toggle.setAttribute('aria-label', 'Abrir menú')
  navigation.classList.remove('open')
}
toggle.addEventListener('click', () => {
  const open = toggle.getAttribute('aria-expanded') !== 'true'
  toggle.setAttribute('aria-expanded', String(open))
  toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú')
  navigation.classList.toggle('open', open)
})
navigation.addEventListener('click', event => {
  if (event.target.closest('a')) closeMenu()
})
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') { closeMenu(); toggle.focus() }
})
document.getElementById('year').textContent = String(new Date().getFullYear())
