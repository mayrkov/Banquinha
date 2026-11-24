<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const { produtos, carregar, salvar } = useProdutos()
const online = useOnline()
const erro = ref('')

const ordenados = computed(() => [...produtos.value].sort((a, b) => Number(b.ativo) - Number(a.ativo) || a.nome.localeCompare(b.nome)))

async function alternar(id: number, ativo: boolean) {
  erro.value = ''
  try {
    await salvar(id, { ativo })
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo com internet.'
  }
}

onMounted(() => carregar().catch(() => {}))
</script>

<template>
  <section class="tela">
    <header class="bloco cab">
      <h1 class="titulo-pagina">Produtos</h1>
      <NuxtLink to="/painel/produtos/novo" class="btn btn-escuro novo" :class="{ off: !online }">+ Novo</NuxtLink>
    </header>
    <p v-if="!online" class="dica nota">Sem internet: dá para ver, mas editar precisa de sinal.</p>
    <p v-if="erro" class="erro nota" role="alert">{{ erro }}</p>

    <ul class="bloco lista">
      <li v-for="p in ordenados" :key="p.id" class="linha-bloco item" :class="{ inativo: !p.ativo }">
        <span class="amostra" :style="{ background: corDoProduto(p) }" aria-hidden="true" />
        <NuxtLink :to="`/painel/produtos/${p.id}`" class="info">
          <span class="nome">{{ p.nome }}</span>
          <span class="dica">{{ formatarBRL(p.preco) }} · estoque {{ p.estoque }}</span>
        </NuxtLink>
        <label class="ativo">
          <input type="checkbox" :checked="p.ativo" :disabled="!online" @change="alternar(p.id, ($event.target as HTMLInputElement).checked)">
          {{ p.ativo ? 'Ativo' : 'Inativo' }}
        </label>
      </li>
      <li v-if="!produtos.length" class="dica vazio">Nenhum produto ainda.</li>
    </ul>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.cab { flex-direction: row; align-items: center; justify-content: space-between; }
.titulo-pagina { margin: 0; font-size: 32px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; }
.novo { min-height: 48px; font-size: 14px; }
.off { pointer-events: none; opacity: .45; }
.nota { padding: 0 8px; }
.lista { list-style: none; padding: 8px; gap: 6px; }
.item { padding: 8px 14px 8px 8px; }
.amostra { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; }
.inativo .amostra { opacity: .4; }
.info { flex: 1; display: flex; flex-direction: column; gap: 2px; color: inherit; text-decoration: none; min-height: 44px; justify-content: center; min-width: 0; }
.nome { font-weight: 600; font-size: 16px; }
.inativo .nome { color: var(--texto-3); }
.ativo { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--texto-2); min-height: 44px; }
.ativo input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
.vazio { padding: 8px; }
</style>
