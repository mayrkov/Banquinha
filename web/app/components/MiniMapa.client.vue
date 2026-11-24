<script setup lang="ts">
// Mapa pequeno e fixo com um ponto; "Abrir mapa" leva ao app de mapas do celular.
import 'maplibre-gl/dist/maplibre-gl.css'
import type { Map as MapaGL, Marker } from 'maplibre-gl'

const props = defineProps<{ latitude: number, longitude: number, foto?: string | null }>()
const el = ref<HTMLDivElement | null>(null)
let mapa: MapaGL | null = null
let marcador: Marker | null = null

onMounted(async () => {
  const lib = await carregarMapLibre()
  mapa = new lib.Map({
    container: el.value!,
    style: estiloMapa(),
    center: [props.longitude, props.latitude],
    zoom: 16,
    interactive: false,
    attributionControl: { compact: true },
  })
  recolherCreditos(mapa)
  const ponto = Object.assign(document.createElement('span'), { className: props.foto ? 'ponto-vendedor com-foto' : 'ponto-vendedor' })
  if (props.foto) ponto.style.backgroundImage = `url("${props.foto}")`
  marcador = new lib.Marker({ element: ponto })
    .setLngLat([props.longitude, props.latitude]).addTo(mapa)
})

watch(() => [props.latitude, props.longitude], () => {
  mapa?.setCenter([props.longitude, props.latitude])
  marcador?.setLngLat([props.longitude, props.latitude])
})
onBeforeUnmount(() => mapa?.remove())
</script>

<template>
  <div class="mini-mapa">
    <div ref="el" class="mapa-gl" role="img" aria-label="Mapa com a localização do vendedor" />
    <a :href="linkMapaExterno(props)" target="_blank" rel="noopener" class="abrir-mapa">Abrir mapa</a>
  </div>
</template>

<style scoped>
.mini-mapa { position: relative; height: 160px; border-radius: var(--raio); overflow: hidden; background: var(--card); }
.abrir-mapa {
  position: absolute; right: 8px; top: 8px; z-index: 2; background: var(--preto); color: var(--sobre-preto);
  font-size: 13px; font-weight: 600; padding: 12px 16px; border-radius: var(--pilula); text-decoration: none;
}
</style>
