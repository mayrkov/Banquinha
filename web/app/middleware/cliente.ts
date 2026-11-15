// Páginas do cliente logado. A sessão é conferida na API ao abrir a página.
export default defineNuxtRouteMiddleware((to) => {
  if (!useCliente().cliente.value) return navigateTo({ path: '/entrar', query: { volta: to.fullPath } })
})
