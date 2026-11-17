export interface Vendedor {
  id: number
  nome: string
  slug: string | null
  email: string
  em_atividade: boolean
  taxa_entrega: number
  prazo_resposta_min: number
  telefone: string | null
  descricao: string | null
  /** Foto de perfil (/storage/...), mostrada no lugar do ponto no mapa. */
  foto_url: string | null
  chave_pix: string | null
  nome_recebedor_pix: string | null
  cidade_recebedor_pix: string | null
  provedor_cartao: 'infinitepay' | 'mercadopago_point' | 'outro' | null
  infinitepay_handle: string | null
}

const CHAVE = 'banquinha:vendedor'
// Painel roda só no navegador (D38): estado de módulo é seguro aqui.
const vendedor = ref<Vendedor | null>(null)
let lidoDoCache = false

function salvar(v: Vendedor | null) {
  vendedor.value = v
  try {
    if (v) localStorage.setItem(CHAVE, JSON.stringify(v))
    else localStorage.removeItem(CHAVE)
  }
  catch {}
}

export function useVendedor() {
  if (!lidoDoCache && import.meta.client) {
    lidoDoCache = true
    try {
      vendedor.value = JSON.parse(localStorage.getItem(CHAVE) ?? 'null')
    }
    catch {}
  }


  /** Atualiza do servidor. Sem sinal, mantém o perfil guardado (painel abre offline). */
  async function atualizar() {
    try {
      salvar(await api<Vendedor>('/vendedor/eu'))
    }
    catch (e) {
      if (e instanceof ErroApi && e.status === 401) salvar(null)
    }
  }

  async function salvarPerfil(dados: Partial<Vendedor>) {
    salvar(await api<Vendedor>('/vendedor/eu', { method: 'PUT', body: dados }))
  }

  async function enviarFoto(arquivo: File) {
    const corpo = new FormData()
    corpo.append('foto', await reduzirFoto(arquivo), 'foto.jpg')
    salvar(await api<Vendedor>('/vendedor/eu/foto', { method: 'POST', body: corpo }))
  }

  async function removerFoto() {
    salvar(await api<Vendedor>('/vendedor/eu/foto', { method: 'DELETE' }))
  }

  async function turno(acao: 'iniciar' | 'encerrar') {
    salvar(await api<Vendedor>(`/vendedor/turno/${acao}`, { method: 'POST' }))
  }

  async function sair() {
    try {
      await api('/vendedor/logout', { method: 'POST' })
    }
    finally {
      salvar(null)
    }
  }

  return { vendedor: readonly(vendedor), definir: salvar, atualizar, salvarPerfil, enviarFoto, removerFoto, turno, sair, esquecer: () => salvar(null) }
}
