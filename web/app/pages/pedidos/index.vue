<script setup lang="ts">
definePageMeta({ middleware: 'cliente' })

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

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  carregar()
  timer = setInterval(carregar, 20_000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <main class="pagina-bloco">
    <TopoBloco voltar="/perfil" rotulo-voltar="Voltar ao perfil" marca="Banquinha" titulo="Meus pedidos">
      <span class="sub">{{ abertos.length }} em andamento</span>
    </TopoBloco>
    <p v-if="erro" class="erro bloco" role="alert">{{ erro }}</p>

    <article
      v-for="r in abertos"
      :key="r.codigo"
      class="bloco pedido"
      :class="{ 'bloco-preto': r.status !== 'pendente' }"
    >
      <div class="linha-topo">
        <span class="pilula" :class="{ cinza: r.status === 'pendente' }">{{ ROTULO_STATUS[r.status] }}</span>
        <span class="vendedor">{{ r.vendedor?.nome }}</span>
      </div>
      <div class="itens">
        <span class="amostras" aria-hidden="true">
          <template v-for="i in r.itens" :key="i.produto_id">
            <span v-for="n in Math.min(i.quantidade, 6)" :key="n" class="amostra" :style="{ background: corDoProduto({ id: i.produto_id, cor: i.cor }) }" />
          </template>
        </span>
        <span class="itens-txt">{{ resumoItens(r) }}</span>
      </div>
      <span class="info">
        {{ r.tipo === 'entrega' ? 'Entrega' : `Retirada · ${r.parada?.local}` }} · {{ formatarBRL(r.valor_total) }}
        <template v-if="r.status === 'pendente'"> · resposta em até {{ minutosAte(r.responder_ate) }} min</template>
      </span>
      <NuxtLink :to="`/pedidos/${r.codigo}`" class="acao">
        {{ r.status === 'aceito' ? 'Ver Pix' : r.status === 'pago' ? 'Ver código de entrega' : 'Ver pedido' }}
        <span class="seta-acao"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </NuxtLink>
      <button v-if="r.status === 'pendente'" class="btn btn-cinza-claro" @click="cancelar(r)">Cancelar reserva</button>
    </article>

    <p v-if="reservas && !reservas.length" class="bloco dica">Você ainda não fez nenhuma reserva.</p>

    <NuxtLink v-for="r in encerrados" :key="r.codigo" :to="`/pedidos/${r.codigo}`" class="bloco bloco-contorno encerrado">
      <span class="enc-txt">
        <span class="enc-status">{{ ROTULO_STATUS[r.status] }}</span>
        <span class="dica">{{ new Date(r.criado_em).toLocaleDateString('pt-BR') }} · {{ resumoItens(r) }} · {{ formatarBRL(r.valor_total) }}</span>
      </span>
      <span class="seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
    </NuxtLink>

  </main>
</template>

<style scoped>
.sub { font-size: 16px; }
.pedido { padding: 18px; gap: 12px; }
.linha-topo { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.linha-topo .pilula { align-self: auto; }
.vendedor { font-size: 13px; opacity: .7; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.itens { display: flex; align-items: center; gap: 10px; }
.amostras { display: flex; flex-shrink: 0; }
.amostra { width: 28px; height: 28px; border-radius: 50%; border: 3px solid var(--card); }
.bloco-preto .amostra { border-color: var(--preto); }
.amostra + .amostra { margin-left: -10px; }
.itens-txt { font-size: 16px; font-weight: 600; }
.info { font-size: 14px; opacity: .8; }
.acao { min-height: 44px; display: flex; align-items: center; justify-content: space-between; color: inherit; text-decoration: none; font-weight: 600; font-size: 15px; }
.seta-acao { width: 40px; height: 40px; border-radius: 50%; background: #FFFFFF; color: #111111; display: flex; align-items: center; justify-content: center; }
.pedido:not(.bloco-preto) .seta-acao { background: var(--preto); color: var(--sobre-preto); }
.btn-cinza-claro { min-height: 48px; background: var(--fundo); color: var(--texto); font-size: 14px; }
.encerrado { flex-direction: row; align-items: center; justify-content: space-between; padding: 14px 14px 14px 18px; text-decoration: none; }
.enc-txt { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.enc-status { font-size: 14px; font-weight: 600; }
.encerrado .seta { width: 36px; height: 36px; }
</style>
