<script setup lang="ts">
definePageMeta({ layout: 'painel' })

type Periodo = 'dia' | 'semana' | 'mes'
interface Metricas {
  periodo: Periodo
  inicio: string
  faturamento_cheio: number
  descontos: number
  faturamento_real: number
  taxas_entrega: number
  por_sabor: { produto_id: number, nome: string, cor: string | null, quantidade: number, valor: number }[]
  por_forma: { pix: number, cartao: number, dinheiro: number }
  reservas: { recebidas: number, aceitas: number, negadas: number, expiradas: number, entregues: number }
  doacoes: { quantidade: number, valor: number }
  perdas: { quantidade: number, valor: number }
  estoque: { produto_id: number, nome: string, cor: string | null, estoque: number }[]
}

const PERIODOS: { id: Periodo, nome: string }[] = [{ id: 'dia', nome: 'Hoje' }, { id: 'semana', nome: 'Semana' }, { id: 'mes', nome: 'Mês' }]
const FORMAS = [
  { id: 'pix', nome: 'Pix', cor: 'var(--destaque)' },
  { id: 'cartao', nome: 'Cartão', cor: '#FFFFFF' },
  { id: 'dinheiro', nome: 'Dinheiro', cor: '#666666' },
] as const

const periodo = ref<Periodo>('dia')
const m = ref<Metricas | null>(null)
const erro = ref('')
const carregando = ref(false)

const maiorSabor = computed(() => Math.max(1, ...(m.value?.por_sabor.map(s => s.quantidade) ?? [])))
const totalFormas = computed(() => m.value ? m.value.por_forma.pix + m.value.por_forma.cartao + m.value.por_forma.dinheiro : 0)
const pct = (v: number) => (totalFormas.value ? Math.round((v / totalFormas.value) * 100) : 0)
const RESERVAS = [
  ['recebidas', 'Recebidas'], ['aceitas', 'Aceitas'], ['entregues', 'Entregues'], ['negadas', 'Negadas'], ['expiradas', 'Expiradas'],
] as const

async function carregar() {
  erro.value = ''
  carregando.value = true
  try {
    m.value = await api<Metricas>(`/vendedor/metricas?periodo=${periodo.value}`)
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. As métricas precisam de internet.'
  }
  finally {
    carregando.value = false
  }
}

watch(periodo, carregar)
onMounted(carregar)
</script>

<template>
  <section class="tela">
    <header class="topo">
      <div role="tablist" aria-label="Período" class="periodos">
        <button
          v-for="p in PERIODOS"
          :key="p.id"
          role="tab"
          :aria-selected="periodo === p.id"
          :class="{ on: periodo === p.id }"
          @click="periodo = p.id"
        >
          {{ p.nome }}
        </button>
      </div>
      <div class="faturamento" aria-live="polite">
        <span class="rot">Faturamento real</span>
        <h1 class="valor">{{ m ? formatarBRL(m.faturamento_real) : '—' }}</h1>
        <span v-if="m" class="rot-fino">
          Cheio {{ formatarBRL(m.faturamento_cheio) }} − descontos {{ formatarBRL(m.descontos) }}
          <template v-if="m.taxas_entrega"> · taxas de entrega {{ formatarBRL(m.taxas_entrega) }}</template>
        </span>
      </div>
    </header>

    <p v-if="erro" class="erro bloco" role="alert">{{ erro }}</p>
    <p v-else-if="carregando && !m" class="dica bloco">Carregando…</p>

    <template v-if="m">
      <section class="bloco">
        <h2 class="bloco-titulo">Vendidos por sabor</h2>
        <p v-if="!m.por_sabor.length" class="dica">Nenhuma venda no período.</p>
        <div v-for="s in m.por_sabor" :key="s.produto_id" class="barra-linha">
          <div class="barra-rot"><span>{{ s.nome }}</span><span><strong>{{ s.quantidade }}</strong> · {{ formatarBRL(s.valor) }}</span></div>
          <div class="trilho" aria-hidden="true">
            <div class="cheio" :style="{ width: `${(s.quantidade / maiorSabor) * 100}%`, background: corDoProduto({ id: s.produto_id, cor: s.cor }) }" />
          </div>
        </div>
      </section>

      <section class="bloco bloco-preto">
        <h2 class="bloco-titulo">Forma de pagamento</h2>
        <div v-if="totalFormas" class="pilha" aria-hidden="true">
          <template v-for="f in FORMAS" :key="f.id">
            <div v-if="m.por_forma[f.id]" :style="{ width: `${pct(m.por_forma[f.id])}%`, background: f.cor }" />
          </template>
        </div>
        <ul class="formas">
          <li v-for="f in FORMAS" :key="f.id">
            <span class="ponto" :style="{ background: f.cor }" aria-hidden="true" />
            <span class="forma-nome">{{ f.nome }}</span>
            <span>{{ formatarBRL(m.por_forma[f.id]) }}</span>
            <span class="claro">{{ pct(m.por_forma[f.id]) }}%</span>
          </li>
        </ul>
      </section>

      <section class="bloco">
        <h2 class="bloco-titulo">Reservas</h2>
        <div class="numeros">
          <div v-for="[id, nome] in RESERVAS" :key="id" class="numero">
            <span class="numero-rot">{{ nome }}</span>
            <span class="numero-valor">{{ m.reservas[id] }}</span>
          </div>
        </div>
      </section>

      <section class="bloco">
        <h2 class="bloco-titulo">Estoque agora</h2>
        <p v-if="!m.estoque.length" class="dica">Nenhum produto ativo.</p>
        <div v-for="p in m.estoque" :key="p.produto_id" class="linha-bloco estoque">
          <span class="amostra" :style="{ background: corDoProduto({ id: p.produto_id, cor: p.cor }) }" aria-hidden="true" />
          <span class="estoque-nome">{{ p.nome }}</span>
          <strong :class="{ zerado: p.estoque <= 0 }">{{ p.estoque }}</strong>
        </div>
      </section>

      <section class="saidas">
        <div class="bloco">
          <span class="numero-rot">Doações</span>
          <span class="numero-valor">{{ m.doacoes.quantidade }}</span>
          <span class="dica">{{ formatarBRL(m.doacoes.valor) }} no preço cheio</span>
        </div>
        <div class="bloco">
          <span class="numero-rot">Perdas</span>
          <span class="numero-valor">{{ m.perdas.quantidade }}</span>
          <span class="dica">{{ formatarBRL(m.perdas.valor) }} no preço cheio</span>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.topo { background: var(--destaque); color: var(--sobre-destaque); border-radius: var(--raio-card); padding: 14px; display: flex; flex-direction: column; gap: 16px; }
.periodos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px; background: #FFFFFF; border-radius: var(--pilula); padding: 4px; }
.periodos button { height: 44px; border: 0; background: none; color: #3D3D3D; font-weight: 600; font-size: 13px; }
.periodos button.on { background: #111111; color: #FFFFFF; }
.faturamento { display: flex; flex-direction: column; gap: 4px; padding: 0 6px 4px; }
.rot { font-size: 13px; font-weight: 600; }
.rot-fino { font-size: 13px; }
.valor { margin: 0; font-size: 44px; font-weight: 600; letter-spacing: -1.5px; line-height: 1; overflow-wrap: anywhere; }
.barra-linha { display: flex; flex-direction: column; gap: 6px; }
.barra-rot { display: flex; justify-content: space-between; gap: 8px; font-size: 14px; }
.trilho { height: 12px; border-radius: 6px; background: var(--fundo); }
.cheio { height: 12px; border-radius: 6px; min-width: 12px; }
.pilha { display: flex; gap: 4px; height: 14px; }
.pilha div { border-radius: 7px; min-width: 14px; }
.formas { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: 14px; }
.formas li { display: flex; align-items: center; gap: 8px; }
.ponto { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.forma-nome { flex: 1; }
.claro { color: #A6A6A6; min-width: 40px; text-align: right; }
.numeros { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.numero { background: var(--fundo); border-radius: 22px; padding: 14px; display: flex; flex-direction: column; gap: 4px; }
.numero-rot { font-size: 12px; font-weight: 500; color: var(--texto-2); }
.numero-valor { font-size: 28px; font-weight: 600; }
.estoque { padding: 8px 18px 8px 8px; }
.amostra { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; }
.estoque-nome { flex: 1; font-weight: 600; }
.estoque strong { font-size: 18px; font-weight: 600; }
.zerado { color: var(--destaque-texto); }
.saidas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.saidas .bloco { gap: 4px; }
</style>
