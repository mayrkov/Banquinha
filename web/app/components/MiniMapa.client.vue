<script setup lang="ts">
// Mapa pequeno e fixo com um ponto; "Abrir mapa" leva ao app de mapas do celular.
import 'maplibre-gl/dist/maplibre-gl.css'
import type { Map as MapaGL, Marker } from 'maplibre-gl'

const props = defineProps<{ latitude: number, longitude: number }>()
const el = ref<HTMLDivElement | null>(null)
let mapa: MapaGL | null = null
let marcador: Marker | null = null

onMounted(async () => {
  const lib = await carregarMapLibre()
  mapa = new lib.Map({
    container: el.value!,
    style: ESTILO_MAPA,
    center: [props.longitude, props.latitude],
    zoom: 16,
    interactive: false,
    attributionControl: { compact: true },
  })
  marcador = new lib.Marker({ element: Object.assign(document.createElement('span'), { className: 'ponto-vendedor' }) })
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
.mini-mapa { position: relative; height: 160px; border-radius: var(--raio-card); overflow: hidden; background: var(--card); }
.abrir-mapa {
  position: absolute; right: 8px; bottom: 8px; z-index: 2; background: var(--fundo); color: var(--texto);
  font-size: 12px; font-weight: 700; letter-spacing: 1px; padding: 10px 12px; text-decoration: none; text-transform: uppercase;
}
</style>
