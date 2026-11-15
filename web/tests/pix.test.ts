import { test } from 'node:test'
import assert from 'node:assert/strict'
import { crc16, gerarBRCode } from '../app/utils/pix.ts'

test('crc16 bate com o valor de referência do CCITT-FALSE', () => {
  assert.equal(crc16('123456789'), '29B1')
})

test('gera BR Code com valor, nome e cidade normalizados', () => {
  const br = gerarBRCode({ chave: 'pix@exemplo.com', nome: 'José da Silva', cidade: 'São Paulo', valorCentavos: 3000 })
  assert.ok(br.startsWith('000201'))
  assert.ok(br.includes('0014br.gov.bcb.pix0115pix@exemplo.com'))
  assert.ok(br.includes('540530.00'))
  assert.ok(br.includes('5913Jose da Silva'))
  assert.ok(br.includes('6009Sao Paulo'))
  assert.ok(br.includes('62070503***'))
  assert.equal(br.slice(-4), crc16(br.slice(0, -4)))
})

test('sem valor omite o campo 54', () => {
  assert.ok(!gerarBRCode({ chave: 'x', nome: 'A', cidade: 'B', valorCentavos: 0 }).includes('5405'))
})
