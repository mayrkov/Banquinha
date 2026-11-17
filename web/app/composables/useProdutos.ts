import { get, set } from 'idb-keyval'

export interface Produto {
  id: number
  nome: string
  descricao: string | null
  cor: string | null
  /** Caminho relativo (/storage/...) ou null. */
  foto_url: string | null
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
    return guardar(id
      ? await api<Produto>(`/vendedor/produtos/${id}`, { method: 'PUT', body: dados })
      : await api<Produto>('/vendedor/produtos', { method: 'POST', body: dados }))
  }

  async function enviarFoto(id: number, arquivo: File) {
    const corpo = new FormData()
    corpo.append('foto', await reduzirFoto(arquivo), 'foto.jpg')
    return guardar(await api<Produto>(`/vendedor/produtos/${id}/foto`, { method: 'POST', body: corpo }))
  }

  async function removerFoto(id: number) {
    return guardar(await api<Produto>(`/vendedor/produtos/${id}/foto`, { method: 'DELETE' }))
  }

  /** Atualiza a lista e o cache do aparelho com o produto salvo. */
  async function guardar(salvo: Produto) {
    const i = produtos.value.findIndex(p => p.id === salvo.id)
    if (i >= 0) produtos.value[i] = salvo
    else produtos.value.push(salvo)
    await set('produtos', toRaw(produtos.value).map(p => toRaw(p)))
    return salvo
  }

  return { produtos, carregar, salvar, enviarFoto, removerFoto }
}
