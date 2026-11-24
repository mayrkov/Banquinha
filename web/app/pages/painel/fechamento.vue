<script setup lang="ts">
definePageMeta({ layout: 'painel' })

type Motivo = 'doacao' | 'perda' | 'venda_nao_registrada' | 'outro'
type Forma = 'pix' | 'dinheiro' | 'cartao'
interface ProdutoCaixa { produto_id: number, nome: string, cor: string | null, preco: number, estoque: number, vendidos: number }
interface Resumo {
  turno: { inicio: string, fim: string | null } | null
  por_forma: Record<Forma, number>
  total: number
  produtos: ProdutoCaixa[]
}
interface Contagem { contagem: string, motivo: Motivo | '', preco: string, forma: Forma, metodo: 'credito' | 'debito', obs: string }

const MOTIVOS: { id: Motivo, nome: string }[] = [
  { id: 'venda_nao_registrada', nome: 'Venda não registrada' },
  { id: 'doacao', nome: 'Doação' },
  { id: 'perda', nome: 'Perda' },
  { id: 'outro', nome: 'Outro' },
]
const FORMAS: { id: Forma, nome: string }[] = [{ id: 'cartao', nome: 'Cartão' }, { id: 'pix', nome: 'Pix' }, { id: 'dinheiro', nome: 'Dinheiro' }]

const { vendedor, turno } = useVendedor()
const { fila } = useFilaVendas()
const online = useOnline()
const resumo = ref<Resumo | null>(null)
const contagens = reactive<Record<number, Contagem>>({})
const erro = ref('')
const enviando = ref(false)

const emTurno = computed(() => !!vendedor.value?.em_atividade)
const numero = (t: string) => (/^\d+$/.test(t.trim()) ? Number(t) : null)
const diferenca = (p: ProdutoCaixa) => {
  const n = numero(contagens[p.produto_id]?.contagem ?? '')
  return n === null ? 0 : n - p.estoque
}
const pendencias = computed(() => (resumo.value?.produtos ?? []).filter((p) => {
  const c = contagens[p.produto_id]!
  if (numero(c.contagem) === null) return true
  if (diferenca(p) >= 0) return false
  if (!c.motivo) return true
  return c.motivo === 'venda_nao_registrada' && centavosDeTexto(c.preco) === null
}).length)

function preencher(r: Resumo) {
  resumo.value = r
  for (const p of r.produtos) {
    contagens[p.produto_id] = { contagem: String(Math.max(0, p.estoque)), motivo: '', preco: textoDeCentavos(p.preco), forma: 'dinheiro', metodo: 'debito', obs: '' }
  }
}

async function carregar() {
  erro.value = ''
  try {
    preencher(await api<Resumo>('/vendedor/fechamento'))
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. O fechamento precisa de internet.'
  }
}

async function concluir() {
  if (!resumo.value) return
  if (emTurno.value && !confirm('Encerrar o turno? Você some do mapa e ninguém consegue reservar.')) return
  erro.value = ''
  enviando.value = true
  try {
    const corpo = resumo.value.produtos.map((p) => {
      const c = contagens[p.produto_id]!
      const falta = diferenca(p) < 0
      const venda = falta && c.motivo === 'venda_nao_registrada'
      return {
        produto_id: p.produto_id,
        contagem: numero(c.contagem)!,
        motivo: falta ? c.motivo : null,
        preco_unitario: venda ? centavosDeTexto(c.preco) : null,
        forma_pagamento: venda ? c.forma : null,
        metodo_cartao: venda && c.forma === 'cartao' ? c.metodo : null,
        observacao: falta && c.motivo === 'outro' ? c.obs.trim() || null : null,
      }
    })
    if (corpo.length) preencher(await api<Resumo>('/vendedor/fechamento', { method: 'POST', body: { contagens: corpo } }))
    if (emTurno.value) {
      await turno('encerrar')
      await navigateTo('/painel/turno')
    }
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo com internet.'
  }
  finally {
    enviando.value = false
  }
}

onMounted(carregar)
</script>

<template>
  <section class="tela">
    <TopoBloco escuro voltar="/painel/turno" rotulo-voltar="Voltar ao turno" :marca="emTurno ? 'Fechar turno' : 'Conferir estoque'" legenda="Confira com seu caixa">
      <template v-if="resumo">
        <div v-for="f in FORMAS" :key="f.id" class="linha"><span class="claro">{{ f.nome }}</span><span>{{ formatarBRL(resumo.por_forma[f.id]) }}</span></div>
        <div class="linha total"><span class="claro">Total do turno</span><span class="valor">{{ formatarBRL(resumo.total) }}</span></div>
        <span v-if="!resumo.turno" class="claro">Nenhum turno registrado ainda.</span>
      </template>
    </TopoBloco>

    <p v-if="fila.length" class="bloco erro" role="alert">Há {{ fila.length }} venda(s) do PDV ainda não enviadas. Conecte-se e espere enviar antes de fechar o caixa.</p>
    <p v-if="erro" class="bloco erro" role="alert">{{ erro }}</p>

    <section v-if="resumo" class="bloco contagem">
      <h2 class="bloco-titulo">Quantos sobraram de verdade?</h2>
      <p v-if="!resumo.produtos.length" class="dica">Nenhum produto ativo.</p>
      <div v-for="p in resumo.produtos" :key="p.produto_id" class="produto" :class="{ falta: diferenca(p) < 0 }">
        <div class="produto-linha">
          <span class="produto-txt">
            <span class="produto-nome">{{ p.nome }}</span>
            <span class="produto-info">
              sistema: {{ p.estoque }}<template v-if="p.vendidos"> · vendidos {{ p.vendidos }}</template>
              <template v-if="diferenca(p) < 0"> · faltam {{ -diferenca(p) }}</template>
              <template v-else-if="diferenca(p) > 0"> · sobram {{ diferenca(p) }}</template>
            </span>
          </span>
          <input v-model="contagens[p.produto_id]!.contagem" class="numero" :aria-label="`Contagem de ${p.nome}`" inputmode="numeric" maxlength="6">
        </div>

        <template v-if="diferenca(p) < 0">
          <label class="campo escuro">O que aconteceu com {{ -diferenca(p) === 1 ? 'a unidade' : `as ${-diferenca(p)}` }}?
            <select v-model="contagens[p.produto_id]!.motivo">
              <option value="" disabled>Escolha</option>
              <option v-for="mo in MOTIVOS" :key="mo.id" :value="mo.id">{{ mo.nome }}</option>
            </select>
          </label>
          <div v-if="contagens[p.produto_id]!.motivo === 'venda_nao_registrada'" class="dois">
            <label class="campo escuro">Preço unitário (R$)
              <input v-model="contagens[p.produto_id]!.preco" inputmode="decimal">
            </label>
            <label class="campo escuro">Pagamento
              <select v-model="contagens[p.produto_id]!.forma">
                <option v-for="f in FORMAS" :key="f.id" :value="f.id">{{ f.nome }}</option>
              </select>
            </label>
            <label v-if="contagens[p.produto_id]!.forma === 'cartao'" class="campo escuro inteiro">Cartão
              <select v-model="contagens[p.produto_id]!.metodo">
                <option value="debito">Débito</option>
                <option value="credito">Crédito</option>
              </select>
            </label>
          </div>
          <label v-if="contagens[p.produto_id]!.motivo === 'outro'" class="campo escuro">Observação
            <input v-model="contagens[p.produto_id]!.obs" maxlength="255">
          </label>
        </template>
      </div>
    </section>

    <div v-if="resumo" class="rodape">
      <p v-if="pendencias" class="dica nota">Falta informar {{ pendencias }} {{ pendencias === 1 ? 'item' : 'itens' }}.</p>
      <button class="btn btn-destaque btn-seta" :disabled="enviando || !online || !!fila.length || !!pendencias" @click="concluir">
        {{ enviando ? 'Salvando…' : emTurno ? 'Encerrar turno' : 'Salvar contagem' }}
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.linha { display: flex; justify-content: space-between; align-items: baseline; font-size: 14px; }
.claro { color: #A6A6A6; font-size: 14px; }
.total { padding-top: 12px; border-top: 1px solid #2A2A2A; }
.valor { font-size: 36px; font-weight: 600; color: var(--destaque); letter-spacing: -1px; }
.contagem { gap: 6px; }
.contagem .bloco-titulo { margin-bottom: 6px; }
.produto { background: var(--fundo); border-radius: 24px; padding: 8px 8px 8px 16px; display: flex; flex-direction: column; gap: 8px; }
.produto.falta { background: var(--preto); color: var(--sobre-preto); padding: 8px; }
.produto.falta .produto-linha { padding-left: 8px; }
.produto-linha { display: flex; align-items: center; gap: 12px; }
.produto-txt { flex: 1; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.produto-nome { font-weight: 600; font-size: 15px; }
.produto-info { font-size: 13px; color: var(--texto-3); }
.falta .produto-info { color: var(--destaque); font-weight: 600; }
.numero { width: 64px; height: 52px; border: 0; background: var(--card); color: var(--texto); font-size: 20px; font-weight: 600; text-align: center; }
.falta .numero { background: var(--destaque); color: var(--sobre-destaque); }
.campo.escuro { padding: 0 8px; color: #D6D6D6; }
.campo.escuro input, .campo.escuro select { background: #2A2A2A; color: #FFFFFF; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; padding-bottom: 8px; }
.dois .campo { padding: 0 8px; }
.dois input, .dois select { min-width: 0; }
.inteiro { grid-column: 1 / -1; }
.rodape { position: sticky; bottom: 88px; display: flex; flex-direction: column; gap: 8px; }
.nota { padding: 0 8px; }
</style>
