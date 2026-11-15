import { get, set } from 'idb-keyval'

export interface VendaLocal {
  id_local: string
  forma_pagamento: 'pix' | 'dinheiro' | 'cartao'
  metodo_cartao?: 'credito' | 'debito'
  pago_em: string
  itens: { produto_id: number, quantidade: number, preco_unitario: number }[]
  /** Recusada pela API (dados inválidos). Fica guardada para não perder a venda. */
  erro?: string
}

// Fila de vendas no IndexedDB. Enviada ao abrir o painel e nos eventos online/visibilitychange (D40).
const fila = ref<VendaLocal[]>([])
const enviando = ref(false)
let carregada: Promise<void> | null = null

async function gravar() {
  await set('fila-vendas', JSON.parse(JSON.stringify(fila.value)))
}

function carregar() {
  carregada ??= get<VendaLocal[]>('fila-vendas').then((v) => { fila.value = v ?? [] })
  return carregada
}

export function useFilaVendas() {
  async function adicionar(venda: VendaLocal) {
    await carregar()
    fila.value.push(venda)
    await gravar()
    enviar()
  }

  async function enviar() {
    await carregar()
    if (enviando.value || !navigator.onLine) return
    enviando.value = true
    let enviouAlguma = false
    try {
      for (const venda of [...fila.value]) {
        if (venda.erro) continue
        try {
          const { erro: _, ...corpo } = venda
          await api('/vendedor/vendas', { method: 'POST', body: corpo })
          fila.value = fila.value.filter(v => v.id_local !== venda.id_local)
          enviouAlguma = true
        }
        catch (e) {
          if (e instanceof ErroApi && e.status === 422) {
            venda.erro = e.mensagem
            continue
          }
          break // sem sinal, 401 ou erro no servidor: tenta de novo depois, na mesma ordem
        }
        finally {
          await gravar()
        }
      }
    }
    finally {
      enviando.value = false
    }
    if (enviouAlguma) await useProdutos().carregar().catch(() => {})
  }

  /** Unidades ainda não enviadas por produto, para o estoque mostrado no PDV já descontar. */
  const pendentesPorProduto = computed(() => {
    const m = new Map<number, number>()
    for (const v of fila.value) {
      if (v.erro) continue
      for (const i of v.itens) m.set(i.produto_id, (m.get(i.produto_id) ?? 0) + i.quantidade)
    }
    return m
  })

  return { fila: readonly(fila), enviando: readonly(enviando), adicionar, enviar, carregar, pendentesPorProduto }
}
