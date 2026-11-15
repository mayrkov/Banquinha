export interface Cliente { id: number, nome: string, email: string, telefone: string }

const CHAVE = 'banquinha:cliente'
const cliente = ref<Cliente | null>(null)
let lido = false

function salvar(c: Cliente | null) {
  cliente.value = c
  try {
    if (c) localStorage.setItem(CHAVE, JSON.stringify(c))
    else localStorage.removeItem(CHAVE)
  }
  catch {}
}

export function useCliente() {
  if (!lido && import.meta.client) {
    lido = true
    try {
      cliente.value = JSON.parse(localStorage.getItem(CHAVE) ?? 'null')
    }
    catch {}
  }


  async function cadastrar(dados: { nome: string, email: string, telefone: string, senha: string }) {
    await csrf()
    salvar(await api<Cliente>('/cliente/cadastro', { method: 'POST', body: dados }))
  }

  /** Confere a sessão; 401 = desloga. */
  async function atualizar() {
    try {
      salvar(await api<Cliente>('/cliente/eu'))
    }
    catch (e) {
      if (e instanceof ErroApi && e.status === 401) salvar(null)
    }
  }

  async function sair() {
    try {
      await api('/cliente/logout', { method: 'POST' })
    }
    finally {
      salvar(null)
    }
  }

  return { cliente: readonly(cliente), definir: salvar, cadastrar, atualizar, sair, esquecer: () => salvar(null) }
}

/** Caminho interno seguro para voltar após login (evita redirecionar para outro site). */
export function caminhoVolta(v: unknown, padrao = '/pedidos'): string {
  return typeof v === 'string' && v.startsWith('/') && !v.startsWith('//') ? v : padrao
}
