import type { FetchOptions } from 'ofetch'

export class ErroApi extends Error {
  constructor(public status: number, public dados: any) {
    super(dados?.message ?? `Erro ${status}`)
  }

  /** Primeira mensagem de validação do Laravel, ou a mensagem geral. */
  get mensagem(): string {
    const erros = this.dados?.errors
    return erros ? (Object.values(erros)[0] as string[])[0]! : this.message
  }
}

function lerCookie(nome: string): string {
  const par = document.cookie.split('; ').find(c => c.startsWith(nome + '='))
  return par ? decodeURIComponent(par.split('=')[1]!) : ''
}

async function csrf() {
  await $fetch(useRuntimeConfig().public.apiBase + '/sanctum/csrf-cookie', { credentials: 'include' })
}

/**
 * Chamada à API Laravel com cookie de sessão do Sanctum (modo SPA).
 * Erro de rede (sem sinal) sobe como TypeError/FetchError sem status; erro HTTP vira ErroApi.
 */
export async function api<T = any>(caminho: string, opcoes: FetchOptions<'json'> = {}, tentouCsrf = false): Promise<T> {
  const metodo = (opcoes.method ?? 'GET').toUpperCase()
  if (metodo !== 'GET' && !lerCookie('XSRF-TOKEN')) await csrf()

  try {
    return await $fetch<T>(useRuntimeConfig().public.apiBase + '/api/v1' + caminho, {
      ...opcoes,
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': lerCookie('XSRF-TOKEN'),
        ...opcoes.headers,
      },
    } as any)
  }
  catch (e: any) {
    const status = e?.response?.status
    if (!status) throw e
    // 419 = token CSRF expirado: renova uma vez.
    if (status === 419 && !tentouCsrf) {
      await csrf()
      return api<T>(caminho, opcoes, true)
    }
    throw new ErroApi(status, e.data)
  }
}

export { csrf }
