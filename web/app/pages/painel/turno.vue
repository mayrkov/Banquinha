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
  if (ativo && !confirm('Encerrar o turno? Você some do mapa e ninguém consegue reservar.')) return
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
    <header class="cab"><h1 class="titulo">Turno e rota</h1></header>

    <div class="turno" :class="{ ativo: vendedor?.em_atividade }">
      <span class="estado">{{ vendedor?.em_atividade ? 'Em turno' : 'Fora de turno' }}</span>
      <span class="dica">
        {{ vendedor?.em_atividade ? 'Clientes veem você e podem reservar.' : 'Sua página mostra "fora de atividade" e as próximas paradas.' }}
      </span>
      <button class="btn" :class="vendedor?.em_atividade ? 'btn-contorno' : 'btn-destaque'" :disabled="ocupado || !online" @click="alternarTurno">
        {{ vendedor?.em_atividade ? 'Encerrar turno' : 'Iniciar turno' }}
      </button>
    </div>
    <div v-if="vendedor?.em_atividade" class="turno">
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
    <p v-if="!vendedor?.slug" class="erro">Defina o endereço da sua página em <NuxtLink to="/painel/conta">Conta</NuxtLink> para os clientes acharem você.</p>
    <p v-else class="dica">Sua página: <a :href="linkPublico" target="_blank" rel="noopener">{{ linkPublico }}</a></p>
    <p v-if="erro" class="erro" role="alert">{{ erro }}</p>

    <h2 class="rotulo-secao">Próximas paradas</h2>
    <div v-for="p in paradas" :key="p.id" class="parada">
      <div class="parada-txt">
        <strong>{{ p.local }}</strong>
        <span class="dica">{{ quandoParada(p) }}</span>
      </div>
      <button class="btn btn-linha" :disabled="!online" @click="remover(p)">Remover</button>
    </div>
    <p v-if="!paradas.length" class="dica">Nenhuma parada programada. O cliente escolhe uma delas para retirar o pedido.</p>

    <form class="form" @submit.prevent="adicionar">
      <h2 class="rotulo-secao">Nova parada</h2>
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
.tela { padding: 0 16px 24px; display: flex; flex-direction: column; gap: 12px; }
.cab { height: 64px; display: flex; align-items: center; }
.turno { background: var(--card); border-radius: var(--raio-card); padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.turno.ativo { border-top: 4px solid var(--destaque); }
.gps { display: flex; gap: 12px; align-items: flex-start; min-height: 44px; }
.gps input { width: 24px; height: 24px; flex-shrink: 0; accent-color: var(--destaque-texto); }
.gps > span { display: flex; flex-direction: column; gap: 4px; }
.estado { font-size: 22px; font-weight: 900; text-transform: uppercase; }
.rotulo-secao { margin-top: 12px; font-size: 13px; }
.parada { background: var(--card); border-radius: var(--raio-card); padding: 14px; display: flex; align-items: center; gap: 12px; }
.parada-txt { flex: 1; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.form { display: flex; flex-direction: column; gap: 12px; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.dois input { padding: 0 8px; font-size: 14px; }
</style>
