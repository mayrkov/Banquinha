<script setup lang="ts">
import QRCode from 'qrcode'

definePageMeta({ middleware: 'cliente' })

const codigo = useRoute().params.codigo as string
const r = ref<Reserva | null>(null)
const erro = ref('')
const qr = ref('')
const brCode = ref('')
const copiado = ref(false)
const enviando = ref(false)

const ETAPAS = ['Enviado', 'Aceito', 'Pagamento', 'Entregue']
const etapa = computed(() => {
  switch (r.value?.status) {
    case 'pendente': return 0
    case 'aceito': return 2
    case 'pago': return 3
    case 'entregue': return 4
    default: return -1
  }
})
const encerradaSemEntrega = computed(() => ['negado', 'expirado', 'cancelado_cliente', 'cancelado_vendedor'].includes(r.value?.status ?? ''))

async function carregar() {
  try {
    r.value = await api<Reserva>(`/cliente/reservas/${codigo}`)
    erro.value = ''
  }
  catch (e) {
    if (e instanceof ErroApi && e.status === 401) {
      useCliente().esquecer()
      return navigateTo({ path: '/entrar', query: { volta: `/pedidos/${codigo}` } })
    }
    erro.value = e instanceof ErroApi && e.status === 404 ? 'Pedido não encontrado.' : 'Sem conexão. Tentando de novo…'
  }
}

// D44: o BR Code da reserva é montado no navegador com os dados de Pix que a API devolve após o aceite.
watch(() => r.value?.pix, async (pix) => {
  if (!pix?.chave || !pix.nome || !pix.cidade || !r.value) {
    qr.value = brCode.value = ''
    return
  }
  brCode.value = gerarBRCode({ chave: pix.chave, nome: pix.nome, cidade: pix.cidade, valorCentavos: r.value.valor_total })
  qr.value = await QRCode.toDataURL(brCode.value, { margin: 1, width: 440 })
})

const campoCopia = ref<HTMLInputElement | null>(null)

async function copiar() {
  try {
    await navigator.clipboard.writeText(brCode.value)
  }
  catch {
    // navigator.clipboard só existe em HTTPS; em http seleciona e usa o comando antigo.
    campoCopia.value?.select()
    document.execCommand('copy')
  }
  copiado.value = true
  setTimeout(() => { copiado.value = false }, 2000)
}

async function acao(caminho: 'ja-paguei' | 'cancelar') {
  if (caminho === 'cancelar' && !confirm('Cancelar esta reserva?')) return
  enviando.value = true
  try {
    r.value = await api<Reserva>(`/cliente/reservas/${codigo}/${caminho}`, { method: 'POST' })
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão.'
    await carregar()
  }
  finally {
    enviando.value = false
  }
}

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  carregar()
  timer = setInterval(carregar, 15_000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <main class="pagina">
    <header class="topo-cliente">
      <NuxtLink to="/pedidos" class="icone-btn" aria-label="Voltar para meus pedidos"><IconeVoltar /></NuxtLink>
      <span class="marca">{{ r?.vendedor?.nome ?? 'Pedido' }}</span>
      <span class="icone-btn" />
    </header>

    <p v-if="erro" class="erro secao" role="alert">{{ erro }}</p>

    <template v-if="r">
      <ol v-if="!encerradaSemEntrega" class="etapas" aria-label="Andamento do pedido">
        <li v-for="(e, k) in ETAPAS" :key="e" :class="{ feita: k < etapa, atual: k === etapa }" :aria-current="k === etapa ? 'step' : undefined">
          <span class="barra" />{{ e }}
        </li>
      </ol>

      <section class="secao">
        <h1 class="titulo-grande">{{ formatarBRL(r.valor_total) }}</h1>
        <span class="subtitulo">
          <template v-if="r.status === 'aceito'">Pague com Pix</template>
          <template v-else>{{ ROTULO_STATUS[r.status] }}</template>
        </span>
        <span class="dica detalhe">
          {{ resumoItens(r) }} ·
          <template v-if="r.tipo === 'entrega'">Entrega em {{ r.endereco_entrega ?? '(endereço apagado)' }}</template>
          <template v-else>Retirada em {{ r.parada?.local }}<template v-if="r.parada">, {{ quandoParada(r.parada) }}</template></template>
          <template v-if="r.taxa_entrega"> · taxa {{ formatarBRL(r.taxa_entrega) }}</template>
        </span>
      </section>

      <section v-if="r.status === 'pendente'" class="secao">
        <p class="aviso">O vendedor tem até {{ minutosAte(r.responder_ate) }} min para responder. Você paga só depois que ele aceitar.</p>
        <button class="btn btn-contorno btn-bloco" :disabled="enviando" @click="acao('cancelar')">Cancelar reserva</button>
      </section>

      <template v-if="r.status === 'aceito'">
        <section class="secao centro">
          <img v-if="qr" :src="qr" alt="QR Code Pix com o valor do pedido" class="qr">
          <p v-else class="erro">O vendedor ainda não cadastrou a chave Pix. Fale com ele{{ r.vendedor?.telefone ? ` pelo ${r.vendedor.telefone}` : '' }}.</p>
          <div v-if="brCode" class="copia">
            <input ref="campoCopia" aria-label="Pix copia e cola" :value="brCode" readonly>
            <button class="btn btn-cinza" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar' }}</button>
          </div>
        </section>
        <section class="secao">
          <button v-if="!r.cliente_informou_pagamento_em" class="btn btn-destaque btn-bloco" :disabled="enviando" @click="acao('ja-paguei')">Já paguei</button>
          <p v-else class="aviso">Você avisou que pagou. Aguardando o vendedor conferir.</p>
          <p class="dica">O vendedor confere no banco e confirma. Aí seu código de entrega aparece aqui.</p>
        </section>
      </template>

      <section v-if="!encerradaSemEntrega" class="secao">
        <div class="codigo" :class="{ ativo: r.codigo_entrega && r.status === 'pago' }">
          <span class="rotulo-secao">Código de entrega</span>
          <span class="digitos">{{ r.codigo_entrega && r.status === 'pago' ? r.codigo_entrega : r.status === 'entregue' ? 'OK' : '• • • •' }}</span>
          <span class="dica">{{ r.status === 'entregue' ? 'Pedido entregue.' : 'Diga este código ao vendedor na hora de receber.' }}</span>
        </div>
      </section>

      <section v-if="encerradaSemEntrega" class="secao">
        <NuxtLink v-if="r.vendedor" :to="`/v/${r.vendedor.slug}`" class="btn btn-cinza btn-bloco">Fazer outra reserva</NuxtLink>
      </section>
    </template>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; max-width: 640px; margin: 0 auto; padding-bottom: 32px; }
.secao { padding: 20px 16px 0; display: flex; flex-direction: column; gap: 8px; margin: 0; }
.centro { align-items: center; }
.etapas { margin: 0; padding: 8px 16px 0; list-style: none; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--texto-3); }
.etapas li { display: flex; flex-direction: column; gap: 6px; }
.barra { height: 4px; background: #D0D0D0; }
.feita { color: var(--texto-2); }
.feita .barra { background: var(--texto); }
.atual { color: var(--texto); }
.atual .barra { background: var(--destaque); }
.detalhe { font-size: 14px; color: var(--texto-2); margin-top: 6px; line-height: 1.5; }
.aviso { margin: 0; background: var(--card); border-radius: var(--raio); padding: 14px; line-height: 1.5; font-size: 14px; }
.qr { width: 220px; height: 220px; border-radius: var(--raio-card); background: var(--card); padding: 10px; }
.copia { align-self: stretch; display: flex; gap: 8px; }
.copia input { flex: 1; min-width: 0; height: 48px; border: 0; background: var(--card); padding: 0 12px; font-size: 14px; color: var(--texto-2); }
.copia .btn { min-height: 48px; font-size: 13px; }
.codigo { padding: 20px; border: 1px dashed #666666; border-radius: var(--raio-card); display: flex; flex-direction: column; gap: 8px; align-items: center; text-align: center; }
.codigo.ativo { border: 2px solid var(--destaque-texto); background: var(--card); }
.digitos { font-size: 48px; font-weight: 900; letter-spacing: 12px; color: #666666; }
.ativo .digitos { color: var(--texto); }
</style>
