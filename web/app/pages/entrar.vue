<script setup lang="ts">
const route = useRoute()
// Sem destino pedido, volta para o início (mapa).
const volta = caminhoVolta(route.query.volta, '/')
const email = ref('')
const senha = ref('')
const erro = ref('')
const carregando = ref(false)

async function enviar() {
  erro.value = ''
  carregando.value = true
  try {
    // Login único: a API diz se a conta é de vendedor ou de cliente.
    await csrf()
    const r = await api<{ tipo: 'vendedor', usuario: Vendedor } | { tipo: 'cliente', usuario: Cliente }>('/login', {
      method: 'POST',
      body: { email: email.value, senha: senha.value },
    })
    if (r.tipo === 'vendedor') {
      useVendedor().definir(r.usuario)
      await navigateTo(volta.startsWith('/painel') ? volta : '/painel')
    }
    else {
      useCliente().definir(r.usuario)
      await navigateTo(volta.startsWith('/painel') ? '/' : volta)
    }
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo.'
  }
  finally {
    carregando.value = false
  }
}
</script>

<template>
  <main class="pagina">
    <header class="topo-cliente">
      <button class="icone-btn" aria-label="Voltar" @click="$router.back()"><IconeVoltar /></button>
      <span class="marca">Banquinha</span>
      <span class="icone-btn" />
    </header>
    <div class="cab">
      <h1 class="titulo-grande">Entrar</h1>
      <span class="subtitulo">{{ volta.includes('/reservar') ? 'Para enviar sua reserva' : 'Com seu e-mail e senha' }}</span>
    </div>
    <form class="form" @submit.prevent="enviar">
      <label class="campo">E-mail
        <input v-model="email" type="email" autocomplete="username" required placeholder="voce@email.com">
      </label>
      <label class="campo">Senha
        <input v-model="senha" type="password" autocomplete="current-password" required>
      </label>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <button class="btn btn-destaque" :disabled="carregando">
        {{ carregando ? 'Entrando…' : volta.includes('/reservar') ? 'Entrar e continuar' : 'Entrar' }}
      </button>
    </form>
    <div class="rodape">
      <span class="subtitulo">Primeira vez aqui?</span>
      <NuxtLink :to="{ path: '/cadastro', query: { volta } }" class="btn btn-cinza">Criar conta</NuxtLink>
    </div>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; display: flex; flex-direction: column; max-width: 480px; margin: 0 auto; }
.cab { padding: 24px 24px 0; display: flex; flex-direction: column; gap: 6px; }
.form { padding: 32px 24px 0; display: flex; flex-direction: column; gap: 16px; }
.rodape { margin-top: auto; padding: 32px 24px; display: flex; flex-direction: column; gap: 12px; }
</style>
