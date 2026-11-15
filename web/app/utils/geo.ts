export interface Ponto { latitude: number, longitude: number }

/** Distância em metros (fórmula de haversine). */
export function distanciaM(a: Ponto, b: Ponto): number {
  const R = 6_371_000
  const rad = (g: number) => (g * Math.PI) / 180
  const dLat = rad(b.latitude - a.latitude)
  const dLon = rad(b.longitude - a.longitude)
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.latitude)) * Math.cos(rad(b.latitude)) * Math.sin(dLon / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}

export function formatarDistancia(m: number): string {
  return m < 1000 ? `${Math.round(m / 10) * 10} m` : `${(m / 1000).toFixed(1).replace('.', ',')} km`
}

/** "atualizado há X min" (REGRAS: localização 3). */
export function haQuantoTempo(iso: string): string {
  const min = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
  return min < 1 ? 'agora' : min < 60 ? `há ${min} min` : `há ${Math.floor(min / 60)} h`
}

export const linkMapaExterno = (p: Ponto) => `https://www.google.com/maps/search/?api=1&query=${p.latitude},${p.longitude}`
