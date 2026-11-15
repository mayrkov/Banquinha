<script setup lang="ts">
// Mapa da tela inicial: só os vendedores (botões acessíveis) e o ponto "você está aqui".
import 'maplibre-gl/dist/maplibre-gl.css'
import type { Map as MapaGL, Marker } from 'maplibre-gl'

export interface PontoVendedor { slug: string, nome: string, status: 'vendendo' | 'esgotado', latitude: number, longitude: number }

const props = defineProps<{ vendedores: PontoVendedor[], ativo: string | null, eu: Ponto | null }>()
const emit = defineEmits<{ escolher: [slug: string] }>()

const el = ref<HTMLDivElement | null>(null)
let mapa: MapaGL | null = null
let lib: typeof import('maplibre-gl') | null = null
const marcadores = new Map<string, Marker>()
let marcadorEu: Marker | null = null

function botao(v: PontoVendedor): HTMLButtonElement {
  const b = document.createElement('button')
  b.type = 'button'
  b.className = 'ponto-vendedor'
  b.addEventListener('click', () => emit('escolher', v.slug))
  return b
}

function desenhar() {
  if (!mapa || !lib) return
  const vistos = new Set<string>()
  for (const v of props.vendedores) {
    vistos.add(v.slug)
    let m = marcadores.get(v.slug)
    if (!m) {
      m = new lib.Marker({ element: botao(v) }).setLngLat([v.longitude, v.latitude]).addTo(mapa)
      marcadores.set(v.slug, m)
    }
    m.setLngLat([v.longitude, v.latitude])
    const e = m.getElement()
    e.setAttribute('aria-label', `${v.nome}, ${v.status === 'vendendo' ? 'vendendo agora' : 'esgotado hoje'}`)
    e.dataset.nome = v.nome
    e.classList.toggle('esgotado', v.status === 'esgotado')
    e.classList.toggle('ativo', v.slug === props.ativo)
  }
  for (const [slug, m] of marcadores) {
    if (!vistos.has(slug)) {
      m.remove()
      marcadores.delete(slug)
    }
  }
  if (props.eu) {
    marcadorEu ??= new lib.Marker({ element: Object.assign(document.createElement('span'), { className: 'ponto-eu', role: 'img', ariaLabel: 'Você está aqui' }) }).setLngLat([props.eu.longitude, props.eu.latitude]).addTo(mapa)
    marcadorEu.setLngLat([props.eu.longitude, props.eu.latitude])
  }
}

/** Mostra todos os vendedores (e você); sem ninguém, o Brasil. */
function enquadrar() {
  if (!mapa || !lib) return
  const pontos: [number, number][] = props.vendedores.map(v => [v.longitude, v.latitude])
  if (props.eu) pontos.push([props.eu.longitude, props.eu.latitude])
  if (pontos.length === 1) mapa.jumpTo({ center: pontos[0], zoom: 15 })
  else if (pontos.length > 1) {
    const caixa = new lib.LngLatBounds(pontos[0], pontos[0])
    for (const p of pontos) caixa.extend(p)
    mapa.fitBounds(caixa, { padding: 64, maxZoom: 16, duration: 0 })
  }
}

function irPara(p: Ponto, zoom = 15) {
  mapa?.easeTo({ center: [p.longitude, p.latitude], zoom: Math.max(mapa.getZoom(), zoom) })
}

defineExpose({ irPara, enquadrar })

onMounted(async () => {
  lib = await carregarMapLibre()
  mapa = new lib.Map({
    container: el.value!,
    style: ESTILO_MAPA,
    center: [-51.9, -14.2],
    zoom: 3,
    attributionControl: { compact: true },
    dragRotate: false,
    pitchWithRotate: false,
  })
  mapa.touchZoomRotate.disableRotation()
  mapa.on('load', () => {
    desenhar()
    enquadrar()
  })
})

watch(() => [props.vendedores, props.ativo, props.eu], desenhar, { deep: true })
onBeforeUnmount(() => mapa?.remove())
</script>

<template>
  <div ref="el" class="mapa-gl" />
</template>

<style>
.mapa-gl { width: 100%; height: 100%; }
</style>
