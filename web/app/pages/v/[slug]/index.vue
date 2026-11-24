<script setup lang="ts">
interface ProdutoPublico { id: number, nome: string, descricao: string | null, cor: string | null, foto_url: string | null, preco: number, disponivel: number }
interface VendedorPublico {
  nome: string
  slug: string
  descricao: string | null
  foto_url: string | null
  status: 'vendendo' | 'esgotado' | 'fora_de_atividade'
  taxa_entrega: number
  prazo_resposta_min: number
  produtos: ProdutoPublico[]
  paradas: (Parada & { latitude: number | null, longitude: number | null })[]
  localizacao: (Ponto & { atualizado_em: string }) | null
  atualizado_em: string
}

const slug = useRoute().params.slug as string
const { data: v, error, refresh } = await useFetch<VendedorPublico>(`/api/v1/publico/vendedores/${slug}`)
if (error.value?.statusCode === 404) throw createError({ statusCode: 404, statusMessage: 'Vendedor não encontrado', fatal: true })

useSeoMeta({
  title: () => v.value ? `${v.value.nome} · Banquinha` : 'Banquinha',
  description: () => v.value?.descricao ?? 'Veja o que tem agora, onde o vendedor está e reserve.',
  ogTitle: () => v.value?.nome,
  ogDescription: () => v.value?.descricao ?? 'Veja o que tem agora e reserve.',
})

const { qtd, definir, carregar, itens } = useCarrinho(slug)
const montado = ref(false)
const atual = ref(0)

const produtos = computed(() => v.value?.produtos ?? [])
const loc = computed(() => v.value?.localizacao ?? null)
const slide = computed(() => produtos.value[atual.value])
// Com alguma foto, o carrossel mostra a bolinha do protótipo em todos os sabores (layout não pula entre slides).
const temFotos = computed(() => produtos.value.some(p => p.foto_url))
const q = (id: number) => (montado.value ? qtd(id) : 0)
const contagem = computed(() => (montado.value ? produtos.value.reduce((n, p) => n + qtd(p.id), 0) : 0))
const total = computed(() => produtos.value.reduce((n, p) => n + q(p.id) * p.preco, 0))
const ir = (k: number) => { atual.value = (k + produtos.value.length) % produtos.value.length }
const rotuloStatus = computed(() => v.value?.status === 'esgotado' ? 'Esgotado hoje' : 'Vendendo agora')

function verSabor(k: number) {
  ir(k)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function somar(p: ProdutoPublico, d: number) {
  definir(p.id, Math.min(p.disponivel, Math.max(0, qtd(p.id) + d)))
}

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  carregar()
  montado.value = true
  timer = setInterval(() => refresh(), 30_000) // DECISOES_TECNICAS: consulta a cada ~30s
})
onBeforeUnmount(() => clearInterval(timer))

// Disponível pode cair enquanto a página está aberta: ajusta o carrinho.
watch(produtos, (lista) => {
  if (!montado.value) return
  for (const p of lista) if (qtd(p.id) > p.disponivel) definir(p.id, p.disponivel)
  for (const id of Object.keys(itens.value).map(Number)) if (!lista.some(p => p.id === id)) definir(id, 0)
})
</script>

<template>
  <main v-if="v" class="pagina-bloco">
    <!-- Fora de atividade -->
    <template v-if="v.status === 'fora_de_atividade'">
      <TopoBloco cinza voltar="/" rotulo-voltar="Voltar ao mapa" :marca="v.nome" legenda="offline" titulo="Ops! Fora de atividade">
        <span class="ops-sub">O vendedor não está vendendo agora. Veja onde ele vai estar.</span>
      </TopoBloco>
      <section v-if="v.paradas.length" class="bloco bloco-preto proxima">
        <h2 class="rot-claro">Próxima parada</h2>
        <span class="proxima-local">{{ v.paradas[0]!.local }}</span>
        <span class="proxima-quando">{{ quandoParada(v.paradas[0]!) }}</span>
      </section>
      <NuxtLink to="/" class="btn btn-destaque btn-seta outros">
        Ver outros vendedores
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </NuxtLink>
    </template>

    <template v-else>
      <section
        class="slide"
        aria-roledescription="carrossel"
        aria-label="Sabores de hoje"
        :style="{ background: slide ? corDoProduto(slide) : '#111111' }"
      >
        <header class="slide-topo">
          <NuxtLink to="/" class="voltar" aria-label="Voltar ao início"><IconeVoltar /></NuxtLink>
          <span class="topo-textos">
            <span class="topo-nome">{{ v.nome }}</span>
            <span class="topo-status"><span class="ponto" aria-hidden="true" />{{ rotuloStatus }}</span>
          </span>
          <NuxtLink :to="`/v/${slug}/reservar`" class="carrinho" :aria-label="`Reserva, ${contagem} itens`">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.5 11h11L21 8H7" /><circle cx="9" cy="19.5" r="1.5" /><circle cx="17" cy="19.5" r="1.5" /></svg>
            <span v-if="contagem" class="badge">{{ contagem }}</span>
          </NuxtLink>
        </header>

        <template v-if="slide">
          <div class="slide-meio" :class="{ 'com-foto': temFotos }">
            <button class="seta-slide" aria-label="Sabor anterior" :disabled="produtos.length < 2" @click="ir(atual - 1)">
              <IconeVoltar />
            </button>
            <div v-if="temFotos" class="bolinha-foto">
              <img v-if="slide.foto_url" :key="slide.id" :src="slide.foto_url" :alt="`Foto de ${slide.nome}`">
            </div>
            <div v-else class="slide-textos" aria-live="polite">
              <h1 class="slide-nome">{{ slide.nome }}</h1>
              <span v-if="slide.descricao" class="slide-desc">{{ slide.descricao }}</span>
            </div>
            <button class="seta-slide virar" aria-label="Próximo sabor" :disabled="produtos.length < 2" @click="ir(atual + 1)">
              <IconeVoltar />
            </button>
          </div>
          <div v-if="temFotos" class="slide-textos abaixo" aria-live="polite">
            <h1 class="slide-nome">{{ slide.nome }}</h1>
            <span v-if="slide.descricao" class="slide-desc">{{ slide.descricao }}</span>
          </div>

          <div class="slide-rodape">
            <div class="slide-preco">
              <span class="preco">{{ formatarBRL(slide.preco) }}</span>
              <span class="estoque">{{ slide.disponivel ? `restam ${slide.disponivel}` : 'acabou por hoje' }}</span>
            </div>
            <div v-if="slide.disponivel" class="stepper">
              <button class="menos" :aria-label="`Tirar ${slide.nome} da reserva`" :disabled="!q(slide.id)" @click="somar(slide, -1)">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12h14" /></svg>
              </button>
              <span aria-live="polite">{{ q(slide.id) }}</span>
              <button class="mais" :aria-label="`Adicionar ${slide.nome} à reserva`" :disabled="q(slide.id) >= slide.disponivel" @click="somar(slide, 1)">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
              </button>
            </div>
            <span v-else class="esgotado">Esgotado hoje</span>
          </div>

          <div class="pontos">
            <button
              v-for="(p, k) in produtos"
              :key="p.id"
              :aria-label="`Ver ${p.nome}`"
              :aria-current="k === atual"
              @click="ir(k)"
            >
              <span :class="{ atual: k === atual }" />
            </button>
          </div>
        </template>
        <p v-else class="vazio">Nenhum sabor cadastrado.</p>
      </section>

      <div v-if="contagem" class="bloco bloco-preto resumo">
        <div class="resumo-topo">
          <span class="resumo-rotulo">Sua reserva</span>
          <span class="pilula">Rascunho</span>
        </div>
        <span class="resumo-total">{{ contagem }} {{ contagem === 1 ? 'item' : 'itens' }} · {{ formatarBRL(total) }}</span>
        <div class="unidades" aria-hidden="true">
          <template v-for="p in produtos" :key="p.id">
            <span v-for="n in q(p.id)" :key="n" :style="{ background: corDoProduto(p) }" />
          </template>
        </div>
      </div>

      <section v-if="loc" class="bloco">
        <div class="parada">
          <span class="icone-parada"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg></span>
          <span class="parada-textos">
            <span class="parada-nome">Onde está agora</span>
            <span class="dica">atualizado {{ haQuantoTempo(loc.atualizado_em) }}</span>
          </span>
        </div>
        <MiniMapa :latitude="loc.latitude" :longitude="loc.longitude" :foto="v.foto_url" />
      </section>

      <section v-if="produtos.length" class="secao">
        <h2 class="titulo-secao">Sabores</h2>
        <div class="cards">
          <button
            v-for="(p, k) in produtos"
            :key="p.id"
            class="card"
            :class="{ selecionado: k === atual, fora: !p.disponivel }"
            :aria-label="`Ver ${p.nome}`"
            @click="verSabor(k)"
          >
            <span class="card-topo">
              <span class="card-seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
              <span class="amostra" :style="{ background: corDoProduto(p) }" />
            </span>
            <span class="card-nome">{{ p.nome }}</span>
            <span class="card-preco">{{ formatarBRL(p.preco) }}</span>
            <span class="card-estoque">{{ !p.disponivel ? 'Esgotado' : q(p.id) ? `${q(p.id)} na reserva` : `restam ${p.disponivel}` }}</span>
          </button>
        </div>
      </section>
    </template>

    <section v-if="v.status !== 'fora_de_atividade' && v.paradas.length" class="bloco">
      <h2 class="rotulo-secao">Próximas paradas</h2>
      <div v-for="p in v.paradas" :key="p.id" class="parada">
        <span class="icone-parada"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg></span>
        <span class="parada-textos"><span class="parada-nome">{{ p.local }}</span><span class="dica">{{ quandoParada(p) }}</span></span>
      </div>
    </section>

    <section v-if="v.descricao && v.status !== 'fora_de_atividade'" class="bloco">
      <h2 class="rotulo-secao">Sobre</h2>
      <p class="sobre">{{ v.descricao }}</p>
    </section>

    <NuxtLink to="/pedidos" class="bloco link-pedidos">
      <span class="parada-nome">Meus pedidos</span>
      <span class="seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
    </NuxtLink>

    <!-- Só aparece com itens: vazio, o botão flutuante cobria o conteúdo sem servir para nada. -->
    <div v-if="v.status !== 'fora_de_atividade' && contagem" class="cta">
      <NuxtLink :to="`/v/${slug}/reservar`" class="reservar">
        <span class="reservar-texto">Reservar · {{ contagem }} {{ contagem === 1 ? 'item' : 'itens' }}</span>
        <span>{{ formatarBRL(total) }}</span>
        <span class="reservar-seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </NuxtLink>
    </div>
  </main>
  <main v-else class="pagina-bloco falha">
    <p class="erro" role="alert">Não foi possível carregar o vendedor agora.</p>
    <button class="btn btn-cinza" @click="refresh()">Tentar de novo</button>
  </main>
</template>

<style scoped>

/* Carrossel: bloco inteiro na cor do sabor. */
.slide { color: #FFFFFF; min-height: 72dvh; border-radius: var(--raio-card); padding: 0 16px 8px; display: flex; flex-direction: column; gap: 18px; transition: background .3s; }
.slide-topo { height: 64px; display: flex; align-items: center; gap: 10px; margin-right: -10px; }
.voltar { width: 40px; height: 40px; border-radius: 50%; background: rgba(0, 0, 0, .22); color: #FFFFFF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.topo-textos { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.topo-nome { font-size: 15px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topo-status { font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 5px; }
.ponto { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.carrinho { width: 52px; height: 52px; border-radius: 50%; background: #FFFFFF; color: #111111; display: flex; align-items: center; justify-content: center; position: relative; flex-shrink: 0; }
.badge {
  position: absolute; top: -2px; right: -2px; background: var(--destaque); color: var(--sobre-destaque); font-size: 11px; font-weight: 700;
  border-radius: 10px; min-width: 20px; height: 20px; padding: 0 4px; display: flex; align-items: center; justify-content: center;
}
.slide-meio { flex: 1; display: flex; align-items: center; gap: 4px; }
.slide-meio.com-foto { flex: 0 0 auto; justify-content: space-between; }
.seta-slide { width: 44px; height: 44px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, .18); color: #FFFFFF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.seta-slide:disabled { opacity: .3; }
.virar { transform: scaleX(-1); }
.bolinha-foto { width: min(220px, 56vw); aspect-ratio: 1; border-radius: 50%; overflow: hidden; background: rgba(0, 0, 0, .18); }
.bolinha-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
.slide-textos { flex: 1; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.slide-textos.abaixo { flex: 1; }
.slide-nome { margin: 0; font-size: clamp(36px, 11vw, 56px); font-weight: 600; line-height: 1; letter-spacing: -1.5px; overflow-wrap: anywhere; }
.slide-desc { font-size: 16px; line-height: 1.3; opacity: .88; }
.slide-rodape { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.slide-preco { display: flex; flex-direction: column; gap: 2px; }
.preco { font-size: 30px; font-weight: 600; letter-spacing: -0.5px; }
.estoque { font-size: 13px; font-weight: 500; opacity: .88; }
.stepper { background: rgba(0, 0, 0, .22); border-radius: var(--pilula); padding: 6px; display: flex; align-items: center; gap: 4px; }
.stepper button { width: 48px; height: 48px; border: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.stepper .menos { background: rgba(255, 255, 255, .18); color: #FFFFFF; }
.stepper .mais { background: #FFFFFF; color: #111111; }
.stepper button:disabled { opacity: .4; }
.stepper span { min-width: 30px; text-align: center; font-size: 22px; font-weight: 600; }
.esgotado { background: rgba(0, 0, 0, .3); border-radius: var(--pilula); padding: 10px 16px; font-weight: 600; font-size: 13px; }
.pontos { display: flex; justify-content: center; gap: 2px; }
.pontos button { width: 32px; height: 32px; border: 0; background: none; color: inherit; display: flex; align-items: center; justify-content: center; }
.pontos span { width: 8px; height: 8px; border-radius: 4px; background: currentColor; opacity: .5; }
.pontos span.atual { width: 22px; opacity: 1; }
.vazio { padding: 24px 0; font-size: 16px; }

/* Resumo preto da reserva. */
.resumo { gap: 14px; padding: 18px 18px 20px; }
.resumo-topo { display: flex; justify-content: space-between; align-items: center; }
.resumo-rotulo { font-size: 13px; color: #A6A6A6; }
.resumo-topo .pilula { align-self: auto; }
.resumo-total { font-size: 28px; font-weight: 600; letter-spacing: -0.5px; }
.unidades { display: flex; gap: 4px; }
.unidades span { flex: 1; height: 6px; border-radius: 3px; }

.parada { display: flex; align-items: center; gap: 12px; }
.icone-parada { width: 44px; height: 44px; border-radius: 50%; background: var(--fundo); color: var(--destaque-texto); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.parada-textos { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.parada-nome { font-size: 17px; font-weight: 600; }

.secao { display: flex; flex-direction: column; gap: 8px; }
.titulo-secao { margin: 8px 8px 4px; font-size: 26px; font-weight: 600; letter-spacing: -0.5px; }
.cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.card {
  min-height: 172px; padding: 14px; background: var(--card); border-radius: var(--raio-card); border: 2px solid transparent;
  display: flex; flex-direction: column; align-items: flex-start; gap: 4px; text-align: left;
}
.card.selecionado { border-color: var(--texto); }
.card.fora { opacity: .55; }
.card-topo { display: flex; justify-content: space-between; width: 100%; }
.card-seta { width: 40px; height: 40px; border-radius: 50%; background: var(--fundo); display: flex; align-items: center; justify-content: center; }
.amostra { width: 40px; height: 40px; border-radius: 50%; }
.card-nome { margin-top: auto; font-size: 17px; font-weight: 600; line-height: 1.15; }
.card-preco { font-size: 14px; color: var(--texto-2); }
.card-estoque { font-size: 12px; font-weight: 600; color: var(--texto-3); }

.sobre { margin: 0; line-height: 1.5; color: var(--texto-2); }
.link-pedidos { flex-direction: row; align-items: center; justify-content: space-between; padding: 10px 10px 10px 18px; text-decoration: none; }

.ops-sub { font-size: 16px; line-height: 1.4; color: var(--texto-2); }
.proxima { gap: 10px; }
.rot-claro { margin: 0; font-size: 13px; font-weight: 500; color: #A6A6A6; }
.proxima-local { font-size: 28px; font-weight: 600; letter-spacing: -0.5px; line-height: 1.1; }
.proxima-quando { font-size: 15px; color: #D6D6D6; }
.outros { order: 99; margin-top: auto; }

/* Botão flutuante de reservar. */
.cta { position: sticky; bottom: 0; margin-top: auto; padding-bottom: env(safe-area-inset-bottom); }
.reservar {
  height: 64px; border-radius: var(--pilula); background: var(--destaque); color: var(--sobre-destaque); text-decoration: none;
  display: flex; align-items: center; gap: 12px; padding: 0 8px 0 24px; font-weight: 600; font-size: 16px; box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.reservar-texto { flex: 1; }
.reservar-seta { width: 48px; height: 48px; border-radius: 50%; background: #111111; color: var(--destaque); display: flex; align-items: center; justify-content: center; }
.falha { padding: 48px 16px; gap: 16px; }
</style>
