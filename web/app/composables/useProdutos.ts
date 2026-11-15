import { get, set } from 'idb-keyval'

export interface Produto {
  id: number
  nome: string
  descricao: string | null
  cor: string | null
  preco: number
  estoque: number
  ativo: boolean
  /** Unidades de reservas aceitas e não pagas (vem só na listagem). */
  reservado?: number
}

const produtos = ref<Produto[]>([])
let carregouCache = false

export function useProdutos() {
  /** Busca no servidor e guarda no aparelho; sem sinal, usa a última lista guardada. */
  async function carregar() {
    if (!carregouCache) {
      carregouCache = true
      produtos.value = (await get<Produto[]>('produtos')) ?? []
    }
    try {
      produtos.value = await api<Produto[]>('/vendedor/produtos')
      await set('produtos', toRaw(produtos.value))
    }
    catch (e) {
      if (e instanceof ErroApi) throw e
    }
  }

  async function salvar(id: number | null, dados: Partial<Produto>) {
    const salvo = id
      ? await api<Produto>(`/vendedor/produtos/${id}`, { method: 'PUT', body: dados })
      : await api<Produto>('/vendedor/produtos', { method: 'POST', body: dados })
    const i = produtos.value.findIndex(p => p.id === salvo.id)
    if (i >= 0) produtos.value[i] = salvo
    else produtos.value.push(salvo)
    await set('produtos', toRaw(produtos.value).map(p => toRaw(p)))
    return salvo
  }

  return { produtos, carregar, salvar }
}
