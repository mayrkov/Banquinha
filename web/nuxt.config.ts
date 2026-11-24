// https://nuxt.com/docs/api/configuration/nuxt-config
// API Laravel. Em dev/preview as rotas /api e /sanctum passam pelo Nuxt (mesma origem = cookie do Sanctum funciona).
const apiAlvo = process.env.NUXT_API_ALVO ?? 'http://api.banquinha.test'
// Fotos: em produção vêm do bucket público do Supabase; local, do disco public do Laravel.
const fotosAlvo = process.env.NUXT_FOTOS_ALVO ?? `${apiAlvo}/storage`

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@vite-pwa/nuxt'],
  css: ['~/assets/css/main.css'],

  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      title: 'Banquinha',
      meta: [{ name: 'theme-color', content: '#EBEBEB' }],
      link: [
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;900&display=swap' },
      ],
      // Aplica o tema escuro e a cor do app antes da primeira pintura (sem piscar). Ver composables/useTema.ts.
      script: [{
        innerHTML: `try{var d=document.documentElement,t=localStorage.getItem('tema'),c=localStorage.getItem('cor');if(t==='escuro'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))d.dataset.tema='escuro';if(c==='laranja'||c==='verde'||c==='azul')d.dataset.cor=c}catch(e){}`,
        tagPosition: 'head',
      }],
    },
  },

  runtimeConfig: {
    // Base da API vista pelo navegador. Vazio = mesma origem (proxy abaixo).
    public: { apiBase: '' },
  },

  routeRules: {
    // D38: painel só no navegador.
    '/painel/**': { ssr: false },
    // Área logada do cliente também só no navegador (D38).
    '/pedidos': { ssr: false },
    '/pedidos/**': { ssr: false },
    '/perfil': { ssr: false },
    '/entrar': { ssr: false },
    '/cadastro': { ssr: false },
    '/v/*/reservar': { ssr: false },
    '/api/**': { proxy: `${apiAlvo}/api/**` },
    '/sanctum/**': { proxy: `${apiAlvo}/sanctum/**` },
    // Fotos dos produtos (disco public do Laravel).
    '/storage/**': { proxy: `${fotosAlvo}/**` },
  },

  vite: {
    // Dev server atrás do proxy do Lerd (host-proxy site); ".lhr.life" = túnel `lerd share --localhost-run` (teste no celular).
    server: { allowedHosts: ['banquinha.test', '.lhr.life'] },
    optimizeDeps: { include: ['idb-keyval', 'qrcode', 'maplibre-gl'] },
    // Worker do MapLibre é módulo ES (import entre arquivos).
    worker: { format: 'es' },
  },

  nitro: {
    // Casca SPA estática do painel, usada pelo service worker para abrir sem internet.
    prerender: { routes: ['/painel'] },
  },

  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'Banquinha — Painel do vendedor',
      short_name: 'Banquinha',
      lang: 'pt-BR',
      start_url: '/painel',
      scope: '/',
      display: 'standalone',
      background_color: '#EBEBEB',
      theme_color: '#EBEBEB',
      icons: [
        { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
        { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
        { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
    },
    generateSW: {
      globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
      // O módulo inclui **/_payload.json para páginas pré-renderizadas; a casca SPA do painel não gera payload.
      globStrict: false,
      navigateFallback: '/painel',
      navigateFallbackAllowlist: [/^\/painel/],
      navigateFallbackDenylist: [/^\/api/, /^\/sanctum/],
      runtimeCaching: [
        {
          urlPattern: /^https:\/\/fonts\.(googleapis|gstatic)\.com\/.*/,
          handler: 'CacheFirst',
          options: { cacheName: 'fontes', expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 } },
        },
      ],
    },
    devOptions: { enabled: false },
  },
})
