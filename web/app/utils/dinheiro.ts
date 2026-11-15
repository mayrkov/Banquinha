// D35: valores sempre em centavos; formatação só na tela.
const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

export function formatarBRL(centavos: number): string {
  return fmt.format(centavos / 100)
}

/** "12,50" | "12.5" | "12" → 1250. Texto inválido → null. */
export function centavosDeTexto(texto: string): number | null {
  const limpo = texto.replace(/[R$\s]/g, '').replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.')
  if (!/^\d+(\.\d{0,2})?$/.test(limpo)) return null
  return Math.round(Number(limpo) * 100)
}

/** 1250 → "12,50" (para preencher inputs). */
export function textoDeCentavos(centavos: number): string {
  return (centavos / 100).toFixed(2).replace('.', ',')
}
