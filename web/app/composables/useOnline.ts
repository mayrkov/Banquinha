const online = ref(true)
let ouvindo = false

export function useOnline() {
  if (!ouvindo && import.meta.client) {
    ouvindo = true
    online.value = navigator.onLine
    window.addEventListener('online', () => { online.value = true })
    window.addEventListener('offline', () => { online.value = false })
  }
  return readonly(online)
}
