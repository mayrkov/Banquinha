<script setup lang="ts">
interface VendedorMapa {
  nome: string
  slug: string
  descricao: string | null
  foto_url: string | null
  status: 'vendendo' | 'esgotado'
  cores: (string | null)[]
  preco_min: number | null
  preco_max: number | null
  localizacao: (Ponto & { atualizado_em: string }) | null
}

useSeoMeta({ title: 'Banquinha · vendedores perto de você', description: 'Veja no mapa quem está vendendo agora, o que ainda tem e reserve.' })

const { data, refresh } = await useFetch<VendedorMapa[]>('/api/v1/publico/vendedores', { default: () => [] })
const { cliente } = useCliente()
const { vendedor } = useVendedor()
// O ícone de conta leva para onde a pessoa logada usa o sistema.
const conta = computed(() => cliente.value
  ? { to: '/perfil', rotulo: 'Meu perfil' }
  : vendedor.value ? { to: '/painel', rotulo: 'Painel do vendedor' } : { to: '/entrar', rotulo: 'Entrar' })
const { escuro, alternar: alternarTema } = useTema()

const mapa = ref<{ irPara: (p: Ponto, zoom?: number) => void } | null>(null)
const eu = ref<Ponto | null>(null)
const selecionado = ref<string | null>(null)
const erroLocal = ref('')

const pontos = computed(() => data.value.filter(v => v.localizacao).map(v => ({
  slug: v.slug, nome: v.nome, foto_url: v.foto_url, status: v.status, latitude: v.localizacao!.latitude, longitude: v.localizacao!.longitude,
})))
const ordenados = computed(() => {
  const lista = [...data.value]
  if (eu.value) {
    const d = (v: VendedorMapa) => (v.localizacao ? distanciaM(eu.value!, v.localizacao) : Infinity)
    lista.sort((a, b) => d(a) - d(b))
  }
  return lista
})
// O card do vendedor só aparece depois de tocar nele (no mapa ou na lista).
const ativo = computed(() => data.value.find(v => v.slug === selecionado.value) ?? null)

const faixa = (v: VendedorMapa) => v.preco_min === null ? '' : v.preco_min === v.preco_max ? formatarBRL(v.preco_min) : `${formatarBRL(v.preco_min)} a ${formatarBRL(v.preco_max!)}`
const vendendo = computed(() => data.value.filter(v => v.status === 'vendendo').length)
const distancia = (v: VendedorMapa) => (eu.value && v.localizacao ? formatarDistancia(distanciaM(eu.value, v.localizacao)) : '')

function centralizarEmMim() {
  erroLocal.value = ''
  if (!('geolocation' in navigator) || !window.isSecureContext) {
    erroLocal.value = 'Seu navegador só libera a localização em páginas HTTPS.'
    return
  }
  navigator.geolocation.getCurrentPosition((p) => {
    eu.value = { latitude: p.coords.latitude, longitude: p.coords.longitude }
    mapa.value?.irPara(eu.value)
  }, () => { erroLocal.value = 'Não foi possível obter sua localização.' }, { enableHighAccuracy: true, timeout: 10_000 })
}

function escolher(v: VendedorMapa) {
  // Tocar de novo no mesmo vendedor fecha o card.
  if (selecionado.value === v.slug) {
    selecionado.value = null
    return
  }
  selecionado.value = v.slug
  if (v.localizacao) mapa.value?.irPara(v.localizacao)
}

function escolherSlug(slug: string) {
  const v = data.value.find(x => x.slug === slug)
  if (v) escolher(v)
}

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { timer = setInterval(() => refresh(), 30_000) })
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <main class="inicio">
    <header class="topo">
      <span class="pino" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
      </span>
      <div class="marca">
        <span class="nome-marca">Banquinha</span>
        <span class="contagem">{{ vendendo }} {{ vendendo === 1 ? 'vendedor' : 'vendedores' }} vendendo</span>
      </div>
      <ClientOnly>
        <button class="redondo" :aria-pressed="escuro" aria-label="Modo escuro" @click="alternarTema">
          <svg v-if="escuro" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
          <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
        </button>
        <NuxtLink :to="conta.to" class="redondo" :aria-label="conta.rotulo">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" /></svg>
        </NuxtLink>
      </ClientOnly>
    </header>

    <div class="bloco-mapa">
      <div class="mapa">
        <MapaVendedores ref="mapa" :vendedores="pontos" :ativo="ativo?.slug ?? null" :eu="eu" @escolher="escolherSlug" />
        <ul v-if="ordenados.length > 1" class="outros" aria-label="Vendedores">
          <li v-for="v in ordenados" :key="v.slug">
            <button :aria-pressed="ativo?.slug === v.slug" @click="escolher(v)">
              {{ v.nome }}<template v-if="distancia(v)"> · {{ distancia(v) }}</template>
            </button>
          </li>
        </ul>
        <span v-if="ativo && distancia(ativo)" class="distancia"><span class="ponto" aria-hidden="true" />{{ distancia(ativo) }}</span>
        <button class="redondo centralizar" aria-label="Centralizar no meu local" @click="centralizarEmMim">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v4M12 18v4M2 12h4M18 12h4" /></svg>
        </button>
      </div>
    </div>

    <section class="rodape" aria-labelledby="titulo-vendedores">
      <h1 id="titulo-vendedores" class="sr">Vendedores em atividade</h1>
      <p v-if="erroLocal" class="erro" role="alert">{{ erroLocal }}</p>
      <p v-if="!data.length" class="dica vazio">Ninguém vendendo agora.</p>
      <NuxtLink v-if="ativo" :to="`/v/${ativo.slug}`" class="card">
        <span class="cores" aria-hidden="true">
          <span v-for="(c, i) in ativo.cores.slice(0, 3)" :key="i" :style="{ background: c || 'var(--cinza)' }" />
        </span>
        <span class="textos">
          <span class="nome">{{ ativo.nome }}</span>
          <span class="faixa">
            <template v-if="ativo.localizacao">atualizado {{ haQuantoTempo(ativo.localizacao.atualizado_em) }}</template>
            <template v-else>sem localização no mapa</template>
            <template v-if="faixa(ativo)"> · {{ faixa(ativo) }}</template>
          </span>
          <span class="pilula" :class="{ esgotado: ativo.status === 'esgotado' }">
            {{ ativo.status === 'vendendo' ? 'Vendendo agora' : 'Esgotado hoje' }}
          </span>
        </span>
        <span class="seta" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17L17 7M9 7h8v8" /></svg>
        </span>
      </NuxtLink>
    </section>
  </main>
</template>

<style scoped>
.inicio {
  height: 100dvh; overflow: hidden; background: var(--fundo); max-width: 640px; margin: 0 auto;
  padding: 8px; padding-top: max(8px, env(safe-area-inset-top)); padding-bottom: max(8px, env(safe-area-inset-bottom));
  display: flex; flex-direction: column; gap: 8px;
}
.topo { height: 64px; flex: none; display: flex; align-items: center; gap: 6px; background: var(--destaque); color: var(--sobre-destaque); border-radius: var(--raio-card); padding: 0 6px 0 12px; }
.pino { width: 40px; height: 40px; border-radius: 50%; background: var(--preto); color: var(--destaque); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.marca { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; padding-left: 4px; }
.nome-marca { font-size: 15px; font-weight: 600; }
.contagem { font-size: 12px; font-weight: 500; }
.redondo { width: 52px; height: 52px; flex-shrink: 0; border-radius: 50%; border: 0; background: #FFFFFF; color: #111111; display: flex; align-items: center; justify-content: center; }
.bloco-mapa { flex: 1; min-height: 0; background: var(--preto); border-radius: var(--raio-card); padding: 10px; display: flex; }
.mapa { flex: 1; position: relative; border-radius: 20px; overflow: hidden; }
.mapa :deep(.mapa-gl) { position: absolute; inset: 0; }
.outros { position: absolute; top: 10px; left: 10px; right: 10px; z-index: 2; list-style: none; margin: 0; padding: 0; display: flex; gap: 6px; overflow-x: auto; }
.outros button { height: 44px; padding: 0 16px; border: 0; background: #FFFFFF; color: #111111; font-weight: 600; font-size: 13px; white-space: nowrap; }
.outros button[aria-pressed="true"] { background: var(--destaque); color: var(--sobre-destaque); }
.distancia { position: absolute; left: 10px; bottom: 14px; z-index: 2; background: #111111; color: #FFFFFF; font-size: 12px; font-weight: 600; padding: 8px 12px; border-radius: var(--pilula); display: flex; align-items: center; gap: 6px; }
.ponto { width: 7px; height: 7px; border-radius: 50%; background: var(--destaque); }
.centralizar { position: absolute; right: 10px; bottom: 44px; /* acima do "i" dos créditos */ z-index: 2; width: 48px; height: 48px; }
.rodape { flex: none; display: flex; flex-direction: column; gap: 8px; }
.rodape > .erro, .vazio { padding: 0 12px; }
.card { min-height: 112px; background: var(--card); color: var(--texto); border-radius: var(--raio-card); padding: 12px 12px 12px 16px; display: flex; align-items: center; gap: 12px; text-decoration: none; }
.cores { display: flex; flex-shrink: 0; }
.cores span { width: 28px; height: 28px; border-radius: 50%; border: 3px solid var(--card); }
.cores span + span { margin-left: -12px; }
.textos { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.nome { font-size: 17px; font-weight: 600; letter-spacing: -0.3px; line-height: 1.15; }
.faixa { font-size: 13px; color: var(--texto-2); }
.pilula.esgotado { background: var(--apagado); color: #FFFFFF; }
</style>
