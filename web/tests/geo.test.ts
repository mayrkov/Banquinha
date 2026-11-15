import { test } from 'node:test'
import assert from 'node:assert/strict'
import { distanciaM, formatarDistancia } from '../app/utils/geo.ts'

test('distância entre Praça da Sé e Av. Paulista (~2,9 km)', () => {
  const d = distanciaM({ latitude: -23.5505, longitude: -46.6333 }, { latitude: -23.5614, longitude: -46.6559 })
  assert.ok(d > 2500 && d < 2700, String(d))
  assert.equal(formatarDistancia(347), '350 m')
  assert.equal(formatarDistancia(2593), '2,6 km')
})
