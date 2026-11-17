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
  ? { to: '/pedidos', rotulo: 'Meus pedidos' }
  : vendedor.value ? { to: '/painel', rotulo: 'Painel do vendedor' } : { to: '/entrar', rotulo: 'Entrar' })
const { escuro, alternar: alternarTema } = useTema()
const folhaAberta = ref(true)

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
const ativo = computed(() => data.value.find(v => v.slug === selecionado.value) ?? ordenados.value[0] ?? null)

const faixa = (v: VendedorMapa) => v.preco_min === null ? '' : v.preco_min === v.preco_max ? formatarBRL(v.preco_min) : `${formatarBRL(v.preco_min)} a ${formatarBRL(v.preco_max!)}`
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
  selecionado.value = v.slug
  folhaAberta.value = true
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
    <div class="mapa">
      <MapaVendedores ref="mapa" :vendedores="pontos" :ativo="ativo?.slug ?? null" :eu="eu" @escolher="escolherSlug" />
    </div>

    <header class="topo">
      <span class="marca">BANQUINHA</span>
      <ClientOnly>
        <div class="acoes">
          <button class="conta" :aria-pressed="escuro" aria-label="Modo escuro" @click="alternarTema">
            <svg v-if="escuro" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
          </button>
          <NuxtLink :to="conta.to" class="conta" :aria-label="conta.rotulo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" /></svg>
          </NuxtLink>
        </div>
      </ClientOnly>
    </header>

    <button class="centralizar" :class="{ baixo: !folhaAberta }" aria-label="Centralizar no meu local" @click="centralizarEmMim">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v4M12 18v4M2 12h4M18 12h4" /></svg>
    </button>

    <section class="folha" :class="{ fechada: !folhaAberta }" aria-labelledby="titulo-vendedores">
      <h1 id="titulo-vendedores" class="sr">Vendedores em atividade</h1>
      <button
        class="alca"
        :aria-expanded="folhaAberta"
        aria-controls="conteudo-folha"
        :aria-label="folhaAberta ? 'Ocultar detalhes e ver só o mapa' : 'Mostrar detalhes do vendedor'"
        @click="folhaAberta = !folhaAberta"
      >
        <span aria-hidden="true" />
      </button>
      <div v-show="folhaAberta" id="conteudo-folha" class="conteudo-folha">
      <p v-if="erroLocal" class="erro" role="alert">{{ erroLocal }}</p>
      <p v-if="!data.length" class="dica">Ninguém vendendo agora.</p>

      <template v-if="ativo">
        <div class="card">
          <div class="cores">
            <span v-for="(c, i) in ativo.cores.slice(0, 4)" :key="i" :style="{ background: c || 'var(--cinza)' }" />
          </div>
          <span class="nome">{{ ativo.nome }}</span>
          <span class="subtitulo">{{ faixa(ativo) }}</span>
          <span class="status" :class="ativo.status">
            <span class="ponto" aria-hidden="true" />
            {{ ativo.status === 'vendendo' ? 'Vendendo agora' : 'Esgotado hoje' }}
            <template v-if="distancia(ativo)"> · {{ distancia(ativo) }}</template>
            <template v-if="ativo.localizacao"> · atualizado {{ haQuantoTempo(ativo.localizacao.atualizado_em) }}</template>
            <template v-else> · sem localização no mapa</template>
          </span>
        </div>
        <NuxtLink :to="`/v/${ativo.slug}`" class="btn btn-destaque btn-bloco">Ver cardápio</NuxtLink>
      </template>

      <ul v-if="ordenados.length > 1" class="outros">
        <li v-for="v in ordenados" :key="v.slug">
          <button :class="{ on: ativo?.slug === v.slug }" @click="escolher(v)">
            {{ v.nome }}<template v-if="distancia(v)"> · {{ distancia(v) }}</template>
          </button>
        </li>
      </ul>

      </div>
    </section>
  </main>
</template>

<style scoped>
.inicio { position: relative; height: 100dvh; overflow: hidden; background: var(--fundo); }
.mapa { position: absolute; inset: 0; z-index: 0; }
.topo {
  position: absolute; top: 0; left: 0; right: 0; z-index: 2; pointer-events: none;
  padding: 12px 16px 0; padding-top: max(12px, env(safe-area-inset-top));
  display: flex; align-items: center; justify-content: space-between;
}
.topo > * { pointer-events: auto; }
.marca { font-weight: 900; font-size: 20px; letter-spacing: 3px; background: var(--fundo); border-radius: var(--raio); padding: 8px 12px; }
.conta, .centralizar {
  width: 44px; height: 44px; border-radius: var(--raio); background: var(--fundo); color: var(--texto);
  display: flex; align-items: center; justify-content: center; border: 1px solid var(--borda);
}
.acoes { display: flex; gap: 8px; }
.centralizar { position: absolute; right: 16px; bottom: calc(46dvh + 12px); z-index: 2; width: 48px; height: 48px; }
.folha {
  position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; max-height: 46dvh; overflow-y: auto;
  background: var(--fundo); border-radius: 24px 24px 0 0; padding: 10px 16px; padding-bottom: max(20px, env(safe-area-inset-bottom));
  display: flex; flex-direction: column; gap: 14px; max-width: 640px; margin: 0 auto;
}
.alca { align-self: center; width: 88px; height: 28px; border: 0; background: none; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin: -6px 0 -8px; }
.alca span { width: 40px; height: 4px; border-radius: 2px; background: var(--borda); }
.folha.fechada { padding-bottom: max(8px, env(safe-area-inset-bottom)); }
.conteudo-folha { display: flex; flex-direction: column; gap: 14px; }
.centralizar.baixo { bottom: 64px; }
.card { background: var(--card); border-radius: var(--raio-card); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
.cores { display: flex; gap: 6px; }
.cores span { flex: 1; height: 40px; border-radius: 10px; }
.nome { font-weight: 900; font-size: 26px; line-height: .95; text-transform: uppercase; }
.subtitulo { font-size: 16px; }
.status { font-size: 13px; font-weight: 700; color: var(--destaque-texto); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; text-transform: uppercase; }
.status.esgotado { color: var(--texto-3); }
.ponto { width: 8px; height: 8px; border-radius: 4px; background: currentColor; }
.outros { list-style: none; margin: 0; padding: 0; display: flex; gap: 8px; overflow-x: auto; }
.outros button { min-height: 44px; padding: 0 14px; border: 1px solid var(--borda); background: var(--card); font-weight: 700; font-size: 13px; white-space: nowrap; }
.outros button.on { border-color: var(--destaque-texto); color: var(--destaque-texto); }
</style>
