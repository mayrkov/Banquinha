<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const { reservas, carregou, pendentes, carregar, acao } = useReservasVendedor()
const erro = ref('')
const codigos = reactive<Record<string, string>>({})
const ocupado = ref('')
const agora = ref(Date.now())

const pagou = computed(() => reservas.value.filter(r => r.status === 'aceito' && r.cliente_informou_pagamento_em))
const aguardando = computed(() => reservas.value.filter(r => r.status === 'aceito' && !r.cliente_informou_pagamento_em))
const entregar = computed(() => reservas.value.filter(r => r.status === 'pago'))
const encerradas = computed(() => reservas.value.filter(r => !['pendente', 'aceito', 'pago'].includes(r.status)).reverse())

const minutos = (iso: string) => Math.max(0, Math.ceil((new Date(iso).getTime() - agora.value) / 60000))
const ondeEntregar = (r: Reserva) => r.tipo === 'entrega'
  ? `Entrega · ${r.endereco_entrega ?? '(endereço apagado)'}${r.referencia_entrega ? ` (${r.referencia_entrega})` : ''}`
  : `Retirada · ${r.parada?.local}${r.parada ? `, ${quandoParada(r.parada)}` : ''}`
const mapa = (r: Reserva) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(r.endereco_entrega ?? '')}`

async function fazer(r: Reserva, nome: Parameters<typeof acao>[1], pergunta?: string) {
  if (pergunta && !confirm(pergunta)) return
  erro.value = ''
  ocupado.value = r.codigo
  try {
    await acao(r.codigo, nome, nome === 'entregar' ? { codigo_entrega: codigos[r.codigo] } : undefined)
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Reservas precisam de internet.'
    await carregar()
  }
  finally {
    ocupado.value = ''
  }
}

let relogio: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  carregar()
  relogio = setInterval(() => { agora.value = Date.now() }, 30_000)
})
onBeforeUnmount(() => clearInterval(relogio))
</script>

<template>
  <section class="tela">
    <header class="bloco cab">
      <h1 class="titulo-pagina">Reservas</h1>
      <button class="btn btn-cinza-claro" @click="carregar">Atualizar</button>
    </header>
    <p v-if="erro" class="erro bloco" role="alert">{{ erro }}</p>
    <p v-if="carregou && !reservas.length" class="bloco dica">Nenhuma reserva por enquanto.</p>

    <template v-if="pendentes.length">
      <article v-for="r in pendentes" :key="r.codigo" class="card novo">
        <div class="topo-card"><span class="rot">Nova reserva</span><span class="pilula preta">responda em {{ minutos(r.responder_ate) }} min</span></div>
        <div class="topo-card"><strong class="nome">{{ r.cliente?.nome }}</strong><strong class="valor">{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="info">{{ ondeEntregar(r) }}</span>
        <div class="duas">
          <button class="btn btn-branco" :disabled="ocupado === r.codigo" @click="fazer(r, 'negar', 'Negar esta reserva?')">Negar</button>
          <button class="btn btn-escuro" :disabled="ocupado === r.codigo" @click="fazer(r, 'aceitar')">Aceitar</button>
        </div>
      </article>
    </template>

    <template v-if="pagou.length">
      <article v-for="r in pagou" :key="r.codigo" class="card bloco-preto">
        <span class="rot">Cliente disse "já paguei"</span>
        <div class="topo-card"><strong class="nome">{{ r.cliente?.nome }}</strong><strong class="valor destaque">{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="info">{{ ondeEntregar(r) }}</span>
        <p class="info">Confira no app do banco antes de confirmar.</p>
        <button class="btn btn-destaque" :disabled="ocupado === r.codigo" @click="fazer(r, 'confirmar-pagamento', `Confirmar que recebeu ${formatarBRL(r.valor_total)} no Pix?`)">Confirmar pagamento</button>
      </article>
    </template>

    <template v-if="aguardando.length">
      <article v-for="r in aguardando" :key="r.codigo" class="card">
        <span class="pilula cinza">Aceita · aguardando Pix</span>
        <div class="topo-card"><strong class="nome">{{ r.cliente?.nome }}</strong><strong class="valor">{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="info">{{ ondeEntregar(r) }} · <a :href="`tel:${r.cliente?.telefone}`">{{ r.cliente?.telefone }}</a></span>
        <div class="duas">
          <button class="btn btn-cinza-claro" :disabled="ocupado === r.codigo" @click="fazer(r, 'cancelar', 'Cancelar e liberar as unidades?')">Cancelar</button>
          <button class="btn btn-escuro" :disabled="ocupado === r.codigo" @click="fazer(r, 'confirmar-pagamento', `Confirmar que recebeu ${formatarBRL(r.valor_total)} no Pix?`)">Pagou</button>
        </div>
      </article>
    </template>

    <template v-if="entregar.length">
      <article v-for="r in entregar" :key="r.codigo" class="card">
        <span class="pilula cinza">Pago · entregar</span>
        <div class="topo-card"><strong class="nome">{{ r.cliente?.nome }}</strong><strong class="valor">{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="info">{{ ondeEntregar(r) }} · <a :href="`tel:${r.cliente?.telefone}`">{{ r.cliente?.telefone }}</a></span>
        <label class="campo">Código de 4 dígitos dito pelo cliente
          <input v-model="codigos[r.codigo]" class="codigo" inputmode="numeric" maxlength="4" placeholder="0000" autocomplete="off">
        </label>
        <div class="duas">
          <a v-if="r.tipo === 'entrega' && r.endereco_entrega" :href="mapa(r)" target="_blank" rel="noopener" class="btn btn-cinza-claro">Abrir no mapa</a>
          <button
            class="btn btn-escuro"
            :class="{ inteiro: r.tipo !== 'entrega' }"
            :disabled="ocupado === r.codigo || (codigos[r.codigo] ?? '').length !== 4"
            @click="fazer(r, 'entregar')"
          >
            Entregar pedido
          </button>
        </div>
      </article>
    </template>

    <template v-if="encerradas.length">
      <div class="bloco bloco-contorno encerradas">
      <h2 class="rotulo-secao">Últimas 24h</h2>
      <div v-for="r in encerradas" :key="r.codigo" class="encerrada">
        <span>{{ r.cliente?.nome }} · {{ resumoItens(r) }}</span>
        <span class="dica">{{ ROTULO_STATUS[r.status].replace('por você', 'pelo cliente') }}</span>
      </div>
      </div>
    </template>
    <p v-if="!pendentes.length && reservas.length" class="dica aviso">Avisos de reserva nova chegam aqui; mantenha o painel aberto durante o turno.</p>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.cab { flex-direction: row; align-items: center; justify-content: space-between; }
.titulo-pagina { margin: 0; font-size: 32px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; }
.btn-cinza-claro { min-height: 48px; background: var(--fundo); color: var(--texto); font-size: 14px; }
.btn-branco { background: #FFFFFF; color: #111111; }
.card { background: var(--card); color: var(--texto); border-radius: var(--raio-card); padding: 18px; display: flex; flex-direction: column; gap: 10px; font-size: 14px; }
.card.novo { background: var(--destaque); color: var(--sobre-destaque); }
.topo-card { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.rot { font-size: 13px; font-weight: 600; }
.bloco-preto .rot { color: #A6A6A6; font-weight: 500; }
.pilula.preta { background: #111111; color: #FFFFFF; }
.nome { font-size: 20px; font-weight: 600; letter-spacing: -0.3px; }
.valor { font-size: 20px; font-weight: 600; white-space: nowrap; }
.valor.destaque { font-size: 24px; color: var(--destaque); }
.info { margin: 0; font-size: 14px; opacity: .8; }
.duas { display: grid; grid-template-columns: 1fr 2fr; gap: 8px; margin-top: 4px; }
.duas .btn { min-height: 56px; }
.inteiro { grid-column: 1 / -1; }
.codigo { text-align: center; min-height: 60px !important; font-size: 26px !important; font-weight: 600 !important; letter-spacing: 14px; background: var(--fundo) !important; }
.encerradas { gap: 0; }
.encerrada { display: flex; flex-direction: column; gap: 2px; padding: 10px 0; border-bottom: 1px solid var(--linha); font-size: 14px; }
.encerrada:last-child { border-bottom: 0; }
.aviso { padding: 0 8px; }
</style>
