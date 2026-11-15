<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const route = useRoute()
const { produtos, carregar, salvar } = useProdutos()
const online = useOnline()

const novo = route.params.id === 'novo'
const id = novo ? null : Number(route.params.id)
const form = reactive({ nome: '', descricao: '', cor: '#5A3324', preco: '', estoque: '0', ativo: true })
const erro = ref('')
const salvando = ref(false)

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
    <header class="cab">
      <NuxtLink to="/painel/produtos" class="voltar" aria-label="Voltar para produtos">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
      </NuxtLink>
      <h1 class="titulo">{{ novo ? 'Novo produto' : 'Editar produto' }}</h1>
    </header>

    <form class="form" @submit.prevent="enviar">
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
      <label class="check">
        <input v-model="form.ativo" type="checkbox"> Ativo (aparece no cardápio e no PDV)
      </label>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <p v-if="!online" class="dica">Sem internet: salvar precisa de sinal.</p>
      <button class="btn btn-destaque" :disabled="salvando || !online">{{ salvando ? 'Salvando…' : 'Salvar' }}</button>
    </form>
  </section>
</template>

<style scoped>
.tela { padding: 0 16px 24px; }
.cab { height: 64px; display: flex; align-items: center; gap: 8px; }
.voltar { width: 44px; height: 44px; margin-left: -10px; color: var(--texto); display: flex; align-items: center; justify-content: center; }
.form { display: flex; flex-direction: column; gap: 16px; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.cor input { width: 88px; padding: 4px; }
.check { display: flex; align-items: center; gap: 10px; font-size: 14px; min-height: 44px; }
.check input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
</style>
