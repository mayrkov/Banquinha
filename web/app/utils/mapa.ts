// Mapa base vetorial do OpenFreeMap (estilos Positron e Dark): só ruas, bairros e nomes; sem lojas e estabelecimentos.
// Gratuito, sem chave e com uso comercial permitido (https://openfreemap.org).
export const ESTILO_MAPA = 'https://tiles.openfreemap.org/styles/positron'
export const ESTILO_MAPA_ESCURO = 'https://tiles.openfreemap.org/styles/dark'

/** Estilo do mapa conforme o tema aplicado na página. */
export function estiloMapa(): string {
  return document.documentElement.dataset.tema === 'escuro' ? ESTILO_MAPA_ESCURO : ESTILO_MAPA
}
