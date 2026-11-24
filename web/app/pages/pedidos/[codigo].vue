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
const liberado = computed(() => !!r.value?.codigo_entrega && r.value.status === 'pago')
// Quatro casas do código de entrega; bloqueado mostra bolinhas.
const digitos = computed(() => {
  if (liberado.value) return r.value!.codigo_entrega!.split('')
  return r.value?.status === 'entregue' ? ['✓', '✓', '✓', '✓'] : ['•', '•', '•', '•']
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
  <main class="pagina-bloco">
    <TopoBloco
      escuro
      voltar="/pedidos"
      rotulo-voltar="Voltar para meus pedidos"
      :marca="r?.vendedor?.nome ?? 'Pedido'"
      :legenda="r ? ROTULO_STATUS[r.status] : undefined"
    >
      <template v-if="r">
        <span class="rotulo-claro">{{ r.status === 'aceito' ? 'Pague com Pix' : 'Total' }}</span>
        <span class="valor">{{ formatarBRL(r.valor_total) }}</span>
        <span class="detalhe">
          {{ resumoItens(r) }} ·
          <template v-if="r.tipo === 'entrega'">Entrega em {{ r.endereco_entrega ?? '(endereço apagado)' }}</template>
          <template v-else>Retirada em {{ r.parada?.local }}<template v-if="r.parada">, {{ quandoParada(r.parada) }}</template></template>
          <template v-if="r.taxa_entrega"> · taxa {{ formatarBRL(r.taxa_entrega) }}</template>
        </span>
        <ol v-if="!encerradaSemEntrega" class="etapas" aria-label="Andamento do pedido">
          <li v-for="(e, k) in ETAPAS" :key="e" :class="{ feita: k < etapa, atual: k === etapa }" :aria-current="k === etapa ? 'step' : undefined">
            <span class="barra" />{{ e }}
          </li>
        </ol>
      </template>
    </TopoBloco>

    <p v-if="erro" class="erro bloco" role="alert">{{ erro }}</p>

    <template v-if="r">
      <section v-if="r.status === 'pendente'" class="bloco">
        <p class="aviso">O vendedor tem até {{ minutosAte(r.responder_ate) }} min para responder. Você paga só depois que ele aceitar.</p>
        <button class="btn btn-cinza-claro" :disabled="enviando" @click="acao('cancelar')">Cancelar reserva</button>
      </section>

      <template v-if="r.status === 'aceito'">
        <section class="bloco centro">
          <img v-if="qr" :src="qr" alt="QR Code Pix com o valor do pedido" class="qr">
          <p v-else class="erro">O vendedor ainda não cadastrou a chave Pix. Fale com ele{{ r.vendedor?.telefone ? ` pelo ${r.vendedor.telefone}` : '' }}.</p>
          <div v-if="brCode" class="copia">
            <input ref="campoCopia" aria-label="Pix copia e cola" :value="brCode" readonly>
            <button class="btn btn-escuro" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar' }}</button>
          </div>
        </section>
        <section class="pagar">
          <button v-if="!r.cliente_informou_pagamento_em" class="btn btn-destaque btn-seta" :disabled="enviando" @click="acao('ja-paguei')">
            Já paguei<span class="bolinha"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
          </button>
          <p v-else class="bloco aviso">Você avisou que pagou. Aguardando o vendedor conferir.</p>
          <p class="dica">O vendedor confere no banco e confirma. Aí seu código de entrega aparece aqui.</p>
        </section>
      </template>

      <section v-if="!encerradaSemEntrega" class="bloco">
        <div class="codigo-topo">
          <h2 class="bloco-titulo">Código de entrega</h2>
          <span class="pilula" :class="{ cinza: !liberado }">{{ r.status === 'entregue' ? 'Entregue' : liberado ? 'Liberado' : 'Bloqueado' }}</span>
        </div>
        <div class="digitos" :class="{ ativo: liberado }" :aria-label="liberado ? `Código ${r.codigo_entrega}` : undefined">
          <span v-for="(d, k) in digitos" :key="k" aria-hidden="true">{{ d }}</span>
        </div>
        <span class="dica">{{ r.status === 'entregue' ? 'Pedido entregue.' : 'Diga este código ao vendedor na hora de receber.' }}</span>
      </section>

      <NuxtLink v-if="encerradaSemEntrega && r.vendedor" :to="`/v/${r.vendedor.slug}`" class="btn btn-destaque btn-seta">
        Fazer outra reserva<span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </NuxtLink>
    </template>
  </main>
</template>

<style scoped>
.rotulo-claro { font-size: 13px; color: #A6A6A6; }
.valor { font-size: 44px; font-weight: 600; color: var(--destaque); letter-spacing: -1.5px; line-height: 1; }
.detalhe { font-size: 14px; color: #D6D6D6; line-height: 1.5; }
.etapas { margin: 6px 0 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; font-size: 11px; font-weight: 600; color: #A6A6A6; }
.etapas li { display: flex; flex-direction: column; gap: 6px; }
.barra { height: 6px; border-radius: 3px; background: #3A3A3A; }
.feita .barra { background: #FFFFFF; }
.atual { color: #FFFFFF; }
.atual .barra { background: var(--destaque); }
.aviso { margin: 0; line-height: 1.5; font-size: 14px; }
.btn-cinza-claro { min-height: 48px; background: var(--fundo); color: var(--texto); font-size: 14px; }
.centro { align-items: center; gap: 14px; }
.qr { width: 220px; height: 220px; border-radius: 22px; background: #FFFFFF; padding: 10px; }
.copia { align-self: stretch; height: 56px; background: var(--fundo); border-radius: var(--pilula); display: flex; align-items: center; gap: 8px; padding: 0 6px 0 18px; }
.copia input { flex: 1; min-width: 0; background: none; border: 0; color: var(--texto-2); font-size: 14px; }
.copia .btn { min-height: 44px; font-size: 13px; }
.pagar { display: flex; flex-direction: column; gap: 8px; }
.pagar > .dica { margin: 0 8px; }
.codigo-topo { display: flex; justify-content: space-between; align-items: center; }
.codigo-topo .pilula { align-self: auto; }
.digitos { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.digitos span { height: 56px; border-radius: 28px; background: var(--fundo); color: var(--texto-3); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 600; }
.digitos.ativo span { background: var(--preto); color: var(--sobre-preto); }
</style>
