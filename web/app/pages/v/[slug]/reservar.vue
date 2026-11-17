<script setup lang="ts">
interface ProdutoPublico { id: number, nome: string, cor: string | null, preco: number, disponivel: number }
interface VendedorPublico {
  nome: string
  status: string
  taxa_entrega: number
  prazo_resposta_min: number
  produtos: ProdutoPublico[]
  paradas: Parada[]
}

const slug = useRoute().params.slug as string
const { cliente } = useCliente()
const { qtd, definir, carregar, limpar } = useCarrinho(slug)

const v = ref<VendedorPublico | null>(null)
const tipo = ref<'entrega' | 'retirada'>('entrega')
const endereco = ref('')
const referencia = ref('')
const paradaId = ref<number | null>(null)
const erro = ref('')
const enviando = ref(false)

// Rascunho do formulário: sobrevive à ida para o login.
const RASCUNHO = `banquinha:rascunho:${slug}`

const escolhidos = computed(() => (v.value?.produtos ?? []).filter(p => qtd(p.id) > 0))
const subtotal = computed(() => escolhidos.value.reduce((n, p) => n + qtd(p.id) * p.preco, 0))
const taxa = computed(() => (tipo.value === 'entrega' ? v.value?.taxa_entrega ?? 0 : 0))
const contagem = computed(() => escolhidos.value.reduce((n, p) => n + qtd(p.id), 0))
const podeEnviar = computed(() =>
  contagem.value > 0 && (tipo.value === 'entrega' ? endereco.value.trim().length > 4 : paradaId.value !== null),
)

function somar(p: ProdutoPublico, d: number) {
  definir(p.id, Math.min(p.disponivel, Math.max(0, qtd(p.id) + d)))
}

function guardarRascunho() {
  sessionStorage.setItem(RASCUNHO, JSON.stringify({ tipo: tipo.value, endereco: endereco.value, referencia: referencia.value, paradaId: paradaId.value }))
}

async function confirmar() {
  erro.value = ''
  if (!cliente.value) {
    guardarRascunho()
    return navigateTo({ path: '/entrar', query: { volta: `/v/${slug}/reservar` } })
  }
  enviando.value = true
  try {
    const r = await api<Reserva>('/cliente/reservas', {
      method: 'POST',
      body: {
        vendedor: slug,
        tipo: tipo.value,
        ...(tipo.value === 'entrega'
          ? { endereco_entrega: endereco.value.trim(), referencia_entrega: referencia.value.trim() || null }
          : { parada_id: paradaId.value }),
        itens: escolhidos.value.map(p => ({ produto_id: p.id, quantidade: qtd(p.id) })),
      },
    })
    limpar()
    sessionStorage.removeItem(RASCUNHO)
    await navigateTo(`/pedidos/${r.codigo}`)
  }
  catch (e) {
    if (e instanceof ErroApi && e.status === 401) {
      useCliente().esquecer()
      guardarRascunho()
      return navigateTo({ path: '/entrar', query: { volta: `/v/${slug}/reservar` } })
    }
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo.'
    await carregarVendedor()
  }
  finally {
    enviando.value = false
  }
}

async function carregarVendedor() {
  v.value = await $fetch<VendedorPublico>(`/api/v1/publico/vendedores/${slug}`)
  for (const p of v.value.produtos) if (qtd(p.id) > p.disponivel) definir(p.id, p.disponivel)
}

onMounted(async () => {
  carregar()
  try {
    const r = JSON.parse(sessionStorage.getItem(RASCUNHO) ?? 'null')
    if (r) {
      tipo.value = r.tipo
      endereco.value = r.endereco
      referencia.value = r.referencia
      paradaId.value = r.paradaId
    }
  }
  catch {}
  try {
    await carregarVendedor()
  }
  catch {
    erro.value = 'Não foi possível carregar o vendedor.'
  }
})
</script>

<template>
  <main class="pagina">
    <header class="topo-cliente">
      <NuxtLink :to="`/v/${slug}`" class="icone-btn" aria-label="Voltar"><IconeVoltar /></NuxtLink>
      <span class="marca">{{ v?.nome }}</span>
      <span class="icone-btn" />
    </header>

    <div class="cab">
      <h1 class="titulo-grande">Sua reserva</h1>
      <span class="subtitulo">{{ contagem }} {{ contagem === 1 ? 'item' : 'itens' }} · {{ formatarBRL(subtotal) }}</span>
    </div>

    <p v-if="v && v.status === 'fora_de_atividade'" class="erro secao">O vendedor não está em atividade agora. Não dá para reservar.</p>

    <section class="secao">
      <div v-for="p in escolhidos" :key="p.id" class="item">
        <span class="amostra" :style="{ background: corDoProduto(p) }" />
        <div class="item-info">
          <span class="item-nome">{{ p.nome }}</span>
          <span class="item-preco">{{ formatarBRL(qtd(p.id) * p.preco) }}</span>
        </div>
        <div class="passos">
          <button class="menos" :aria-label="`Tirar um ${p.nome}`" @click="somar(p, -1)">−</button>
          <span class="num">{{ qtd(p.id) }}</span>
          <button class="mais" :aria-label="`Adicionar um ${p.nome}`" :disabled="qtd(p.id) >= p.disponivel" @click="somar(p, 1)">+</button>
        </div>
      </div>
      <p v-if="v && !escolhidos.length" class="dica">Nenhum sabor escolhido.</p>
      <NuxtLink :to="`/v/${slug}`" class="mais-sabores">+ Adicionar mais sabores</NuxtLink>
    </section>

    <section class="secao">
      <h2 class="rotulo-secao">Como você quer receber?</h2>
      <div role="radiogroup" aria-label="Forma de recebimento" class="opcoes">
        <button role="radio" :aria-checked="tipo === 'entrega'" :class="{ on: tipo === 'entrega' }" @click="tipo = 'entrega'">Entrega</button>
        <button role="radio" :aria-checked="tipo === 'retirada'" :class="{ on: tipo === 'retirada' }" @click="tipo = 'retirada'">Retirada</button>
      </div>

      <template v-if="tipo === 'entrega'">
        <label class="campo">Endereço
          <input v-model="endereco" autocomplete="street-address" maxlength="500" placeholder="Rua, número, complemento">
        </label>
        <label class="campo">Ponto de referência
          <input v-model="referencia" maxlength="255" placeholder="Ex.: portão azul, ao lado da padaria">
        </label>
        <p class="dica">O endereço é apagado 3 dias depois que o pedido termina.</p>
      </template>

      <template v-else>
        <p v-if="!v?.paradas.length" class="dica">O vendedor não tem paradas programadas. Escolha entrega.</p>
        <label v-for="p in v?.paradas" :key="p.id" class="parada" :class="{ on: paradaId === p.id }">
          <input v-model="paradaId" type="radio" name="parada" :value="p.id">
          <span class="parada-txt">
            <strong>{{ p.local }}</strong>
            <span class="dica">{{ quandoParada(p) }}</span>
          </span>
        </label>
      </template>
    </section>

    <section class="secao totais">
      <div class="linha"><span>Subtotal</span><span>{{ formatarBRL(subtotal) }}</span></div>
      <div v-if="tipo === 'entrega'" class="linha"><span>Taxa de entrega</span><span>{{ formatarBRL(taxa) }}</span></div>
      <div class="linha total"><span>TOTAL</span><span>{{ formatarBRL(subtotal + taxa) }}</span></div>
      <p class="dica">O vendedor tem até {{ v?.prazo_resposta_min ?? 15 }} min para aceitar. Você paga por Pix só depois que ele aceitar.</p>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
    </section>

    <div class="barra-fixa">
      <button class="btn btn-destaque btn-bloco" :disabled="!podeEnviar || enviando || v?.status === 'fora_de_atividade'" @click="confirmar">
        {{ enviando ? 'Enviando…' : cliente ? 'Confirmar reserva' : 'Entrar e enviar reserva' }}
      </button>
    </div>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; display: flex; flex-direction: column; max-width: 640px; margin: 0 auto; }
.cab { padding: 8px 16px 0; display: flex; flex-direction: column; gap: 4px; }
.secao { padding: 20px 16px 0; display: flex; flex-direction: column; gap: 10px; margin: 0; }
.item { background: var(--card); border-radius: var(--raio-card); padding: 10px; display: flex; align-items: center; gap: 12px; }
.amostra { width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0; }
.item-info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.item-nome { font-weight: 900; text-transform: uppercase; }
.item-preco { font-weight: 300; font-size: 14px; }
.passos { display: flex; align-items: center; gap: 4px; }
.passos button { width: 44px; height: 44px; border: 0; font-size: 22px; font-weight: 900; }
.menos { background: var(--cinza); }
.mais { background: var(--destaque); }
.num { width: 24px; text-align: center; font-weight: 900; }
.mais-sabores { padding: 8px 0; font-size: 14px; font-weight: 700; }
.opcoes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.opcoes button { height: 52px; border: 0; background: var(--cinza); font-weight: 900; letter-spacing: 1.5px; font-size: 14px; text-transform: uppercase; }
.opcoes button.on { background: var(--texto); color: var(--texto-inverso); }
.parada { background: var(--card); border-radius: var(--raio-card); padding: 14px; display: flex; gap: 12px; align-items: center; border: 2px solid transparent; }
.parada.on { border-color: var(--destaque-texto); }
.parada input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
.parada-txt { display: flex; flex-direction: column; gap: 2px; }
.totais { padding-bottom: 24px; }
.linha { display: flex; justify-content: space-between; font-weight: 300; }
.linha.total { font-weight: 900; font-size: 24px; padding-top: 6px; }
.barra-fixa { margin-top: auto; }
</style>
