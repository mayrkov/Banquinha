<script setup lang="ts">
// Cabeçalho do estilo blocos: aba com voltar + marca e, abaixo, o título grande no mesmo bloco.
defineProps<{ marca: string, legenda?: string, titulo?: string, voltar?: string, rotuloVoltar?: string, semVoltar?: boolean, escuro?: boolean, cinza?: boolean, aoVoltar?: () => void }>()
</script>

<template>
  <header class="topo-bloco" :class="{ escuro, cinza }">
    <div class="aba">
      <template v-if="!semVoltar">
        <NuxtLink v-if="voltar" :to="voltar" class="voltar" :aria-label="rotuloVoltar ?? 'Voltar'"><IconeVoltar /></NuxtLink>
        <button v-else type="button" class="voltar" :aria-label="rotuloVoltar ?? 'Voltar'" @click="aoVoltar ? aoVoltar() : $router.back()"><IconeVoltar /></button>
      </template>
      <span class="textos">
        <span class="marca">{{ marca }}</span>
        <span v-if="legenda" class="legenda">{{ legenda }}</span>
      </span>
      <slot name="acoes" />
    </div>
    <div v-if="titulo || $slots.default" class="corpo">
      <h1 v-if="titulo" class="titulo-topo">{{ titulo }}</h1>
      <slot />
    </div>
  </header>
</template>

<style scoped>
.topo-bloco { background: var(--destaque); color: var(--sobre-destaque); border-radius: var(--raio-card); }
.topo-bloco.escuro { background: var(--preto); color: var(--sobre-preto); }
.topo-bloco.cinza { background: var(--cinza); color: var(--texto); }
.aba { min-height: 64px; display: flex; align-items: center; gap: 10px; padding: 0 6px 0 12px; }
.voltar { width: 40px; height: 40px; border: 0; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.escuro .voltar { background: #2A2A2A; }
.voltar :deep(svg) { width: 20px; height: 20px; }
.textos { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.marca { font-size: 15px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.legenda { font-size: 12px; font-weight: 500; }
.escuro .legenda { color: #A6A6A6; }
.corpo { padding: 4px 20px 22px; display: flex; flex-direction: column; gap: 8px; }
.titulo-topo { margin: 0; font-size: 40px; font-weight: 600; line-height: 1.02; letter-spacing: -1.5px; overflow-wrap: anywhere; }
</style>
