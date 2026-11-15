<script setup lang="ts">
const route = useRoute()
const { cadastrar } = useCliente()
// Sem destino pedido, volta para o início (lista de vendedores).
const volta = caminhoVolta(route.query.volta, '/')
const form = reactive({ nome: '', email: '', telefone: '', senha: '' })
const erro = ref('')
const carregando = ref(false)

async function enviar() {
  erro.value = ''
  carregando.value = true
  try {
    await cadastrar({ ...form })
    await navigateTo(volta)
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
      <h1 class="titulo-grande">Criar conta</h1>
      <span class="subtitulo">Para reservar e acompanhar pedidos</span>
    </div>
    <form class="form" @submit.prevent="enviar">
      <label class="campo">Nome
        <input v-model="form.nome" autocomplete="name" required maxlength="255">
      </label>
      <label class="campo">E-mail
        <input v-model="form.email" type="email" autocomplete="email" required>
      </label>
      <label class="campo">Telefone (WhatsApp)
        <input v-model="form.telefone" type="tel" autocomplete="tel" required minlength="10" maxlength="30" placeholder="(11) 99999-0000">
        <small>O vendedor usa para falar com você na entrega.</small>
      </label>
      <label class="campo">Senha
        <input v-model="form.senha" type="password" autocomplete="new-password" required minlength="8">
        <small>Mínimo de 8 caracteres.</small>
      </label>
      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <button class="btn btn-destaque" :disabled="carregando">{{ carregando ? 'Criando…' : 'Criar conta' }}</button>
      <p class="dica">Pedimos nome, e-mail, telefone e senha. O endereço de entrega é apagado 3 dias após o pedido terminar.</p>
    </form>
    <p class="rodape dica">Já tem conta? <NuxtLink :to="{ path: '/entrar', query: { volta } }">Entrar</NuxtLink></p>
  </main>
</template>

<style scoped>
.pagina { min-height: 100dvh; display: flex; flex-direction: column; max-width: 480px; margin: 0 auto; }
.cab { padding: 24px 24px 0; display: flex; flex-direction: column; gap: 6px; }
.form { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.rodape { padding: 0 24px 32px; }
</style>
