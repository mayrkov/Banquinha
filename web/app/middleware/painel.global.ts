export default defineNuxtRouteMiddleware((to) => {
  if (!to.path.startsWith('/painel')) return
  const logado = !!useVendedor().vendedor.value
  // Endereço antigo do login do vendedor: agora o login é único em /entrar.
  if (to.path === '/painel/login') return navigateTo(logado ? '/painel' : { path: '/entrar', query: { volta: '/painel' } })
  if (!logado) return navigateTo({ path: '/entrar', query: { volta: to.fullPath } })
})
