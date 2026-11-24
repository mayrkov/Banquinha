/**
 * Tema claro ou escuro e cor de destaque do app (protótipo, canvas). A escolha fica no aparelho
 * (localStorage "tema" e "cor"); sem tema escolhido, segue o sistema. O script no <head> (nuxt.config.ts)
 * aplica os dois antes de pintar a tela.
 */
export const CORES_APP = [
  { id: 'vermelho', nome: 'Vermelho', hex: '#FF3E41' },
  { id: 'laranja', nome: 'Laranja', hex: '#FF8A1F' },
  { id: 'verde', nome: 'Verde', hex: '#2EC27E' },
  { id: 'azul', nome: 'Azul', hex: '#4C8DFF' },
] as const
export type CorApp = typeof CORES_APP[number]['id']

export function useTema() {
  const escuro = useState('tema-escuro', () => false)
  const cor = useState<CorApp>('tema-cor', () => 'vermelho')
  onMounted(() => {
    escuro.value = document.documentElement.dataset.tema === 'escuro'
    cor.value = (document.documentElement.dataset.cor as CorApp | undefined) ?? 'vermelho'
  })

  function alternar() {
    escuro.value = !escuro.value
    if (escuro.value) document.documentElement.dataset.tema = 'escuro'
    else delete document.documentElement.dataset.tema
    try { localStorage.setItem('tema', escuro.value ? 'escuro' : 'claro') }
    catch {}
  }

  function escolherCor(id: CorApp) {
    cor.value = id
    // Vermelho é o padrão do CSS: sem atributo.
    if (id === 'vermelho') delete document.documentElement.dataset.cor
    else document.documentElement.dataset.cor = id
    try { localStorage.setItem('cor', id) }
    catch {}
  }

  return { escuro, alternar, cor, escolherCor }
}
