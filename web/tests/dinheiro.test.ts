import { test } from 'node:test'
import assert from 'node:assert/strict'
import { centavosDeTexto, textoDeCentavos } from '../app/utils/dinheiro.ts'

test('converte texto de preço em centavos', () => {
  assert.equal(centavosDeTexto('12,50'), 1250)
  assert.equal(centavosDeTexto('R$ 15'), 1500)
  assert.equal(centavosDeTexto('0,1'), 10)
  assert.equal(centavosDeTexto('1.234,56'), 123456)
  assert.equal(centavosDeTexto('abc'), null)
  assert.equal(centavosDeTexto('1,234'), null)
  assert.equal(textoDeCentavos(1250), '12,50')
})
