<script setup lang="ts">
definePageMeta({ middleware: 'cliente' })

const { sair } = useCliente()
const reservas = ref<Reserva[] | null>(null)
const erro = ref('')
const ABERTOS = ['pendente', 'aceito', 'pago']

const abertos = computed(() => (reservas.value ?? []).filter(r => ABERTOS.includes(r.status)))
const encerrados = computed(() => (reservas.value ?? []).filter(r => !ABERTOS.includes(r.status)))

async function carregar() {
  try {
    reservas.value = await api<Reserva[]>('/cliente/reservas')
    erro.value = ''
  }
  catch (e) {
    if (e instanceof ErroApi && e.status === 401) {
      useCliente().esquecer()
      return navigateTo({ path: '/entrar', query: { volta: '/pedidos' } })
    }
    erro.value = 'Sem conexão. Tentando de novo…'
  }
}

async function cancelar(r: Reserva) {
  if (!confirm('Cancelar esta reserva?')) return
  try {
    await api(`/cliente/reservas/${r.codigo}/cancelar`, { method: 'POST' })
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão.'
  }
  await carregar()
}

async function deslogar() {
  await sair().catch(() => {})
  await navigateTo('/')
}

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  carregar()
  timer = setInterval(carregar, 20_000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <main class="pagina">
    <header class="topo-cliente">
      <NuxtLink to="/" class="icone-btn" aria-label="Voltar ao início"><IconeVoltar /></NuxtLink>
      <span class="marca">Banquinha</span>
      <span class="icone-btn" />
    </header>
    <div class="cab">
      <h1 class="titulo-grande">Meus pedidos</h1>
      <span class="subtitulo">{{ abertos.length }} em andamento</span>
    </div>
    <p v-if="erro" class="erro secao" role="alert">{{ erro }}</p>

    <div class="lista">
      <article
        v-for="r in abertos"
        :key="r.codigo"
        class="pedido"
        :class="{ destaque: r.status === 'aceito' && !r.cliente_informou_pagamento_em }"
      >
        <div class="linha-topo">
          <span class="selo" :class="r.status">{{ ROTULO_STATUS[r.status] }}</span>
          <span class="dica">{{ r.vendedor?.nome }}</span>
        </div>
        <div class="amostras">
          <template v-for="i in r.itens" :key="i.produto_id">
            <span v-for="n in Math.min(i.quantidade, 6)" :key="n" class="amostra" :style="{ background: corDoProduto({ id: i.produto_id, cor: i.cor }) }" />
          </template>
        </div>
        <span class="itens">{{ resumoItens(r) }}</span>
        <span class="info">
          {{ r.tipo === 'entrega' ? 'Entrega' : `Retirada · ${r.parada?.local}` }} · {{ formatarBRL(r.valor_total) }}
          <template v-if="r.status === 'pendente'"> · resposta em até {{ minutosAte(r.responder_ate) }} min</template>
        </span>
        <NuxtLink :to="`/pedidos/${r.codigo}`" class="acao">
          {{ r.status === 'aceito' ? 'Ver Pix →' : r.status === 'pago' ? 'Ver código de entrega →' : 'Ver pedido →' }}
        </NuxtLink>
        <button v-if="r.status === 'pendente'" class="btn-cancelar" @click="cancelar(r)">Cancelar reserva</button>
      </article>

      <p v-if="reservas && !reservas.length" class="dica">Você ainda não fez nenhuma reserva.</p>

      <NuxtLink v-for="r in encerrados" :key="r.codigo" :to="`/pedidos/${r.codigo}`" class="pedido encerrado">
        <div class="linha-topo">
          <span class="selo-simples">{{ ROTULO_STATUS[r.status] }}</span>
          <span class="dica">{{ new Date(r.criado_em).toLocaleDateString('pt-BR') }}</span>
        </div>
        <span class="info">{{ resumoItens(r) }} · {{ formatarBRL(r.valor_total) }}</span>
      </NuxtLink>
    </div>

    <div class="secao">
      <button class="btn btn-contorno" @click="deslogar">Sair da conta</button>
    </div>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; max-width: 640px; margin: 0 auto; padding-bottom: 32px; }
.cab { padding: 8px 16px 0; display: flex; flex-direction: column; gap: 4px; }
.secao { padding: 16px; margin: 0; }
.lista { padding: 20px 16px; display: flex; flex-direction: column; gap: 12px; }
.pedido {
  background: var(--card); border-radius: var(--raio-card); padding: 14px; border: 2px solid transparent;
  display: flex; flex-direction: column; gap: 10px; color: inherit; text-decoration: none;
}
.pedido.destaque { border-color: var(--destaque-texto); }
.pedido.encerrado { background: none; border: 1px solid var(--borda); gap: 6px; }
.linha-topo { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.selo { font-size: 12px; font-weight: 900; letter-spacing: 1.5px; background: var(--borda); border-radius: 999px; padding: 5px 10px; text-transform: uppercase; }
.selo.aceito { background: var(--destaque); }
.selo.pago { background: var(--texto); color: var(--texto-inverso); }
.selo-simples { font-size: 12px; font-weight: 900; letter-spacing: 1.5px; color: var(--texto-3); text-transform: uppercase; }
.amostras { display: flex; gap: 6px; flex-wrap: wrap; }
.amostra { width: 28px; height: 28px; border-radius: 8px; }
.itens { font-size: 17px; font-weight: 900; text-transform: uppercase; }
.info { font-weight: 300; font-size: 15px; }
.encerrado .info { color: var(--texto-2); }
.btn-cancelar { min-height: 44px; background: none; border: 1px solid var(--apagado); font-weight: 700; letter-spacing: 1px; font-size: 13px; text-transform: uppercase; }
.acao { min-height: 44px; display: flex; align-items: center; text-decoration: none; color: var(--destaque-texto); font-size: 14px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
</style>
