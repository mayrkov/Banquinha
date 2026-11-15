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
    <header class="cab">
      <h1 class="titulo">Reservas</h1>
      <button class="btn btn-linha" @click="carregar">Atualizar</button>
    </header>
    <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
    <p v-if="carregou && !reservas.length" class="dica">Nenhuma reserva por enquanto.</p>

    <template v-if="pendentes.length">
      <h2 class="rotulo-secao">Novas · responda no prazo</h2>
      <article v-for="r in pendentes" :key="r.codigo" class="card novo">
        <div class="topo-card"><strong>{{ r.cliente?.nome }}</strong><strong>{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="dica">{{ ondeEntregar(r) }}</span>
        <span class="prazo">Responder em {{ minutos(r.responder_ate) }} min</span>
        <div class="duas">
          <button class="btn btn-contorno" :disabled="ocupado === r.codigo" @click="fazer(r, 'negar', 'Negar esta reserva?')">Negar</button>
          <button class="btn btn-destaque" :disabled="ocupado === r.codigo" @click="fazer(r, 'aceitar')">Aceitar</button>
        </div>
      </article>
    </template>

    <template v-if="pagou.length">
      <h2 class="rotulo-secao">Cliente disse "já paguei"</h2>
      <article v-for="r in pagou" :key="r.codigo" class="card">
        <div class="topo-card"><strong>{{ r.cliente?.nome }}</strong><strong>{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="dica">{{ ondeEntregar(r) }}</span>
        <p class="dica">Confira no app do banco antes de confirmar.</p>
        <button class="btn btn-escuro" :disabled="ocupado === r.codigo" @click="fazer(r, 'confirmar-pagamento', `Confirmar que recebeu ${formatarBRL(r.valor_total)} no Pix?`)">Confirmar pagamento</button>
      </article>
    </template>

    <template v-if="aguardando.length">
      <h2 class="rotulo-secao">Aceitas · aguardando Pix</h2>
      <article v-for="r in aguardando" :key="r.codigo" class="card">
        <div class="topo-card"><strong>{{ r.cliente?.nome }}</strong><strong>{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="dica">{{ ondeEntregar(r) }} · <a :href="`tel:${r.cliente?.telefone}`">{{ r.cliente?.telefone }}</a></span>
        <div class="duas">
          <button class="btn btn-contorno" :disabled="ocupado === r.codigo" @click="fazer(r, 'cancelar', 'Cancelar e liberar as unidades?')">Cancelar</button>
          <button class="btn btn-escuro" :disabled="ocupado === r.codigo" @click="fazer(r, 'confirmar-pagamento', `Confirmar que recebeu ${formatarBRL(r.valor_total)} no Pix?`)">Pagou</button>
        </div>
      </article>
    </template>

    <template v-if="entregar.length">
      <h2 class="rotulo-secao">Pago · entregar</h2>
      <article v-for="r in entregar" :key="r.codigo" class="card">
        <div class="topo-card"><strong>{{ r.cliente?.nome }}</strong><strong>{{ formatarBRL(r.valor_total) }}</strong></div>
        <span>{{ resumoItens(r) }}</span>
        <span class="dica">{{ ondeEntregar(r) }} · <a :href="`tel:${r.cliente?.telefone}`">{{ r.cliente?.telefone }}</a></span>
        <label class="campo">Código de 4 dígitos dito pelo cliente
          <input v-model="codigos[r.codigo]" class="codigo" inputmode="numeric" maxlength="4" placeholder="0000" autocomplete="off">
        </label>
        <div class="duas">
          <a v-if="r.tipo === 'entrega' && r.endereco_entrega" :href="mapa(r)" target="_blank" rel="noopener" class="btn btn-contorno">Abrir no mapa</a>
          <button
            class="btn btn-destaque"
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
      <h2 class="rotulo-secao">Últimas 24h</h2>
      <div v-for="r in encerradas" :key="r.codigo" class="encerrada">
        <span>{{ r.cliente?.nome }} · {{ resumoItens(r) }}</span>
        <span class="dica">{{ ROTULO_STATUS[r.status].replace('por você', 'pelo cliente') }}</span>
      </div>
    </template>
    <p v-if="!pendentes.length && reservas.length" class="dica">Avisos de reserva nova chegam aqui; mantenha o painel aberto durante o turno.</p>
  </section>
</template>

<style scoped>
.tela { padding: 0 16px 24px; display: flex; flex-direction: column; gap: 10px; }
.cab { height: 64px; display: flex; align-items: center; justify-content: space-between; }
.rotulo-secao { margin-top: 8px; font-size: 13px; }
.card { background: var(--card); border-radius: var(--raio-card); padding: 16px; display: flex; flex-direction: column; gap: 8px; font-size: 15px; }
.card.novo { border-top: 4px solid var(--destaque-texto); }
.topo-card { display: flex; justify-content: space-between; gap: 8px; font-size: 17px; }
.prazo { font-size: 13px; font-weight: 900; color: var(--destaque-texto); letter-spacing: 1px; text-transform: uppercase; }
.duas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 6px; }
.inteiro { grid-column: 1 / -1; }
.card .dica { font-size: 14px; }
.codigo { text-align: center; font-size: 28px !important; font-weight: 900 !important; letter-spacing: 12px; background: var(--fundo) !important; }
.encerrada { display: flex; flex-direction: column; gap: 2px; padding: 10px 0; border-bottom: 1px solid var(--linha); font-size: 14px; }
</style>
