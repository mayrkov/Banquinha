<script setup lang="ts">
const { vendedor } = useVendedor()
const { fila, enviando, enviar } = useFilaVendas()
const { pendentes, iniciarConsulta } = useReservasVendedor()
const gps = useGps()
// Liga/desliga o GPS conforme o turno (encerrar turno para de enviar).
watch(() => vendedor.value?.em_atividade, emTurno => gps.sincronizar(!!emTurno))
const online = useOnline()

const naFila = computed(() => fila.value.filter(v => !v.erro).length)
const comErro = computed(() => fila.value.filter(v => v.erro).length)

onMounted(() => {
  enviar()
  useVendedor().atualizar()
  iniciarConsulta()
  gps.sincronizar(!!vendedor.value?.em_atividade)
})
</script>

<template>
  <div class="painel">
    <header class="topo">
      <span class="inicial" aria-hidden="true">{{ vendedor?.nome?.charAt(0).toUpperCase() }}</span>
      <div class="quem">
        <span class="rotulo">{{ vendedor?.em_atividade ? 'Em turno' : 'Fora de turno' }}</span>
        <span class="nome">{{ vendedor?.nome }}<template v-if="vendedor?.em_atividade"> · GPS {{ gps.ligado.value ? 'ligado' : 'desligado' }}</template></span>
      </div>
      <button
        class="sinc"
        :class="{ alerta: !online || comErro }"
        :disabled="!online || enviando || !naFila"
        @click="enviar()"
      >
        <span class="ponto" :class="{ off: !online }" aria-hidden="true" />
        <template v-if="!online">Sem internet</template>
        <template v-else-if="enviando">Enviando…</template>
        <template v-else>Online</template>
        <template v-if="naFila"> · {{ naFila }} na fila</template>
        <template v-if="comErro"> · {{ comErro }} com erro</template>
      </button>
    </header>

    <main class="conteudo">
      <slot />
    </main>

    <nav aria-label="Painel" class="nav">
      <NuxtLink to="/painel" class="item" exact-active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3" /><path d="M4 10h16M10 10v10" /></svg>
        Vender
      </NuxtLink>
      <NuxtLink to="/painel/reservas" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        Reservas
        <span v-if="pendentes.length" class="contador" :aria-label="`${pendentes.length} novas`">{{ pendentes.length }}</span>
      </NuxtLink>
      <NuxtLink to="/painel/turno" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
        Turno
      </NuxtLink>
      <NuxtLink to="/painel/produtos" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4z" /><path d="M3 7v10l9 4 9-4V7" /></svg>
        Produtos
      </NuxtLink>
      <NuxtLink to="/painel/metricas" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
        Métricas
      </NuxtLink>
      <NuxtLink to="/painel/conta" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 4-6 8-6s8 2 8 6" /></svg>
        Conta
      </NuxtLink>
    </nav>
  </div>
</template>

<style scoped>
.painel {
  min-height: 100dvh; max-width: 640px; margin: 0 auto; padding: 8px; padding-top: max(8px, env(safe-area-inset-top));
  display: flex; flex-direction: column; gap: 8px;
}
.topo {
  position: sticky; top: 8px; z-index: 5; min-height: 64px;
  background: var(--destaque); color: var(--sobre-destaque); border-radius: var(--raio-card);
  padding: 0 8px 0 12px; display: flex; align-items: center; gap: 10px;
}
.inicial { width: 40px; height: 40px; border-radius: 50%; background: #111111; color: var(--destaque); display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.quem { flex: 1; display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.rotulo { font-size: 15px; font-weight: 600; }
.nome { font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sinc {
  border: 0; background: #FFFFFF; min-height: 44px; padding: 0 14px;
  font-size: 12px; font-weight: 600; color: #111111;
  display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.sinc:disabled { opacity: 1; cursor: default; }
.sinc.alerta { color: #C42326; }
.ponto { width: 8px; height: 8px; border-radius: 4px; background: #1F9D55; }
.ponto.off { background: var(--destaque); }
.conteudo { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.nav {
  position: sticky; bottom: max(8px, env(safe-area-inset-bottom)); z-index: 5;
  height: 72px; padding: 6px; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 2px;
  background: var(--card); border-radius: var(--pilula); box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
  font-size: 10px; font-weight: 600;
}
.item {
  position: relative; border-radius: var(--pilula); color: var(--texto-2); text-decoration: none;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
}
.item svg { width: 20px; height: 20px; }
.item.ativo { background: var(--preto); color: var(--sobre-preto); }
.contador {
  position: absolute; top: 4px; right: calc(50% - 24px); background: var(--destaque); color: var(--sobre-destaque);
  font-size: 11px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
</style>
