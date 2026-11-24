// Carrega o MapLibre só no navegador. O worker precisa ser empacotado à parte pelo Vite;
// sem isso o build de produção não acha o arquivo ("Worker failed to load").
import urlWorker from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

export async function carregarMapLibre() {
  const lib = await import('maplibre-gl')
  lib.setWorkerUrl(urlWorker)
  return lib
}

/**
 * Créditos do mapa recolhidos no botão "i". O MapLibre abre o texto no celular quando os créditos chegam
 * (styledata/sourcedata) e só fecha ao arrastar; no mapa fixo isso nunca acontece e o texto cobria os botões.
 * Recolhe na primeira vez que o MapLibre abrir; depois o "i" volta a funcionar normalmente.
 */
export function recolherCreditos(mapa: import('maplibre-gl').Map) {
  const eventos = ['styledata', 'sourcedata', 'idle'] as const
  const recolher = () => setTimeout(() => {
    const c = mapa.getContainer().querySelector('.maplibregl-ctrl-attrib')
    if (!c?.classList.contains('maplibregl-compact')) return
    c.classList.remove('maplibregl-compact-show')
    for (const e of eventos) mapa.off(e, recolher)
  })
  for (const e of eventos) mapa.on(e, recolher)
}
