<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const route = useRoute()
const { produtos, carregar, salvar, enviarFoto, removerFoto } = useProdutos()
const online = useOnline()

const novo = route.params.id === 'novo'
const id = novo ? null : Number(route.params.id)
const form = reactive({ nome: '', descricao: '', cor: '#5A3324', preco: '', estoque: '0', ativo: true })
const erro = ref('')
const salvando = ref(false)
const foto = computed(() => produtos.value.find(x => x.id === id)?.foto_url ?? null)
const erroFoto = ref('')
const enviandoFoto = ref(false)

async function mudarFoto(acao: () => Promise<unknown>) {
  erroFoto.value = ''
  enviandoFoto.value = true
  try {
    await acao()
  }
  catch (e) {
    erroFoto.value = e instanceof ErroApi ? e.mensagem : e instanceof TypeError || !online.value ? 'Sem conexão. Tente de novo com internet.' : 'Não foi possível ler essa imagem. Tente uma foto JPG ou PNG.'
  }
  finally {
    enviandoFoto.value = false
  }
}

function escolherFoto(ev: Event) {
  const campo = ev.target as HTMLInputElement
  const arquivo = campo.files?.[0]
  campo.value = '' // permite escolher o mesmo arquivo de novo
  if (arquivo && id) mudarFoto(() => enviarFoto(id, arquivo))
}

function preencher() {
  const p = produtos.value.find(x => x.id === id)
  if (!p) return
  Object.assign(form, { nome: p.nome, descricao: p.descricao ?? '', cor: corDoProduto(p), preco: textoDeCentavos(p.preco), estoque: String(p.estoque), ativo: p.ativo })
}

async function enviar() {
  erro.value = ''
  const preco = centavosDeTexto(form.preco)
  const estoque = Number(form.estoque)
  if (preco === null) return (erro.value = 'Preço inválido. Use, por exemplo, 15,00.')
  if (!Number.isInteger(estoque) || estoque < 0) return (erro.value = 'Estoque deve ser um número inteiro.')

  salvando.value = true
  try {
    await salvar(id, { nome: form.nome, descricao: form.descricao || null, cor: form.cor, preco, estoque, ativo: form.ativo })
    await navigateTo('/painel/produtos')
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo com internet.'
  }
  finally {
    salvando.value = false
  }
}

onMounted(async () => {
  if (!novo) {
    preencher()
    await carregar().catch(() => {})
    preencher()
  }
})
</script>

<template>
  <section class="tela">
    <TopoBloco escuro voltar="/painel/produtos" rotulo-voltar="Voltar para produtos" marca="Produtos" :titulo="novo ? 'Novo produto' : 'Editar produto'" />

    <section class="bloco foto" aria-labelledby="titulo-foto">
      <h2 id="titulo-foto" class="bloco-titulo">Foto do sabor</h2>
      <div class="foto-linha">
        <div class="bolinha" :style="{ background: form.cor }">
          <img v-if="foto" :src="foto" alt="" width="112" height="112">
        </div>
        <div v-if="!novo" class="foto-acoes">
          <label class="btn btn-cinza-claro" :class="{ desligado: enviandoFoto || !online }">
            {{ enviandoFoto ? 'Enviando…' : foto ? 'Trocar foto' : 'Escolher foto' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="sr" :disabled="enviandoFoto || !online" @change="escolherFoto">
          </label>
          <button v-if="foto" type="button" class="btn btn-contorno" :disabled="enviandoFoto || !online" @click="mudarFoto(() => removerFoto(id!))">Remover</button>
        </div>
        <p v-else class="dica">Salve o produto para adicionar a foto.</p>
      </div>
      <p v-if="erroFoto" class="erro" role="alert">{{ erroFoto }}</p>
    </section>

    <form class="bloco" @submit.prevent="enviar">
      <label class="campo">Nome
        <input v-model="form.nome" required maxlength="255" placeholder="Ex.: Nutella">
      </label>
      <label class="campo">Descrição
        <textarea v-model="form.descricao" maxlength="2000" />
      </label>
      <label class="campo cor">Cor na página do cliente
        <input v-model="form.cor" type="color">
      </label>
      <div class="dois">
        <label class="campo">Preço padrão (R$)
          <input v-model="form.preco" inputmode="decimal" required placeholder="15,00">
        </label>
        <label class="campo">Estoque
          <input v-model="form.estoque" type="number" inputmode="numeric" min="0" step="1" required>
          <small>Quantidade de agora</small>
        </label>
      </div>
      <label class="linha-bloco check">
        <input v-model="form.ativo" type="checkbox"> Ativo (aparece no cardápio e no PDV)
      </label>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <p v-if="!online" class="dica">Sem internet: salvar precisa de sinal.</p>
      <button class="btn btn-destaque btn-seta" :disabled="salvando || !online">
        {{ salvando ? 'Salvando…' : 'Salvar' }}
        <span class="bolinha-seta"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
      </button>
    </form>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.cor input { width: 88px; padding: 4px; }
.foto-linha { display: flex; align-items: center; gap: 16px; }
.bolinha { width: 112px; height: 112px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.bolinha img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-acoes { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.foto-acoes .btn { min-height: 48px; font-size: 14px; }
.foto-acoes label:focus-within { outline: 3px solid var(--destaque-texto); outline-offset: 2px; }
.btn-cinza-claro { background: var(--fundo); color: var(--texto); }
.desligado { opacity: .45; cursor: not-allowed; }
.check { min-height: 56px; padding: 0 18px; font-size: 14px; cursor: pointer; }
.check input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
.btn-seta .bolinha-seta { width: 48px; height: 48px; border-radius: 50%; background: #111111; color: var(--destaque); display: flex; align-items: center; justify-content: center; }
</style>
