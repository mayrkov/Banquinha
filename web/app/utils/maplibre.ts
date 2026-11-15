// Carrega o MapLibre só no navegador. O worker precisa ser empacotado à parte pelo Vite;
// sem isso o build de produção não acha o arquivo ("Worker failed to load").
import urlWorker from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

export async function carregarMapLibre() {
  const lib = await import('maplibre-gl')
  lib.setWorkerUrl(urlWorker)
  return lib
}
