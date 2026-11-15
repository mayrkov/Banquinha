export interface ItemReserva { produto_id: number, nome: string, cor: string | null, quantidade: number, preco_unitario: number }
export interface Parada { id: number, local: string, inicio: string, fim: string | null }

export interface Reserva {
  codigo: string
  status: 'pendente' | 'aceito' | 'negado' | 'expirado' | 'cancelado_cliente' | 'cancelado_vendedor' | 'pago' | 'entregue'
  tipo: 'entrega' | 'retirada'
  itens: ItemReserva[]
  parada: Parada | null
  endereco_entrega: string | null
  referencia_entrega: string | null
  taxa_entrega: number
  valor_total: number
  responder_ate: string
  cliente_informou_pagamento_em: string | null
  criado_em: string
  encerrada_em: string | null
  // visão do cliente
  vendedor?: { nome: string, slug: string, telefone: string | null }
  codigo_entrega?: string | null
  pix?: { chave: string | null, nome: string | null, cidade: string | null } | null
  // visão do vendedor
  cliente?: { nome: string, telefone: string }
}

export const ROTULO_STATUS: Record<Reserva['status'], string> = {
  pendente: 'Aguardando vendedor',
  aceito: 'Aceito · pague com Pix',
  negado: 'Negado',
  expirado: 'Expirado',
  cancelado_cliente: 'Cancelado por você',
  cancelado_vendedor: 'Cancelado pelo vendedor',
  pago: 'Pago · aguardando entrega',
  entregue: 'Entregue',
}

export const resumoItens = (r: { itens: ItemReserva[] }) => r.itens.map(i => `${i.quantidade} ${i.nome}`).join(', ')

const fmtData = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' })
const fmtHora = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' })

export function quandoParada(p: { inicio: string, fim: string | null }): string {
  const ini = new Date(p.inicio)
  const hoje = new Date().toDateString() === ini.toDateString()
  const base = hoje ? `hoje, ${fmtHora.format(ini)}` : fmtData.format(ini)
  return p.fim ? `${base} até ${fmtHora.format(new Date(p.fim))}` : base
}

export const minutosAte = (iso: string) => Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 60000))
