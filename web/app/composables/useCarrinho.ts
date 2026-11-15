// Carrinho do cliente por vendedor, guardado no aparelho (sobrevive ao login no meio da reserva).
const carrinhos = reactive<Record<string, Record<number, number>>>({})

export function useCarrinho(slug: string) {
  const chave = `banquinha:carrinho:${slug}`
  carrinhos[slug] ??= {}

  /** Lê do aparelho. Chamar em onMounted: no SSR o carrinho é vazio, e ler antes quebraria a hidratação. */
  function carregar() {
    try {
      carrinhos[slug] = JSON.parse(localStorage.getItem(chave) ?? '{}')
    }
    catch {}
  }
  const itens = computed(() => carrinhos[slug]!)

  function gravar() {
    try {
      localStorage.setItem(chave, JSON.stringify(carrinhos[slug]))
    }
    catch {}
  }

  function definir(produtoId: number, qtd: number) {
    if (qtd <= 0) delete carrinhos[slug]![produtoId]
    else carrinhos[slug]![produtoId] = qtd
    gravar()
  }

  const qtd = (id: number) => itens.value[id] ?? 0

  function limpar() {
    carrinhos[slug] = {}
    gravar()
  }

  return { itens, qtd, definir, limpar, carregar }
}
