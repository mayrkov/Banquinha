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
  <main class="pagina-bloco">
    <TopoBloco marca="Banquinha" :legenda="volta.includes('/reservar') ? 'Passo 2 de 2' : undefined" titulo="Entrar">
      <span class="sub">{{ volta.includes('/reservar') ? 'para enviar sua reserva' : 'com seu e-mail e senha' }}</span>
    </TopoBloco>
    <form class="bloco form" @submit.prevent="enviar">
      <label class="campo">E-mail
        <input v-model="email" type="email" autocomplete="username" required placeholder="voce@email.com">
      </label>
      <label class="campo">Senha
        <input v-model="senha" type="password" autocomplete="current-password" required>
      </label>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <button class="btn btn-escuro btn-seta" :disabled="carregando">
        {{ carregando ? 'Entrando…' : volta.includes('/reservar') ? 'Entrar e continuar' : 'Entrar' }}
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </button>
    </form>
    <div class="bloco rodape">
      <span class="bloco-titulo">Primeira vez aqui?</span>
      <NuxtLink :to="{ path: '/cadastro', query: { volta } }" class="btn btn-cinza-claro">Criar conta</NuxtLink>
    </div>
  </main>
</template>

<style scoped>
.pagina-bloco { max-width: 480px; }
.sub { font-size: 16px; line-height: 1.3; }
.form { gap: 14px; }
.rodape { margin-top: auto; }
.btn-cinza-claro { background: var(--fundo); color: var(--texto); }
</style>
