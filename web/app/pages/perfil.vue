<script setup lang="ts">
definePageMeta({ middleware: 'cliente' })

const { cliente, sair } = useCliente()

const inicial = computed(() => cliente.value?.nome.trim().charAt(0).toUpperCase() ?? '')

async function deslogar() {
  await sair().catch(() => {})
  await navigateTo('/')
}

onMounted(() => useCliente().atualizar())
</script>

<template>
  <main class="pagina-bloco">
    <TopoBloco voltar="/" rotulo-voltar="Voltar ao mapa" marca="Banquinha" legenda="Meu perfil">
      <div class="quem">
        <span class="avatar" aria-hidden="true">{{ inicial }}</span>
        <span class="quem-txt">
          <h1 class="nome">{{ cliente?.nome }}</h1>
          <span>{{ cliente?.telefone }}</span>
        </span>
      </div>
    </TopoBloco>

    <section class="bloco">
      <div class="cab">
        <h2 class="bloco-titulo">Cor do app</h2>
        <span class="dica">Muda a cor de destaque em todas as telas.</span>
      </div>
      <SeletorCor />
    </section>

    <nav class="bloco atalhos" aria-label="Minha conta">
      <NuxtLink to="/pedidos" class="linha-bloco atalho">
        <span class="atalho-txt"><span class="atalho-nome">Meus pedidos</span><span class="dica">Reservas em andamento e encerradas</span></span>
        <span class="seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </NuxtLink>
    </nav>

    <button class="btn btn-contorno sair" @click="deslogar">Sair da conta</button>
  </main>
</template>

<style scoped>
.quem { display: flex; align-items: center; gap: 14px; }
.avatar { width: 64px; height: 64px; border-radius: 50%; background: #111111; color: var(--destaque); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 600; flex-shrink: 0; }
.quem-txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; font-size: 14px; }
.nome { margin: 0; font-size: 30px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; overflow-wrap: anywhere; }
.cab { display: flex; flex-direction: column; gap: 2px; }
.atalhos { padding: 8px; }
.atalho { min-height: 64px; padding: 0 8px 0 18px; justify-content: space-between; }
.atalho-txt { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.atalho-nome { font-size: 15px; font-weight: 600; }
.sair { margin-top: auto; }
</style>
