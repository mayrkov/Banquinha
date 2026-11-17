<script setup lang="ts">
const { escuro } = useTema()
useHead({ meta: [{ name: 'theme-color', content: () => (escuro.value ? '#0A0A0A' : '#EBEBEB') }] })
</script>

<template>
  <NuxtPwaManifest />
  <NuxtRouteAnnouncer />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
