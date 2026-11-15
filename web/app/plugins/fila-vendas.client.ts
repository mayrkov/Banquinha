export default defineNuxtPlugin(() => {
  const { enviar } = useFilaVendas()
  const noPainel = () => location.pathname.startsWith('/painel')
  window.addEventListener('online', () => noPainel() && enviar())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && noPainel()) enviar()
  })
})
