// Só no modo dev: o build de produção gera o sw.js real (arquivo estático, servido antes desta rota).
// Remove o service worker de um build de produção anterior, que serviria páginas antigas do painel.
const DESREGISTRAR = `
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (e) => e.waitUntil((async () => {
  for (const k of await caches.keys()) await caches.delete(k)
  await self.registration.unregister()
  for (const c of await self.clients.matchAll({ type: 'window' })) c.navigate(c.url)
})()))
`

export default defineEventHandler((event) => {
  setHeader(event, 'Content-Type', 'text/javascript; charset=utf-8')
  setHeader(event, 'Cache-Control', 'no-store')
  return DESREGISTRAR
})
