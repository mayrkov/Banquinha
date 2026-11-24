<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const { vendedor, turno } = useVendedor()
const gps = useGps()
const enviadoHa = computed(() => gps.ultimoEnvio.value ? `enviada às ${gps.ultimoEnvio.value.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` : 'aguardando o GPS…')
const online = useOnline()
const paradas = ref<Parada[]>([])
const form = reactive({ local: '', inicio: '', fim: '' })
const erro = ref('')
const ocupado = ref(false)

const linkPublico = computed(() => vendedor.value?.slug ? `${location.origin}/v/${vendedor.value.slug}` : '')

async function alternarTurno() {
  const ativo = vendedor.value?.em_atividade
  // Fluxo 6: encerrar passa pelo fechamento de caixa e pela contagem das sobras.
  if (ativo) return navigateTo('/painel/fechamento')
  erro.value = ''
  ocupado.value = true
  try {
    await turno(ativo ? 'encerrar' : 'iniciar')
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo com internet.'
  }
  finally {
    ocupado.value = false
  }
}

async function carregarParadas() {
  try {
    paradas.value = await api<Parada[]>('/vendedor/paradas')
  }
  catch {}
}

// datetime-local vem sem fuso: o navegador interpreta no horário do aparelho.
const iso = (v: string) => (v ? new Date(v).toISOString() : null)

async function adicionar() {
  erro.value = ''
  try {
    await api('/vendedor/paradas', { method: 'POST', body: { local: form.local, inicio: iso(form.inicio), fim: iso(form.fim) } })
    Object.assign(form, { local: '', inicio: '', fim: '' })
    await carregarParadas()
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão.'
  }
}

async function remover(p: Parada) {
  if (!confirm(`Remover a parada "${p.local}"?`)) return
  try {
    await api(`/vendedor/paradas/${p.id}`, { method: 'DELETE' })
    await carregarParadas()
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão.'
  }
}

onMounted(carregarParadas)
</script>

<template>
  <section class="tela">
    <header class="bloco"><h1 class="titulo-pagina">Turno e rota</h1></header>

    <div class="bloco turno" :class="{ 'bloco-preto': vendedor?.em_atividade }">
      <span class="estado">{{ vendedor?.em_atividade ? 'Em turno' : 'Fora de turno' }}</span>
      <span class="dica">
        {{ vendedor?.em_atividade ? 'Clientes veem você e podem reservar.' : 'Sua página mostra "fora de atividade" e as próximas paradas.' }}
      </span>
      <button class="btn btn-destaque btn-seta" :disabled="ocupado || !online" @click="alternarTurno">
        {{ vendedor?.em_atividade ? 'Encerrar turno' : 'Iniciar turno' }}
        <span class="bolinha"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
      </button>
    </div>
    <div v-if="vendedor?.em_atividade" class="bloco">
      <label class="gps">
        <input type="checkbox" :checked="gps.ligado.value" @change="gps.alternar(($event.target as HTMLInputElement).checked)">
        <span>
          <strong>Mostrar minha localização no mapa</strong>
          <span class="dica">
            {{ gps.ligado.value ? `Posição ${enviadoHa}. Enviada a cada minuto com o painel aberto.` : 'Desligado: clientes veem só suas paradas.' }}
          </span>
        </span>
      </label>
      <p v-if="gps.ligado.value && gps.erro.value" class="erro" role="alert">{{ gps.erro.value }}</p>
    </div>
    <NuxtLink v-if="!vendedor?.em_atividade" to="/painel/fechamento" class="bloco linha-atalho">
      <span><strong>Conferir estoque</strong><span class="dica">Conte as sobras e registre doações e perdas.</span></span>
      <span class="seta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8" /></svg></span>
    </NuxtLink>
    <p v-if="!vendedor?.slug" class="erro bloco">Defina o endereço da sua página em <NuxtLink to="/painel/conta">Conta</NuxtLink> para os clientes acharem você.</p>
    <p v-else class="dica nota">Sua página: <a :href="linkPublico" target="_blank" rel="noopener">{{ linkPublico }}</a></p>
    <p v-if="erro" class="erro bloco" role="alert">{{ erro }}</p>

    <section class="bloco paradas">
    <h2 class="bloco-titulo">Próximas paradas</h2>
    <div v-for="p in paradas" :key="p.id" class="linha-bloco parada">
      <div class="parada-txt">
        <strong>{{ p.local }}</strong>
        <span class="dica">{{ quandoParada(p) }}</span>
      </div>
      <button class="btn btn-branco" :disabled="!online" @click="remover(p)">Remover</button>
    </div>
    <p v-if="!paradas.length" class="dica">Nenhuma parada programada. O cliente escolhe uma delas para retirar o pedido.</p>
    </section>

    <form class="bloco" @submit.prevent="adicionar">
      <h2 class="bloco-titulo">Nova parada</h2>
      <label class="campo">Local
        <input v-model="form.local" required maxlength="255" placeholder="Ex.: Praça Principal">
      </label>
      <div class="dois">
        <label class="campo">Início
          <input v-model="form.inicio" type="datetime-local" required>
        </label>
        <label class="campo">Fim
          <input v-model="form.fim" type="datetime-local">
        </label>
      </div>
      <button class="btn btn-escuro" :disabled="!online">Adicionar parada</button>
    </form>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.titulo-pagina { margin: 0; font-size: 32px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; }
.turno { gap: 10px; }
.bloco-preto .dica { color: #A6A6A6; }
.gps { display: flex; gap: 12px; align-items: flex-start; min-height: 44px; }
.gps input { width: 24px; height: 24px; flex-shrink: 0; accent-color: var(--destaque-texto); }
.gps > span { display: flex; flex-direction: column; gap: 4px; }
.estado { font-size: 26px; font-weight: 600; letter-spacing: -0.5px; }
.nota { padding: 0 8px; overflow-wrap: anywhere; }
.linha-atalho { flex-direction: row; align-items: center; justify-content: space-between; text-decoration: none; padding: 12px 12px 12px 18px; }
.linha-atalho > span:first-child { display: flex; flex-direction: column; gap: 2px; }
.paradas { padding: 16px 8px 8px; gap: 6px; }
.paradas .bloco-titulo, .paradas > .dica { padding: 0 8px; }
.parada { padding: 8px 8px 8px 16px; }
.parada-txt { flex: 1; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.btn-branco { min-height: 44px; background: var(--card); color: var(--texto); font-size: 14px; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.dois input { padding: 0 12px; font-size: 14px; }
</style>
