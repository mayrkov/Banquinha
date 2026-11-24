<script setup lang="ts">
const { escuro } = useTema()
useHead({ meta: [{ name: 'theme-color', content: () => (escuro.value ? '#000000' : '#EBEBEB') }] })
</script>

<template>
  <NuxtPwaManifest />
  <NuxtRouteAnnouncer />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
