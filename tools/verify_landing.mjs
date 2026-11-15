// Run with Node 22+ while Vite is serving on 127.0.0.1:5173.
import { spawn } from 'node:child_process'
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve } from 'node:path'
import assert from 'node:assert/strict'

const chrome = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--remote-debugging-pipe', `--user-data-dir=${mkdtempSync(join(tmpdir(), 'factuflash-preview-'))}`,
], { stdio: ['ignore', 'ignore', 'ignore', 'pipe', 'pipe'], windowsHide: true })
const pending = new Map()
let sequence = 0
let buffer = ''
chrome.stdio[4].on('data', chunk => {
  buffer += chunk.toString()
  let boundary
  while ((boundary = buffer.indexOf('\0')) >= 0) {
    const message = JSON.parse(buffer.slice(0, boundary))
    buffer = buffer.slice(boundary + 1)
    if (message.id && pending.has(message.id)) {
      const { resolve, reject, timeout } = pending.get(message.id)
      clearTimeout(timeout)
      pending.delete(message.id)
      message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result)
    }
  }
})
function call(method, params = {}, sessionId) {
  return new Promise((resolve, reject) => {
    const id = ++sequence
    const timeout = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout: ${method}`)) }, 15000)
    pending.set(id, { resolve, reject, timeout })
    chrome.stdio[3].write(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }) + '\0')
  })
}
try {
  const { targetId } = await call('Target.createTarget', { url: 'about:blank' })
  const { sessionId } = await call('Target.attachToTarget', { targetId, flatten: true })
  const command = (method, params) => call(method, params, sessionId)
  const evaluate = async expression => {
    const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails))
    return result.result.value
  }
  await command('Page.enable')
  const output = resolve('artifacts')
  mkdirSync(output, { recursive: true })
  for (const [name, width, height] of [['desktop', 1440, 1000], ['mobile', 390, 844], ['small', 320, 720]]) {
    await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 600 })
    await command('Page.navigate', { url: 'http://127.0.0.1:5173/landing/index.html' })
    await new Promise(resolve => setTimeout(resolve, 1000))
    await evaluate('document.fonts.ready.then(() => true)')
    const metrics = await evaluate(`({ viewport: innerWidth, width: document.documentElement.scrollWidth, title: document.title, register: document.querySelector('[data-app="register"]').href })`)
    assert.ok(metrics.width <= metrics.viewport, `${name}: horizontal overflow ${JSON.stringify(metrics)}`)
    assert.equal(metrics.register, 'http://127.0.0.1:5173/#/register')
    if (width < 600) {
      await evaluate(`document.querySelector('.menu-toggle').click()`)
      assert.equal(await evaluate(`document.querySelector('.menu-toggle').getAttribute('aria-expanded')`), 'true')
      await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`)
      assert.equal(await evaluate(`document.querySelector('.menu-toggle').getAttribute('aria-expanded')`), 'false')
    }
    await evaluate(`document.querySelector('details summary').click()`)
    assert.equal(await evaluate(`document.querySelector('details').open`), true)
    await evaluate(`document.querySelector('details summary').click()`)
    const screenshot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
    writeFileSync(join(output, `landing-${name}.png`), Buffer.from(screenshot.data, 'base64'))
    console.log(`${name}: no overflow, links and interactions OK (${width}px)`)
  }
  await command('Page.navigate', { url: 'http://127.0.0.1:5173/' })
  await new Promise(resolve => setTimeout(resolve, 1500))
  assert.equal(await evaluate('location.pathname'), '/landing/index.html')
  console.log('Guest root redirects to landing: OK')
} finally {
  await call('Browser.close').catch(() => chrome.kill())
}
