// BR Code do Pix estático (padrão EMV MPM do Banco Central), gerado no celular para o PDV offline (D34).
// Mesma regra existirá no backend (reservas/e-mails); um teste deve comparar as duas saídas.

export interface DadosPix {
  chave: string
  nome: string
  cidade: string
  valorCentavos: number
  txid?: string
}

function campo(id: string, valor: string): string {
  return id + String(valor.length).padStart(2, '0') + valor
}

// Bancos rejeitam acentos/caracteres fora do ASCII básico em nome e cidade.
function normalizar(texto: string, max: number): string {
  return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9 ]/g, '').trim().slice(0, max)
}

/** CRC16-CCITT-FALSE (poly 0x1021, init 0xFFFF), em 4 dígitos hexadecimais maiúsculos. */
export function crc16(texto: string): string {
  let crc = 0xFFFF
  for (let i = 0; i < texto.length; i++) {
    crc ^= texto.charCodeAt(i) << 8
    for (let b = 0; b < 8; b++) {
      crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xFFFF : (crc << 1) & 0xFFFF
    }
  }
  return crc.toString(16).toUpperCase().padStart(4, '0')
}

export function gerarBRCode({ chave, nome, cidade, valorCentavos, txid = '***' }: DadosPix): string {
  const conta = campo('00', 'br.gov.bcb.pix') + campo('01', chave.trim())
  const corpo
    = campo('00', '01')
    + campo('26', conta)
    + campo('52', '0000')
    + campo('53', '986')
    + (valorCentavos > 0 ? campo('54', (valorCentavos / 100).toFixed(2)) : '')
    + campo('58', 'BR')
    + campo('59', normalizar(nome, 25))
    + campo('60', normalizar(cidade, 15))
    + campo('62', campo('05', txid))
    + '6304'
  return corpo + crc16(corpo)
}
