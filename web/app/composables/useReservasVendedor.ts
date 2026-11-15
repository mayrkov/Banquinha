// Reservas do vendedor, compartilhadas entre a aba Reservas e o contador do menu.
const reservas = ref<Reserva[]>([])
const carregou = ref(false)
let timer: ReturnType<typeof setInterval> | undefined
let conhecidas: Set<string> | null = null

export function useReservasVendedor() {
  async function carregar() {
    try {
      reservas.value = await api<Reserva[]>('/vendedor/reservas')
      carregou.value = true
      // Com o painel aberto, vibra quando chega reserva nova (REGRAS: notificações 1).
      const atuais = new Set(reservas.value.filter(r => r.status === 'pendente').map(r => r.codigo))
      if (conhecidas && [...atuais].some(c => !conhecidas!.has(c))) navigator.vibrate?.(300)
      conhecidas = atuais
    }
    catch {}
  }

  function iniciarConsulta() {
    if (timer) return
    carregar()
    timer = setInterval(carregar, 20_000)
  }

  async function acao(codigo: string, nome: 'aceitar' | 'negar' | 'cancelar' | 'confirmar-pagamento' | 'entregar', corpo?: object) {
    await api(`/vendedor/reservas/${codigo}/${nome}`, { method: 'POST', body: corpo })
    await carregar()
    if (nome === 'confirmar-pagamento') await useProdutos().carregar().catch(() => {})
  }

  const pendentes = computed(() => reservas.value.filter(r => r.status === 'pendente'))

  return { reservas, carregou, pendentes, carregar, iniciarConsulta, acao }
}
