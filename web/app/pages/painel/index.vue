<script setup lang="ts">
import QRCode from 'qrcode'

definePageMeta({ layout: 'painel' })

type Etapa = 'carrinho' | 'pix' | 'dinheiro' | 'cartao' | 'cartao-confirmar'
interface Item { quantidade: number, preco: number }
interface CobrancaCartao { id_local: string, metodo: 'credito' | 'debito', itens: [number, Item][] }

const CHAVE_COBRANCA = 'banquinha:cobranca-cartao'

const { vendedor } = useVendedor()
const { produtos, carregar } = useProdutos()
const { adicionar, pendentesPorProduto } = useFilaVendas()

const carrinho = ref(new Map<number, Item>())
const etapa = ref<Etapa>('carrinho')
const metodoCartao = ref<'credito' | 'debito'>('debito')
const idLocal = ref('')
const qrPix = ref('')
const aviso = ref('')

const ativos = computed(() => produtos.value.filter(p => p.ativo))
const reservado = (id: number) => produtos.value.find(p => p.id === id)?.reservado ?? 0
// Regra de estoque 4: não vender o pão de quem reservou.
const disponivel = (id: number) => (produtos.value.find(p => p.id === id)?.estoque ?? 0) - reservado(id) - (pendentesPorProduto.value.get(id) ?? 0)
const nome = (id: number) => produtos.value.find(p => p.id === id)?.nome ?? ''
const itens = computed(() => [...carrinho.value.entries()])
const total = computed(() => itens.value.reduce((s, [, i]) => s + i.quantidade * i.preco, 0))
const resumo = computed(() => itens.value.map(([id, i]) => `${i.quantidade}× ${nome(id)}`).join(' · '))
const pixConfigurado = computed(() => !!(vendedor.value?.chave_pix && vendedor.value.nome_recebedor_pix && vendedor.value.cidade_recebedor_pix))
const infinitepay = computed(() => vendedor.value?.provedor_cartao === 'infinitepay')

function somar(id: number, delta: number) {
  const p = produtos.value.find(x => x.id === id)
  if (!p) return
  const atual = carrinho.value.get(id)
  const qtd = (atual?.quantidade ?? 0) + delta
  if (qtd > disponivel(id)) return
  if (qtd <= 0) carrinho.value.delete(id)
  else carrinho.value.set(id, { quantidade: qtd, preco: atual?.preco ?? p.preco })
}

function mudarPreco(id: number, texto: string) {
  const item = carrinho.value.get(id)
  const c = centavosDeTexto(texto)
  if (item && c !== null) item.preco = c
}

function limpar() {
  carrinho.value = new Map()
  etapa.value = 'carrinho'
  localStorage.removeItem(CHAVE_COBRANCA)
}

async function registrar(forma: 'pix' | 'dinheiro' | 'cartao') {
  await adicionar({
    id_local: idLocal.value || novoUUID(),
    forma_pagamento: forma,
    ...(forma === 'cartao' ? { metodo_cartao: metodoCartao.value } : {}),
    pago_em: new Date().toISOString(),
    itens: itens.value.map(([produto_id, i]) => ({ produto_id, quantidade: i.quantidade, preco_unitario: i.preco })),
  })
  idLocal.value = ''
  limpar()
  aviso.value = 'Venda registrada'
  setTimeout(() => { aviso.value = '' }, 2000)
}

async function abrirPix() {
  etapa.value = 'pix'
  qrPix.value = ''
  if (!pixConfigurado.value) return
  const v = vendedor.value!
  const br = gerarBRCode({ chave: v.chave_pix!, nome: v.nome_recebedor_pix!, cidade: v.cidade_recebedor_pix!, valorCentavos: total.value })
  qrPix.value = await QRCode.toDataURL(br, { margin: 1, width: 560, errorCorrectionLevel: 'M' })
}

function cobrarCartao(metodo: 'credito' | 'debito') {
  metodoCartao.value = metodo
  idLocal.value = novoUUID()
  etapa.value = 'cartao-confirmar'
  // Guarda a cobrança: no iPhone o painel pode ser recarregado ao voltar do app da InfinitePay.
  const cobranca: CobrancaCartao = { id_local: idLocal.value, metodo, itens: itens.value.map(([id, i]) => [id, { ...i }]) }
  localStorage.setItem(CHAVE_COBRANCA, JSON.stringify(cobranca))

  if (infinitepay.value) {
    // D24: sem retorno automático; na volta o vendedor responde "Pagamento concluído?".
    const q = new URLSearchParams({
      amount: String(total.value),
      payment_method: metodo === 'credito' ? 'credit' : 'debit',
      order_id: idLocal.value,
      result_url: `${location.origin}/painel`,
      app_client_referrer: 'Banquinha',
      af_force_deeplink: 'true',
    })
    location.href = `infinitepaydash://infinitetap-app?${q}`
  }
}

onMounted(async () => {
  try {
    const salva: CobrancaCartao | null = JSON.parse(localStorage.getItem(CHAVE_COBRANCA) ?? 'null')
    if (salva) {
      carrinho.value = new Map(salva.itens)
      idLocal.value = salva.id_local
      metodoCartao.value = salva.metodo
      etapa.value = 'cartao-confirmar'
    }
  }
  catch {}
  await carregar().catch(() => {})
})
</script>

<template>
  <!-- Carrinho -->
  <section v-if="etapa === 'carrinho'" class="pdv">
    <div class="bloco cab">
      <h1 class="titulo-pagina">Nova venda</h1>
      <span class="dica">{{ ativos.length && !itens.length ? 'Toque no sabor para somar.' : resumo }}</span>
    </div>

    <div class="grade">
      <button
        v-for="p in ativos"
        :key="p.id"
        class="sabor"
        :class="{ escolhido: carrinho.has(p.id), esgotado: disponivel(p.id) <= 0 }"
        :disabled="disponivel(p.id) <= 0"
        @click="somar(p.id, 1)"
      >
        <span class="sabor-topo">
          <span class="amostra" :style="{ background: corDoProduto(p) }" />
          <span v-if="carrinho.has(p.id)" class="qtd">{{ carrinho.get(p.id)!.quantidade }}</span>
        </span>
        <span class="sabor-textos">
          <span class="sabor-nome">{{ p.nome }}</span>
          <span class="sabor-info">
            <template v-if="disponivel(p.id) > 0">{{ formatarBRL(p.preco) }} · restam {{ disponivel(p.id) }}</template>
            <template v-else>esgotado</template>
            <template v-if="reservado(p.id)"> ({{ reservado(p.id) }} reservados)</template>
          </span>
        </span>
      </button>
    </div>

    <p v-if="!ativos.length" class="bloco dica">
      Nenhum produto ativo. <NuxtLink to="/painel/produtos">Cadastrar produtos</NuxtLink>
    </p>

    <div class="bloco bloco-preto rodape">
      <div v-for="[id, item] in itens" :key="id" class="linha">
        <div class="linha-nome">
          <span>{{ nome(id) }}</span>
          <label class="preco">
            <span class="sr">Preço de {{ nome(id) }}</span>R$
            <input
              :value="textoDeCentavos(item.preco)"
              inputmode="decimal"
              @change="mudarPreco(id, ($event.target as HTMLInputElement).value)"
            >
            cada
          </label>
        </div>
        <div class="passos">
          <button class="menos" :aria-label="`Tirar um ${nome(id)}`" @click="somar(id, -1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12h14" /></svg></button>
          <span class="num">{{ item.quantidade }}</span>
          <button class="mais" :aria-label="`Adicionar um ${nome(id)}`" :disabled="item.quantidade >= disponivel(id)" @click="somar(id, 1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg></button>
        </div>
      </div>

      <div class="total">
        <span>Total</span>
        <strong>{{ formatarBRL(total) }}</strong>
      </div>

      <div class="formas">
        <button class="btn btn-destaque" :disabled="!total" @click="abrirPix">Pix</button>
        <button class="btn forma" :disabled="!total" @click="etapa = 'dinheiro'">Dinheiro</button>
        <button class="btn forma" :disabled="!total" @click="etapa = 'cartao'">Cartão</button>
      </div>
    </div>
    <p v-if="aviso" class="toast" role="status">{{ aviso }}</p>
  </section>

  <!-- Telas de cobrança -->
  <section v-else class="cobrar">
    <TopoBloco
      escuro
      :sem-voltar="etapa === 'cartao-confirmar'"
      :ao-voltar="() => { etapa = 'carrinho' }"
      rotulo-voltar="Voltar para a venda"
      :marca="etapa === 'pix' ? 'Cobrar com Pix' : etapa === 'dinheiro' ? 'Cobrar em dinheiro' : 'Cobrar no cartão'"
      :legenda="resumo"
    >
      <span class="rotulo-claro">Valor</span>
      <span class="valor">{{ formatarBRL(total) }}</span>
    </TopoBloco>

    <div class="bloco cobrar-meio">
      <template v-if="etapa === 'pix'">
        <img v-if="qrPix" :src="qrPix" alt="QR Code Pix com o valor da venda" class="qr">
        <p v-else-if="!pixConfigurado" class="erro">
          Cadastre a chave Pix, o nome e a cidade em <NuxtLink to="/painel/conta">Conta</NuxtLink>.
        </p>
        <p class="dica centro">Mostre a tela ao cliente. Confira no app do banco antes de confirmar.</p>
      </template>

      <p v-else-if="etapa === 'dinheiro'" class="pergunta">Recebeu o valor em dinheiro?</p>

      <template v-else-if="etapa === 'cartao'">
        <p class="dica centro">
          {{ infinitepay ? 'O app da InfinitePay abre com o valor.' : 'Cobre na sua maquininha e confirme aqui.' }}
        </p>
        <div class="duas">
          <button class="btn btn-escuro" @click="cobrarCartao('debito')">Débito</button>
          <button class="btn btn-escuro" @click="cobrarCartao('credito')">Crédito</button>
        </div>
      </template>

      <template v-else>
        <p class="pergunta">Pagamento concluído?</p>
        <p class="dica centro">{{ metodoCartao === 'credito' ? 'Crédito' : 'Débito' }}</p>
      </template>
    </div>

    <div v-if="etapa !== 'cartao'" class="acoes">
      <button class="btn btn-branco" @click="etapa === 'cartao-confirmar' ? limpar() : (etapa = 'carrinho')">
        {{ etapa === 'cartao-confirmar' ? 'Não' : 'Cancelar' }}
      </button>
      <button
        class="btn btn-destaque btn-seta confirmar"
        :disabled="etapa === 'pix' && !qrPix"
        @click="registrar(etapa === 'pix' ? 'pix' : etapa === 'dinheiro' ? 'dinheiro' : 'cartao')"
      >
        {{ etapa === 'cartao-confirmar' ? 'Sim' : etapa === 'dinheiro' ? 'Recebido' : 'Pago' }}
        <span class="bolinha"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.pdv { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.cab { gap: 4px; }
.titulo-pagina { margin: 0; font-size: 32px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; }
.grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.sabor {
  height: 128px; border: 0; border-radius: var(--raio-card); padding: 14px;
  background: var(--card); color: var(--texto);
  display: flex; flex-direction: column; justify-content: space-between; align-items: flex-start; text-align: left;
}
.sabor.escolhido { background: var(--preto); color: var(--sobre-preto); }
.sabor.esgotado { background: none; color: var(--texto-3); border: 1px dashed var(--apagado); }
.sabor.esgotado .amostra { opacity: .4; }
.sabor-topo { display: flex; justify-content: space-between; width: 100%; }
.amostra { width: 32px; height: 32px; border-radius: 50%; }
.qtd { width: 36px; height: 36px; border-radius: 50%; background: var(--destaque); color: var(--sobre-destaque); font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.sabor-textos { display: flex; flex-direction: column; gap: 2px; }
.sabor-nome { font-size: 18px; font-weight: 600; line-height: 1.1; }
.sabor-info { font-size: 13px; opacity: .75; }
.rodape { margin-top: auto; position: sticky; bottom: 88px; gap: 14px; }
.linha { display: flex; align-items: center; gap: 10px; }
.linha-nome { flex: 1; display: flex; flex-direction: column; gap: 2px; font-size: 16px; font-weight: 500; min-width: 0; }
.preco { font-size: 13px; color: #A6A6A6; display: flex; align-items: center; gap: 4px; }
.preco input { width: 72px; height: 32px; border: 0; background: #2A2A2A; color: #FFFFFF; padding: 0 10px; font-size: 16px; }
.passos { background: #2A2A2A; border-radius: var(--pilula); padding: 4px; display: flex; align-items: center; gap: 4px; }
.passos button { width: 44px; height: 44px; border: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.passos button:disabled { opacity: .4; }
.menos { background: #3A3A3A; color: #FFFFFF; }
.mais { background: #FFFFFF; color: #111111; }
.num { width: 26px; text-align: center; font-size: 18px; font-weight: 600; }
.total { display: flex; justify-content: space-between; align-items: baseline; padding-top: 12px; border-top: 1px solid #2A2A2A; font-size: 14px; color: #A6A6A6; }
.linha + .total, .total:first-child { border-top: 0; padding-top: 0; }
.total strong { font-size: 34px; font-weight: 600; color: var(--destaque); letter-spacing: -0.5px; }
.formas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
.formas .btn { min-height: 56px; padding: 0 4px; font-size: 15px; }
.forma { background: #2A2A2A; color: #FFFFFF; }
.toast {
  position: fixed; left: 16px; right: 16px; bottom: 100px; margin: 0; z-index: 10;
  background: var(--preto); color: var(--sobre-preto); padding: 16px; text-align: center; font-weight: 600; border-radius: var(--pilula);
}

.cobrar { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.rotulo-claro { font-size: 13px; color: #A6A6A6; }
.valor { font-size: 56px; font-weight: 600; color: var(--destaque); letter-spacing: -2px; line-height: 1; }
.cobrar-meio { flex: 1; align-items: center; justify-content: center; gap: 16px; padding: 20px; }
.qr { width: 270px; height: 270px; border-radius: 24px; background: #FFFFFF; padding: 12px; }
.centro { text-align: center; line-height: 1.5; font-size: 14px; }
.pergunta { margin: 0; font-size: 24px; font-weight: 600; text-align: center; }
.duas { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.duas .btn { min-height: 64px; }
.acoes { display: flex; gap: 8px; }
.acoes .btn { min-height: 64px; flex: 1; }
.acoes .confirmar { flex: 2; }
.btn-branco { background: var(--card); color: var(--texto); }
</style>
