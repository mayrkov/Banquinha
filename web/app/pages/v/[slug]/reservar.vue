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
  <main class="pagina-bloco">
    <TopoBloco :voltar="`/v/${slug}`" :marca="v?.nome ?? ''" :legenda="cliente ? undefined : 'Passo 1 de 2'" titulo="Sua reserva">
      <span class="resumo">{{ contagem }} {{ contagem === 1 ? 'item' : 'itens' }} · {{ formatarBRL(subtotal) }}</span>
    </TopoBloco>

    <p v-if="v && v.status === 'fora_de_atividade'" class="erro bloco">O vendedor não está em atividade agora. Não dá para reservar.</p>

    <section class="bloco itens" aria-label="Sabores escolhidos">
      <div v-for="p in escolhidos" :key="p.id" class="linha-bloco">
        <span class="amostra" :style="{ background: corDoProduto(p) }" />
        <span class="item-info">
          <span class="item-nome">{{ p.nome }}</span>
          <span class="item-preco">{{ formatarBRL(qtd(p.id) * p.preco) }}</span>
        </span>
        <span class="passos">
          <button class="menos" :aria-label="`Tirar um ${p.nome}`" @click="somar(p, -1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12h14" /></svg></button>
          <span class="num">{{ qtd(p.id) }}</span>
          <button class="mais" :aria-label="`Adicionar um ${p.nome}`" :disabled="qtd(p.id) >= p.disponivel" @click="somar(p, 1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg></button>
        </span>
      </div>
      <p v-if="v && !escolhidos.length" class="dica vazio">Nenhum sabor escolhido.</p>
      <NuxtLink :to="`/v/${slug}`" class="mais-sabores">
        <span class="mais-icone"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg></span>
        Adicionar mais sabores
      </NuxtLink>
    </section>

    <section class="bloco">
      <h2 class="bloco-titulo">Como você quer receber?</h2>
      <div role="radiogroup" aria-label="Forma de recebimento" class="opcoes">
        <button role="radio" :aria-checked="tipo === 'entrega'" :class="{ on: tipo === 'entrega' }" @click="tipo = 'entrega'">
          Entrega<span class="marcador"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
        </button>
        <button role="radio" :aria-checked="tipo === 'retirada'" :class="{ on: tipo === 'retirada' }" @click="tipo = 'retirada'">
          Retirada<span class="marcador"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
        </button>
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
        <label v-for="p in v?.paradas" :key="p.id" class="linha-bloco parada" :class="{ on: paradaId === p.id }">
          <input v-model="paradaId" type="radio" name="parada" :value="p.id">
          <span class="parada-txt">
            <strong>{{ p.local }}</strong>
            <span class="dica">{{ quandoParada(p) }}</span>
          </span>
        </label>
      </template>
    </section>

    <section class="bloco bloco-preto totais">
      <div class="linha"><span class="claro">Subtotal</span><span>{{ formatarBRL(subtotal) }}</span></div>
      <div v-if="tipo === 'entrega'" class="linha"><span class="claro">Taxa de entrega</span><span>{{ formatarBRL(taxa) }}</span></div>
      <div class="linha total"><span class="claro">Total</span><span class="valor">{{ formatarBRL(subtotal + taxa) }}</span></div>
    </section>

    <div class="rodape">
      <p class="dica">O vendedor tem até {{ v?.prazo_resposta_min ?? 15 }} min para aceitar. Você paga por Pix só depois que ele aceitar.</p>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <button class="btn btn-destaque btn-seta" :disabled="!podeEnviar || enviando || v?.status === 'fora_de_atividade'" @click="confirmar">
        {{ enviando ? 'Enviando…' : cliente ? 'Confirmar reserva' : 'Entrar e enviar reserva' }}
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </button>
    </div>
  </main>
</template>

<style scoped>
.resumo { font-size: 16px; }
.itens { padding: 8px; gap: 6px; }
.amostra { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; }
.item-info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.item-nome { font-weight: 600; }
.item-preco { font-size: 13px; color: var(--texto-2); }
.passos { display: flex; align-items: center; gap: 4px; }
.passos button { width: 40px; height: 40px; border: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.passos button:disabled { opacity: .4; }
.menos { background: var(--card); color: var(--texto); }
.mais { background: var(--preto); color: var(--sobre-preto); }
.num { width: 22px; text-align: center; font-weight: 600; }
.vazio { padding: 8px; }
.mais-sabores { min-height: 48px; border: 1px dashed var(--apagado); border-radius: 22px; color: var(--texto); text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 0 8px; font-size: 14px; font-weight: 500; }
.mais-icone { width: 32px; height: 32px; border-radius: 50%; background: var(--fundo); display: flex; align-items: center; justify-content: center; }
.mais-icone svg { width: 14px; height: 14px; }
.opcoes { display: flex; flex-direction: column; gap: 6px; }
.opcoes button { height: 56px; border: 0; background: var(--fundo); color: var(--texto); display: flex; align-items: center; justify-content: space-between; padding: 0 8px 0 20px; font-size: 15px; font-weight: 500; }
.marcador { width: 40px; height: 40px; border-radius: 50%; background: var(--card); display: flex; align-items: center; justify-content: center; }
.marcador svg { visibility: hidden; }
.opcoes button.on { background: var(--preto); color: var(--sobre-preto); }
.opcoes button.on .marcador { background: var(--destaque); color: var(--sobre-destaque); }
.opcoes button.on .marcador svg { visibility: visible; }
.parada { padding: 12px 16px; border: 2px solid transparent; }
.parada.on { border-color: var(--texto); }
.parada input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
.parada-txt { display: flex; flex-direction: column; gap: 2px; }
.totais { padding: 18px; gap: 10px; font-size: 14px; }
.linha { display: flex; justify-content: space-between; align-items: baseline; }
.claro { color: #A6A6A6; }
.linha.total { padding-top: 12px; border-top: 1px solid #2A2A2A; }
.valor { font-size: 34px; font-weight: 600; color: var(--destaque); letter-spacing: -0.5px; }
.rodape { margin-top: auto; position: sticky; bottom: 0; display: flex; flex-direction: column; gap: 8px; padding-top: 8px; background: var(--fundo); }
.rodape > p { margin: 0 8px; }
</style>
