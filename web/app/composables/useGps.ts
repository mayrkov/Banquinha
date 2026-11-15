// Compartilha a posição do vendedor enquanto o painel está aberto e o turno ativo (risco técnico 1: web não envia GPS em segundo plano).
const PREF = 'banquinha:gps'
const INTERVALO_MS = 60_000

const ligado = ref(false)
const erro = ref('')
const ultimoEnvio = ref<Date | null>(null)
let watchId: number | null = null
let enviadoEm = 0

async function enviar(pos: GeolocationPosition) {
  if (Date.now() - enviadoEm < INTERVALO_MS) return
  enviadoEm = Date.now()
  try {
    await api('/vendedor/localizacao', { method: 'PUT', body: { latitude: pos.coords.latitude, longitude: pos.coords.longitude } })
    ultimoEnvio.value = new Date()
    erro.value = ''
  }
  catch (e) {
    enviadoEm = 0 // tenta de novo na próxima leitura
    if (e instanceof ErroApi) erro.value = e.mensagem
  }
}

function observar() {
  if (watchId !== null) return
  if (!('geolocation' in navigator) || !window.isSecureContext) {
    erro.value = 'Este navegador só libera o GPS em páginas HTTPS.'
    ligado.value = false
    return
  }
  enviadoEm = 0
  watchId = navigator.geolocation.watchPosition(enviar, (e) => {
    erro.value = e.code === e.PERMISSION_DENIED ? 'Permissão de localização negada no navegador.' : 'Não foi possível obter a localização.'
  }, { enableHighAccuracy: true, maximumAge: 30_000 })
}

function pararObservacao() {
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)
  watchId = null
}

export function useGps() {
  /** Liga ou desliga pelo botão do vendedor; a escolha fica guardada no aparelho. */
  async function alternar(valor: boolean) {
    ligado.value = valor
    try {
      localStorage.setItem(PREF, valor ? '1' : '0')
    }
    catch {}
    if (valor) {
      observar()
    }
    else {
      pararObservacao()
      ultimoEnvio.value = null
      await api('/vendedor/localizacao', { method: 'DELETE' }).catch(() => {})
    }
  }

  /** Chamado pelo layout: segue a preferência guardada e o estado do turno. */
  function sincronizar(emTurno: boolean) {
    let pref = false
    try {
      pref = localStorage.getItem(PREF) === '1'
    }
    catch {}
    ligado.value = pref
    if (pref && emTurno) observar()
    else pararObservacao()
  }

  return { ligado: readonly(ligado), erro: readonly(erro), ultimoEnvio: readonly(ultimoEnvio), alternar, sincronizar }
}
