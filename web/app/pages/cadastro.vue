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
  <main class="pagina-bloco">
    <TopoBloco marca="Banquinha" titulo="Criar conta">
      <span class="sub">para reservar e acompanhar pedidos</span>
    </TopoBloco>
    <form class="bloco form" @submit.prevent="enviar">
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
      <button class="btn btn-escuro btn-seta" :disabled="carregando">
        {{ carregando ? 'Criando…' : 'Criar conta' }}
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </button>
      <p class="dica">Pedimos nome, e-mail, telefone e senha. O endereço de entrega é apagado 3 dias após o pedido terminar.</p>
    </form>
    <p class="rodape dica">Já tem conta? <NuxtLink :to="{ path: '/entrar', query: { volta } }">Entrar</NuxtLink></p>
  </main>
</template>

<style scoped>
.pagina-bloco { max-width: 480px; }
.sub { font-size: 16px; line-height: 1.3; }
.form { gap: 14px; }
.rodape { padding: 8px 8px 24px; }
</style>
