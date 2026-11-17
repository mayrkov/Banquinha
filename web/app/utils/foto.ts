/**
 * Corta a foto no centro (quadrada, porque aparece numa bolinha) e reduz para no máximo 800 px em JPEG.
 * Foto de celular tem vários MB; assim o envio fica leve e a página do vendedor abre rápido.
 */
export async function reduzirFoto(arquivo: File, lado = 800): Promise<Blob> {
  const img = await createImageBitmap(arquivo)
  const corte = Math.min(img.width, img.height)
  const final = Math.min(corte, lado)
  const tela = document.createElement('canvas')
  tela.width = tela.height = final
  tela.getContext('2d')!.drawImage(img, (img.width - corte) / 2, (img.height - corte) / 2, corte, corte, 0, 0, final, final)
  img.close()
  return new Promise((ok, falha) => tela.toBlob(b => (b ? ok(b) : falha(new Error('Não foi possível ler a imagem.'))), 'image/jpeg', 0.85))
}
