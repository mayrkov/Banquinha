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
      <div class="quem">
        <span class="rotulo">{{ vendedor?.em_atividade ? 'EM TURNO' : 'FORA DE TURNO' }}</span>
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
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" /><path d="M4 10h16M10 10v10" /></svg>
        VENDER
      </NuxtLink>
      <NuxtLink to="/painel/reservas" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        RESERVAS
        <span v-if="pendentes.length" class="contador" :aria-label="`${pendentes.length} novas`">{{ pendentes.length }}</span>
      </NuxtLink>
      <NuxtLink to="/painel/turno" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
        TURNO
      </NuxtLink>
      <NuxtLink to="/painel/produtos" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4z" /><path d="M3 7v10l9 4 9-4V7" /></svg>
        PRODUTOS
      </NuxtLink>
      <NuxtLink to="/painel/conta" class="item" active-class="ativo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 4-6 8-6s8 2 8 6" /></svg>
        CONTA
      </NuxtLink>
    </nav>
  </div>
</template>

<style scoped>
.painel { min-height: 100dvh; display: flex; flex-direction: column; }
.topo {
  position: sticky; top: 0; z-index: 5;
  background: var(--card);
  padding: 12px 16px;
  padding-top: max(12px, env(safe-area-inset-top));
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.quem { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.rotulo { font-size: 12px; font-weight: 900; letter-spacing: 1.5px; color: var(--destaque-texto); }
.nome { font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sinc {
  border: 0; background: var(--fundo); min-height: 36px; padding: 0 12px;
  font-size: 12px; font-weight: 700; color: var(--texto-2);
  display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.sinc:disabled { opacity: 1; cursor: default; }
.sinc.alerta { color: var(--destaque-texto); }
.ponto { width: 8px; height: 8px; border-radius: 4px; background: #1F9D55; }
.ponto.off { background: var(--destaque); }
.conteudo { flex: 1; display: flex; flex-direction: column; }
.nav {
  position: sticky; bottom: 0;
  display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));
  background: var(--card);
  padding-bottom: env(safe-area-inset-bottom);
  font-size: 10px; font-weight: 700; letter-spacing: .5px;
}
.item {
  height: 64px; color: var(--texto-2); text-decoration: none;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
}
.item.ativo { color: var(--destaque-texto); }
.item { position: relative; }
.contador {
  position: absolute; top: 8px; right: calc(50% - 22px); background: var(--destaque); color: var(--texto);
  font-size: 11px; font-weight: 900; min-width: 18px; height: 18px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
</style>
