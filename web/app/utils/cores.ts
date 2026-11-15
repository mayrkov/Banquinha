// Cores dos sabores do protótipo; usadas quando o produto não tem cor cadastrada.
const PALETA = ['#5A3324', '#9A6424', '#B32D46', '#2F5D50', '#3B4A8C', '#7A3E8E']

export function corDoProduto(p: { id: number, cor?: string | null }): string {
  return p.cor || PALETA[p.id % PALETA.length]!
}
