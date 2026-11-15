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
    <div class="grade">
      <button
        v-for="p in ativos"
        :key="p.id"
        class="sabor"
        :class="{ escolhido: carrinho.has(p.id), esgotado: disponivel(p.id) <= 0 }"
        :disabled="disponivel(p.id) <= 0"
        @click="somar(p.id, 1)"
      >
        <span class="sabor-nome">{{ p.nome }}</span>
        <span class="sabor-info">
          <template v-if="disponivel(p.id) > 0">{{ formatarBRL(p.preco) }} · restam {{ disponivel(p.id) }}</template>
          <template v-else>esgotado</template>
          <template v-if="reservado(p.id)"> ({{ reservado(p.id) }} reservados)</template>
        </span>
        <span v-if="carrinho.has(p.id)" class="qtd">{{ carrinho.get(p.id)!.quantidade }}</span>
      </button>
    </div>

    <p v-if="!ativos.length" class="dica vazio">
      Nenhum produto ativo. <NuxtLink to="/painel/produtos">Cadastrar produtos</NuxtLink>
    </p>
    <p v-else-if="!itens.length" class="dica vazio">Toque no sabor para somar.</p>

    <div class="rodape">
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
        <button class="passo" :aria-label="`Tirar um ${nome(id)}`" @click="somar(id, -1)">−</button>
        <span class="num">{{ item.quantidade }}</span>
        <button class="passo" :aria-label="`Adicionar um ${nome(id)}`" :disabled="item.quantidade >= disponivel(id)" @click="somar(id, 1)">+</button>
      </div>

      <div class="total">
        <span>Total</span>
        <strong>{{ formatarBRL(total) }}</strong>
      </div>

      <div class="formas">
        <button class="btn btn-escuro" :disabled="!total" @click="abrirPix">Pix</button>
        <button class="btn btn-escuro" :disabled="!total" @click="etapa = 'dinheiro'">Dinheiro</button>
        <button class="btn btn-escuro" :disabled="!total" @click="etapa = 'cartao'">Cartão</button>
      </div>
    </div>
    <p v-if="aviso" class="toast" role="status">{{ aviso }}</p>
  </section>

  <!-- Telas de cobrança -->
  <section v-else class="cobrar">
    <header class="cobrar-topo">
      <button v-if="etapa !== 'cartao-confirmar'" class="voltar" aria-label="Voltar para a venda" @click="etapa = 'carrinho'">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
      </button>
      <h1 class="titulo">
        {{ etapa === 'pix' ? 'Cobrar com Pix' : etapa === 'dinheiro' ? 'Cobrar em dinheiro' : 'Cobrar no cartão' }}
      </h1>
    </header>

    <div class="cobrar-meio">
      <span class="valor">{{ formatarBRL(total) }}</span>
      <span class="dica">{{ resumo }}</span>

      <template v-if="etapa === 'pix'">
        <img v-if="qrPix" :src="qrPix" alt="QR Code Pix com o valor da venda" class="qr">
        <p v-else-if="!pixConfigurado" class="erro">
          Cadastre a chave Pix, o nome e a cidade em <NuxtLink to="/painel/conta">Conta</NuxtLink>.
        </p>
        <p class="dica centro">Mostre a tela ao cliente. Confira no app do banco antes de confirmar.</p>
      </template>

      <p v-else-if="etapa === 'dinheiro'" class="dica centro">Recebeu o valor em dinheiro?</p>

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

    <div v-if="etapa !== 'cartao'" class="duas acoes">
      <button class="btn btn-contorno" @click="etapa === 'cartao-confirmar' ? limpar() : (etapa = 'carrinho')">
        {{ etapa === 'cartao-confirmar' ? 'Não' : 'Cancelar' }}
      </button>
      <button
        class="btn btn-destaque"
        :disabled="etapa === 'pix' && !qrPix"
        @click="registrar(etapa === 'pix' ? 'pix' : etapa === 'dinheiro' ? 'dinheiro' : 'cartao')"
      >
        {{ etapa === 'cartao-confirmar' ? 'Sim' : etapa === 'dinheiro' ? 'Recebido' : 'Pago' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.pdv { flex: 1; display: flex; flex-direction: column; }
.grade { padding: 16px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.sabor {
  position: relative; height: 128px; border: 0; padding: 14px;
  background: #DCDCDC; color: var(--texto);
  display: flex; flex-direction: column; justify-content: space-between; align-items: flex-start; text-align: left;
}
.sabor.escolhido { background: var(--destaque); }
.sabor.esgotado { background: #E4E4E4; color: #666666; border: 1px dashed #D0D0D0; }
.sabor-nome { font-size: 20px; font-weight: 900; text-transform: uppercase; padding-right: 36px; line-height: 1.1; }
.sabor-info { font-size: 14px; font-weight: 500; }
.qtd {
  position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border-radius: 18px;
  background: var(--fundo); color: var(--destaque-texto); font-size: 18px; font-weight: 900;
  display: flex; align-items: center; justify-content: center;
}
.vazio { padding: 0 16px; }
.rodape {
  margin-top: auto; padding: 16px; border-top: 1px solid var(--linha);
  display: flex; flex-direction: column; gap: 12px; background: var(--fundo);
  position: sticky; bottom: 64px;
}
.linha { display: flex; align-items: center; gap: 10px; }
.linha-nome { flex: 1; display: flex; flex-direction: column; gap: 2px; font-size: 16px; font-weight: 700; min-width: 0; }
.preco { font-size: 13px; font-weight: 500; color: var(--texto-2); display: flex; align-items: center; gap: 4px; }
.preco input { width: 64px; height: 28px; border: 0; background: var(--card); padding: 0 6px; font-size: 16px; border-radius: 8px; }
.passo { width: 48px; height: 48px; border: 0; background: #DCDCDC; font-size: 24px; }
.num { width: 28px; text-align: center; font-size: 20px; font-weight: 900; }
.total { display: flex; justify-content: space-between; align-items: baseline; font-size: 15px; color: var(--texto-2); }
.total strong { font-size: 32px; font-weight: 900; color: var(--texto); }
.formas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.formas .btn { min-height: 64px; padding: 0 4px; }
.toast {
  position: fixed; left: 16px; right: 16px; bottom: 96px; margin: 0; z-index: 10;
  background: var(--texto); color: #FFFFFF; padding: 14px; text-align: center; font-weight: 700; border-radius: var(--raio);
}

.cobrar { flex: 1; display: flex; flex-direction: column; }
.cobrar-topo { height: 64px; display: flex; align-items: center; gap: 8px; padding: 0 8px 0 16px; }
.voltar { width: 44px; height: 44px; margin-left: -8px; border: 0; background: none; display: flex; align-items: center; justify-content: center; }
.cobrar-meio { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 0 24px; }
.valor { font-size: 56px; font-weight: 900; }
.qr { width: 280px; height: 280px; border-radius: var(--raio-card); background: var(--card); padding: 12px; }
.centro { text-align: center; line-height: 1.5; font-size: 14px; }
.pergunta { margin: 0; font-size: 24px; font-weight: 900; }
.duas { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.duas .btn { min-height: 64px; }
.acoes { padding: 16px; }
.sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
