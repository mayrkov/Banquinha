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
  <main v-if="v" class="pagina">
    <!-- Fora de atividade -->
    <template v-if="v.status === 'fora_de_atividade'">
      <header class="topo-cliente">
        <NuxtLink to="/" class="icone-btn" aria-label="Voltar ao início"><IconeVoltar /></NuxtLink>
        <span class="marca">{{ v.nome }}</span>
        <span class="icone-btn" />
      </header>
      <div class="ops">
        <h1 class="titulo-grande">Ops!</h1>
        <p class="subtitulo">O vendedor não está em atividade no momento</p>
      </div>
      <section v-if="v.paradas.length" class="secao">
        <h2 class="rotulo-secao">Próxima parada</h2>
        <div class="parada-grande">
          <span class="parada-local">{{ v.paradas[0]!.local }}</span>
          <span class="subtitulo">{{ quandoParada(v.paradas[0]!) }}</span>
        </div>
      </section>
    </template>

    <template v-else>
      <section
        class="slide"
        aria-roledescription="carrossel"
        aria-label="Sabores de hoje"
        :style="{ background: slide ? corDoProduto(slide) : '#111111' }"
      >
        <header class="topo-cliente">
          <NuxtLink to="/" class="icone-btn" aria-label="Voltar ao início"><IconeVoltar /></NuxtLink>
          <span class="marca">{{ v.nome }}</span>
          <NuxtLink :to="`/v/${slug}/reservar`" class="icone-btn" :aria-label="`Reserva, ${contagem} itens`">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.5 11h11L21 8H7" /><circle cx="9" cy="19.5" r="1.5" /><circle cx="17" cy="19.5" r="1.5" /></svg>
            <span v-if="contagem" class="badge">{{ contagem }}</span>
          </NuxtLink>
        </header>

        <p class="status"><span class="ponto" aria-hidden="true" />{{ rotuloStatus }}</p>

        <template v-if="slide">
          <div class="slide-meio" :class="{ 'com-foto': temFotos }">
            <button class="icone-btn" aria-label="Sabor anterior" :disabled="produtos.length < 2" @click="ir(atual - 1)">
              <IconeVoltar />
            </button>
            <div v-if="temFotos" class="bolinha-foto">
              <img v-if="slide.foto_url" :key="slide.id" :src="slide.foto_url" :alt="`Foto de ${slide.nome}`">
            </div>
            <div v-else class="slide-textos" aria-live="polite">
              <h1 class="slide-nome">{{ slide.nome }}</h1>
              <span v-if="slide.descricao" class="subtitulo">{{ slide.descricao }}</span>
            </div>
            <button class="icone-btn virar" aria-label="Próximo sabor" :disabled="produtos.length < 2" @click="ir(atual + 1)">
              <IconeVoltar />
            </button>
          </div>
          <div v-if="temFotos" class="slide-textos abaixo" aria-live="polite">
            <h1 class="slide-nome">{{ slide.nome }}</h1>
            <span v-if="slide.descricao" class="subtitulo">{{ slide.descricao }}</span>
          </div>

          <div class="slide-rodape">
            <div class="slide-preco">
              <span class="preco">{{ formatarBRL(slide.preco) }}</span>
              <span class="estoque">{{ slide.disponivel ? `restam ${slide.disponivel}` : 'acabou por hoje' }}</span>
            </div>
            <div v-if="slide.disponivel" class="stepper">
              <button :aria-label="`Tirar ${slide.nome} da reserva`" :disabled="!q(slide.id)" @click="somar(slide, -1)">−</button>
              <span aria-live="polite">{{ q(slide.id) }}</span>
              <button :aria-label="`Adicionar ${slide.nome} à reserva`" :disabled="q(slide.id) >= slide.disponivel" @click="somar(slide, 1)">+</button>
            </div>
            <span v-else class="esgotado">ESGOTADO</span>
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
        <p v-else class="subtitulo vazio">Nenhum sabor cadastrado.</p>
      </section>

      <section v-if="loc" class="secao">
        <p class="onde">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="stroke: var(--destaque-texto)" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
          Localização atualizada {{ haQuantoTempo(loc.atualizado_em) }}
        </p>
        <MiniMapa :latitude="loc.latitude" :longitude="loc.longitude" :foto="v.foto_url" />
      </section>

      <section v-if="produtos.length" class="secao">
        <h2 class="titulo-grande menor">Sabores</h2>
        <div class="cards">
          <button
            v-for="(p, k) in produtos"
            :key="p.id"
            class="card"
            :class="{ selecionado: k === atual, fora: !p.disponivel }"
            :aria-label="`Ver ${p.nome}`"
            @click="verSabor(k)"
          >
            <span class="amostra" :style="{ background: corDoProduto(p) }" />
            <span class="card-nome">{{ p.nome }}</span>
            <span class="card-preco">{{ formatarBRL(p.preco) }}</span>
            <span class="card-estoque">{{ !p.disponivel ? 'ESGOTADO' : q(p.id) ? `${q(p.id)} NA RESERVA` : `RESTAM ${p.disponivel}` }}</span>
          </button>
        </div>
      </section>
    </template>

    <section v-if="v.status !== 'fora_de_atividade' && v.paradas.length" class="secao">
      <h2 class="rotulo-secao">Próximas paradas</h2>
      <div v-for="p in v.paradas" :key="p.id" class="parada">
        <span class="parada-nome">{{ p.local }}</span>
        <span class="dica">{{ quandoParada(p) }}</span>
      </div>
    </section>

    <section v-if="v.descricao && v.status !== 'fora_de_atividade'" class="secao">
      <h2 class="rotulo-secao">Sobre</h2>
      <p class="sobre">{{ v.descricao }}</p>
    </section>

    <p class="secao dica">
      <NuxtLink to="/pedidos">Meus pedidos</NuxtLink>
    </p>

    <div v-if="v.status !== 'fora_de_atividade'" class="barra-fixa">
      <NuxtLink
        :to="contagem ? `/v/${slug}/reservar` : ''"
        class="btn btn-destaque btn-bloco reservar"
        :class="{ off: !contagem }"
        :aria-disabled="!contagem"
      >
        <span>Reservar · {{ contagem }} {{ contagem === 1 ? 'item' : 'itens' }}</span>
        <span>{{ formatarBRL(total) }}</span>
      </NuxtLink>
    </div>
  </main>
  <main v-else class="pagina falha">
    <p class="erro" role="alert">Não foi possível carregar o vendedor agora.</p>
    <button class="btn btn-cinza" @click="refresh()">Tentar de novo</button>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; display: flex; flex-direction: column; max-width: 640px; margin: 0 auto; }
.slide { color: #FFFFFF; min-height: 72dvh; display: flex; flex-direction: column; transition: background .3s; }
.badge {
  position: absolute; top: 4px; right: 2px; background: #FFFFFF; color: #0A0A0A; font-size: 11px; font-weight: 900;
  border-radius: 9px; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;
}
.status { margin: 4px 24px 0; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; }
.ponto { width: 8px; height: 8px; border-radius: 4px; background: currentColor; }
.slide-meio { flex: 1; display: flex; align-items: center; gap: 4px; padding: 24px 4px; }
.slide-meio .icone-btn:disabled { opacity: .3; }
.slide-meio.com-foto { flex: 0 0 auto; justify-content: space-between; padding: 16px 4px 8px; }
.bolinha-foto { width: min(230px, 58vw); aspect-ratio: 1; border-radius: 50%; overflow: hidden; background: rgba(0, 0, 0, .18); }
.bolinha-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
.slide-textos.abaixo { flex: 1; padding: 8px 24px 20px; }
.virar { transform: scaleX(-1); }
.slide-textos { flex: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.slide-nome { margin: 0; font-size: clamp(40px, 13vw, 64px); font-weight: 900; line-height: .95; text-transform: uppercase; overflow-wrap: anywhere; }
.slide-rodape { padding: 0 24px; display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
.slide-preco { display: flex; flex-direction: column; gap: 4px; }
.preco { font-size: 30px; }
.estoque { font-size: 14px; font-weight: 500; }
.stepper { display: flex; align-items: center; gap: 8px; }
.stepper button {
  width: 56px; height: 56px; border-radius: 18px; border: 0; background: rgba(255, 255, 255, .18); color: inherit; font-size: 28px; font-weight: 900;
}
.stepper button:disabled { opacity: .4; }
.stepper span { min-width: 32px; text-align: center; font-size: 26px; font-weight: 900; }
.esgotado { background: rgba(0, 0, 0, .25); border-radius: 999px; padding: 10px 16px; font-weight: 900; letter-spacing: 1.5px; font-size: 13px; }
.pontos { display: flex; justify-content: center; gap: 4px; padding: 20px 0; }
.pontos button { width: 32px; height: 32px; border: 0; background: none; color: inherit; display: flex; align-items: center; justify-content: center; }
.pontos span { width: 8px; height: 8px; border-radius: 4px; background: currentColor; }
.pontos span.atual { width: 18px; height: 18px; border-radius: 9px; border: 2px solid currentColor; background: radial-gradient(currentColor 3px, transparent 4px); }
.vazio { padding: 24px; }
.secao { padding: 32px 16px 0; display: flex; flex-direction: column; gap: 12px; margin: 0; }
.menor { font-size: 34px; }
.onde { margin: 0; display: flex; align-items: center; gap: 8px; color: var(--texto-2); font-size: 15px; }
.cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.card {
  min-height: 200px; padding: 12px; background: var(--card); border-radius: var(--raio-card); border: 2px solid transparent;
  display: flex; flex-direction: column; align-items: flex-start; gap: 6px; text-align: left;
}
.card.selecionado { border-color: var(--destaque-texto); }
.card.fora { opacity: .55; }
.amostra { width: 100%; height: 90px; border-radius: 12px; margin-bottom: 4px; }
.card-nome { font-size: 18px; font-weight: 900; line-height: 1.05; text-transform: uppercase; }
.card-preco { font-size: 14px; font-style: italic; color: var(--texto-2); }
.card-estoque { font-size: 12px; font-weight: 700; letter-spacing: 1px; color: var(--texto-3); }
.parada { display: flex; flex-direction: column; gap: 2px; }
.parada-nome { font-weight: 700; }
.sobre { margin: 0; line-height: 1.5; color: var(--texto-2); }
.ops { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 14px; padding: 48px 24px; }
.ops .titulo-grande { font-size: 52px; }
.parada-grande { background: var(--card); border-radius: var(--raio-card); padding: 20px; display: flex; flex-direction: column; gap: 6px; }
.parada-local { font-weight: 900; font-size: 28px; text-transform: uppercase; line-height: 1; }
.barra-fixa { margin-top: auto; }
.reservar { justify-content: space-between; }
.off { pointer-events: none; opacity: .45; }
.falha { padding: 48px 16px; gap: 16px; }
</style>
