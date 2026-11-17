/**
 * Tema claro ou escuro (protótipo, canvas). A escolha fica no aparelho (localStorage "tema");
 * sem escolha, segue o sistema. O script no <head> (nuxt.config.ts) aplica antes de pintar a tela.
 */
export function useTema() {
  const escuro = useState('tema-escuro', () => false)
  onMounted(() => { escuro.value = document.documentElement.dataset.tema === 'escuro' })

  function alternar() {
    escuro.value = !escuro.value
    if (escuro.value) document.documentElement.dataset.tema = 'escuro'
    else delete document.documentElement.dataset.tema
    try { localStorage.setItem('tema', escuro.value ? 'escuro' : 'claro') }
    catch {}
  }

  return { escuro, alternar }
}
