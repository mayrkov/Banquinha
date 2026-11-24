// Carrega o MapLibre só no navegador. O worker precisa ser empacotado à parte pelo Vite;
// sem isso o build de produção não acha o arquivo ("Worker failed to load").
import urlWorker from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

export async function carregarMapLibre() {
  const lib = await import('maplibre-gl')
  lib.setWorkerUrl(urlWorker)
  return lib
}

/**
 * Créditos do mapa recolhidos no botão "i". O MapLibre abre o texto no celular e só fecha ao arrastar;
 * no mapa fixo isso nunca acontece e o texto cobria os botões.
 */
export function recolherCreditos(mapa: import('maplibre-gl').Map) {
  mapa.once('idle', () => {
    mapa.getContainer().querySelector('.maplibregl-ctrl-attrib')?.classList.remove('maplibregl-compact-show')
  })
}
