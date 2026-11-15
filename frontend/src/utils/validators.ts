/**
 * Validador oficial de CUIT/CUIL con algoritmo Módulo 11 (ARCA / AFIP)
 */
export function isValidCuit(cuit: string | null | undefined): boolean {
  if (!cuit) return false
  const clean = cuit.replace(/\D/g, '')
  if (clean.length !== 11) return false
  if (/^(\d)\1{10}$/.test(clean)) return false

  const prefix = clean.substring(0, 2)
  const validPrefixes = ['20', '23', '24', '27', '30', '33', '34', '50', '51', '55']
  if (!validPrefixes.includes(prefix)) return false

  const multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]
  let sum = 0
  for (let i = 0; i < 10; i++) {
    sum += parseInt(clean[i], 10) * multipliers[i]
  }

  const remainder = 11 - (sum % 11)
  const expectedCheckDigit = remainder === 11 ? 0 : remainder === 10 ? 9 : remainder
  return parseInt(clean[10], 10) === expectedCheckDigit
}

/**
 * Formatea un CUIT como XX-XXXXXXXX-X
 */
export function formatCuit(cuit: string | null | undefined): string {
  if (!cuit) return ''
  const clean = cuit.replace(/\D/g, '')
  if (clean.length !== 11) return cuit
  return `${clean.substring(0, 2)}-${clean.substring(2, 10)}-${clean.substring(10, 11)}`
}

/**
 * Tope legal para ventas a Consumidor Final anónimo sin identificar (ARCA / AFIP RG 1415)
 */
export const ANONYMOUS_CONSUMER_FINAL_LIMIT = 344488.0
