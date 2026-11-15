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
    <header class="cab">
      <h1 class="titulo">Produtos</h1>
      <NuxtLink to="/painel/produtos/novo" class="btn btn-linha" :class="{ off: !online }">+ Novo</NuxtLink>
    </header>
    <p v-if="!online" class="dica">Sem internet: dá para ver, mas editar precisa de sinal.</p>
    <p v-if="erro" class="erro" role="alert">{{ erro }}</p>

    <ul class="lista">
      <li v-for="p in ordenados" :key="p.id" class="item" :class="{ inativo: !p.ativo }">
        <NuxtLink :to="`/painel/produtos/${p.id}`" class="info">
          <span class="nome">{{ p.nome }}</span>
          <span class="dica">{{ formatarBRL(p.preco) }} · estoque {{ p.estoque }}</span>
        </NuxtLink>
        <label class="ativo">
          <input type="checkbox" :checked="p.ativo" :disabled="!online" @change="alternar(p.id, ($event.target as HTMLInputElement).checked)">
          {{ p.ativo ? 'Ativo' : 'Inativo' }}
        </label>
      </li>
    </ul>
    <p v-if="!produtos.length" class="dica">Nenhum produto ainda.</p>
  </section>
</template>

<style scoped>
.tela { padding: 0 16px 24px; }
.cab { height: 64px; display: flex; align-items: center; justify-content: space-between; }
.off { pointer-events: none; opacity: .45; }
.lista { list-style: none; margin: 0; padding: 0; }
.item { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--linha); }
.info { flex: 1; display: flex; flex-direction: column; gap: 2px; padding: 14px 0; color: inherit; text-decoration: none; min-height: 44px; }
.nome { font-weight: 700; font-size: 16px; }
.inativo .nome { color: var(--texto-3); }
.ativo { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--texto-2); min-height: 44px; }
.ativo input { width: 22px; height: 22px; accent-color: var(--destaque-texto); }
</style>
