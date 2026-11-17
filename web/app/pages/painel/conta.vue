<script setup lang="ts">
definePageMeta({ layout: 'painel' })

const { vendedor, salvarPerfil, enviarFoto, removerFoto, sair } = useVendedor()
const { fila } = useFilaVendas()
const { escuro, alternar: alternarTema } = useTema()
const online = useOnline()

const form = reactive({
  nome: '', slug: '', descricao: '', taxa_entrega: '0,00', prazo_resposta_min: '15', telefone: '', chave_pix: '', nome_recebedor_pix: '', cidade_recebedor_pix: '', provedor_cartao: '',
})
const erro = ref('')
const ok = ref(false)
const salvando = ref(false)
const erroFoto = ref('')
const enviandoFoto = ref(false)

async function mudarFoto(acao: () => Promise<unknown>) {
  erroFoto.value = ''
  enviandoFoto.value = true
  try {
    await acao()
  }
  catch (e) {
    erroFoto.value = e instanceof ErroApi ? e.mensagem : e instanceof TypeError || !online.value ? 'Sem conexão. Tente de novo com internet.' : 'Não foi possível ler essa imagem. Tente uma foto JPG ou PNG.'
  }
  finally {
    enviandoFoto.value = false
  }
}

function escolherFoto(ev: Event) {
  const campo = ev.target as HTMLInputElement
  const arquivo = campo.files?.[0]
  campo.value = '' // permite escolher o mesmo arquivo de novo
  if (arquivo) mudarFoto(() => enviarFoto(arquivo))
}

watchEffect(() => {
  const v = vendedor.value
  if (!v) return
  Object.assign(form, {
    nome: v.nome, slug: v.slug ?? '', descricao: v.descricao ?? '', taxa_entrega: textoDeCentavos(v.taxa_entrega),
    prazo_resposta_min: String(v.prazo_resposta_min), telefone: v.telefone ?? '', chave_pix: v.chave_pix ?? '',
    nome_recebedor_pix: v.nome_recebedor_pix ?? '', cidade_recebedor_pix: v.cidade_recebedor_pix ?? '',
    provedor_cartao: v.provedor_cartao ?? '',
  })
})

async function enviar() {
  erro.value = ''
  ok.value = false
  salvando.value = true
  const taxa = centavosDeTexto(form.taxa_entrega)
  if (taxa === null) {
    erro.value = 'Taxa de entrega inválida. Use, por exemplo, 3,00.'
    salvando.value = false
    return
  }
  try {
    const nulo = (s: string) => s.trim() || null
    await salvarPerfil({
      nome: form.nome,
      slug: nulo(form.slug.toLowerCase()),
      descricao: nulo(form.descricao),
      taxa_entrega: taxa,
      prazo_resposta_min: Number(form.prazo_resposta_min),
      telefone: nulo(form.telefone),
      chave_pix: nulo(form.chave_pix),
      nome_recebedor_pix: nulo(form.nome_recebedor_pix),
      cidade_recebedor_pix: nulo(form.cidade_recebedor_pix),
      provedor_cartao: (nulo(form.provedor_cartao) as any),
    })
    ok.value = true
  }
  catch (e) {
    erro.value = e instanceof ErroApi ? e.mensagem : 'Sem conexão. Tente de novo com internet.'
  }
  finally {
    salvando.value = false
  }
}

async function deslogar() {
  await sair().catch(() => {})
  await navigateTo('/entrar')
}
</script>

<template>
  <section class="tela">
    <header class="cab"><h1 class="titulo">Conta</h1></header>

    <section class="foto" aria-labelledby="titulo-foto">
      <h2 id="titulo-foto" class="sub">Foto do perfil</h2>
      <div class="foto-linha">
        <div class="bolinha">
          <img v-if="vendedor?.foto_url" :src="vendedor.foto_url" alt="" width="96" height="96">
        </div>
        <div class="foto-acoes">
          <label class="btn btn-cinza" :class="{ desligado: enviandoFoto || !online }">
            {{ enviandoFoto ? 'Enviando…' : vendedor?.foto_url ? 'Trocar foto' : 'Escolher foto' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="sr" :disabled="enviandoFoto || !online" @change="escolherFoto">
          </label>
          <button v-if="vendedor?.foto_url" type="button" class="btn btn-contorno" :disabled="enviandoFoto || !online" @click="mudarFoto(removerFoto)">Remover</button>
        </div>
      </div>
      <p class="dica">Aparece no mapa no lugar do seu ponto.</p>
      <p v-if="erroFoto" class="erro" role="alert">{{ erroFoto }}</p>
    </section>

    <form class="form" @submit.prevent="enviar">
      <label class="campo">Nome
        <input v-model="form.nome" required maxlength="255">
      </label>
      <label class="campo">Telefone
        <input v-model="form.telefone" type="tel" maxlength="30">
      </label>

      <h2 class="sub">Página pública</h2>
      <label class="campo">Endereço da página
        <input v-model="form.slug" maxlength="60" autocapitalize="off" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="pao-de-queijo-do-joao">
        <small>Só letras minúsculas, números e hífen. Link: {{ form.slug ? `/v/${form.slug}` : '—' }}</small>
      </label>
      <label class="campo">Descrição
        <textarea v-model="form.descricao" maxlength="2000" placeholder="O que você vende, onde costuma estar…" />
      </label>

      <h2 class="sub">Reservas</h2>
      <div class="dois">
        <label class="campo">Taxa de entrega (R$)
          <input v-model="form.taxa_entrega" inputmode="decimal">
        </label>
        <label class="campo">Prazo p/ responder (min)
          <input v-model="form.prazo_resposta_min" type="number" inputmode="numeric" min="1" max="240">
        </label>
      </div>

      <h2 class="sub">Pix</h2>
      <label class="campo">Chave Pix
        <input v-model="form.chave_pix" maxlength="77" autocapitalize="off">
        <small>Celular com +55, e-mail, CPF/CNPJ ou chave aleatória.</small>
      </label>
      <div class="dois">
        <label class="campo">Nome do recebedor
          <input v-model="form.nome_recebedor_pix" maxlength="25">
        </label>
        <label class="campo">Cidade
          <input v-model="form.cidade_recebedor_pix" maxlength="15">
        </label>
      </div>

      <h2 class="sub">Cartão</h2>
      <label class="campo">Como você cobra no cartão?
        <select v-model="form.provedor_cartao">
          <option value="">Não aceito cartão / não informado</option>
          <option value="infinitepay">InfinitePay (abre o app com o valor)</option>
          <option value="mercadopago_point">Mercado Pago Point</option>
          <option value="outro">Outra maquininha</option>
        </select>
      </label>

      <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
      <p v-if="ok" class="dica" role="status">Salvo.</p>
      <button class="btn btn-destaque" :disabled="salvando || !online">{{ salvando ? 'Salvando…' : 'Salvar' }}</button>
    </form>

    <h2 class="sub aparencia">Aparência</h2>
    <label class="tema">
      <input type="checkbox" :checked="escuro" @change="alternarTema">
      Modo escuro
    </label>

    <div class="sair">
      <p v-if="fila.length" class="dica">Há {{ fila.length }} venda(s) ainda não enviadas. Conecte-se antes de sair.</p>
      <button class="btn btn-contorno" :disabled="!!fila.length" @click="deslogar">Sair</button>
    </div>
  </section>
</template>

<style scoped>
.tela { padding: 0 16px 24px; }
.cab { height: 64px; display: flex; align-items: center; }
.form { display: flex; flex-direction: column; gap: 16px; }
.sub { margin: 8px 0 0; font-size: 13px; font-weight: 900; letter-spacing: 2px; color: var(--texto-3); text-transform: uppercase; }
.foto { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
.foto .sub { margin: 0; }
.foto-linha { display: flex; align-items: center; gap: 16px; }
.bolinha { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: var(--cinza); }
.bolinha img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-acoes { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.foto-acoes .btn { min-height: 48px; font-size: 13px; }
.foto-acoes label:focus-within { outline: 3px solid var(--destaque-texto); outline-offset: 2px; }
.desligado { opacity: .45; cursor: not-allowed; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.aparencia { margin-top: 32px; }
.tema { margin-top: 12px; min-height: 48px; display: flex; align-items: center; gap: 12px; font-weight: 700; }
.tema input { width: 24px; height: 24px; accent-color: var(--destaque); }
.sair { margin-top: 32px; display: flex; flex-direction: column; gap: 8px; }
</style>
