<script setup lang="ts">
// Escolha da cor de destaque do app (canvas: "Cor do app"). Fica salva no aparelho.
const { cor, escolherCor } = useTema()
</script>

<template>
  <ClientOnly>
    <div role="radiogroup" aria-label="Cor do app" class="cores">
      <button
        v-for="c in CORES_APP"
        :key="c.id"
        type="button"
        role="radio"
        :aria-checked="cor === c.id"
        :class="{ on: cor === c.id }"
        @click="escolherCor(c.id)"
      >
        <span class="amostra" :style="{ background: c.hex }" />
        {{ c.nome }}
      </button>
    </div>
  </ClientOnly>
</template>

<style scoped>
.cores { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.cores button {
  height: 96px; border-radius: 22px; border: 2px solid transparent; background: var(--fundo); color: var(--texto);
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; font-size: 12px; font-weight: 600;
}
.cores button.on { border-color: var(--texto); }
.amostra { width: 36px; height: 36px; border-radius: 50%; }
</style>
