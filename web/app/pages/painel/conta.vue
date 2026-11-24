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
    <header class="bloco"><h1 class="titulo-pagina">Conta</h1></header>

    <section class="bloco foto" aria-labelledby="titulo-foto">
      <h2 id="titulo-foto" class="bloco-titulo">Foto do perfil</h2>
      <div class="foto-linha">
        <div class="bolinha">
          <img v-if="vendedor?.foto_url" :src="vendedor.foto_url" alt="" width="96" height="96">
        </div>
        <div class="foto-acoes">
          <label class="btn btn-cinza-claro" :class="{ desligado: enviandoFoto || !online }">
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
      <div class="bloco">
      <h2 class="bloco-titulo">Dados</h2>
      <label class="campo">Nome
        <input v-model="form.nome" required maxlength="255">
      </label>
      <label class="campo">Telefone
        <input v-model="form.telefone" type="tel" maxlength="30">
      </label>

      </div>
      <div class="bloco">
      <h2 class="bloco-titulo">Página pública</h2>
      <label class="campo">Endereço da página
        <input v-model="form.slug" maxlength="60" autocapitalize="off" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="pao-de-queijo-do-joao">
        <small>Só letras minúsculas, números e hífen. Link: {{ form.slug ? `/v/${form.slug}` : '—' }}</small>
      </label>
      <label class="campo">Descrição
        <textarea v-model="form.descricao" maxlength="2000" placeholder="O que você vende, onde costuma estar…" />
      </label>

      </div>
      <div class="bloco">
      <h2 class="bloco-titulo">Reservas</h2>
      <div class="dois">
        <label class="campo">Taxa de entrega (R$)
          <input v-model="form.taxa_entrega" inputmode="decimal">
        </label>
        <label class="campo">Prazo p/ responder (min)
          <input v-model="form.prazo_resposta_min" type="number" inputmode="numeric" min="1" max="240">
        </label>
      </div>

      </div>
      <div class="bloco">
      <h2 class="bloco-titulo">Pix</h2>
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

      </div>
      <div class="bloco">
      <h2 class="bloco-titulo">Cartão</h2>
      <label class="campo">Como você cobra no cartão?
        <select v-model="form.provedor_cartao">
          <option value="">Não aceito cartão / não informado</option>
          <option value="infinitepay">InfinitePay (abre o app com o valor)</option>
          <option value="mercadopago_point">Mercado Pago Point</option>
          <option value="outro">Outra maquininha</option>
        </select>
      </label>
      </div>

      <p v-if="erro" class="erro nota" role="alert">{{ erro }}</p>
      <p v-if="ok" class="dica nota" role="status">Salvo.</p>
      <button class="btn btn-destaque btn-seta" :disabled="salvando || !online">
        {{ salvando ? 'Salvando…' : 'Salvar' }}
        <span class="bolinha-seta"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg></span>
      </button>
    </form>

    <section class="bloco">
      <h2 class="bloco-titulo">Aparência</h2>
      <span class="dica">Cor do app: muda a cor de destaque em todas as telas deste aparelho.</span>
      <SeletorCor />
      <label class="linha-bloco tema">
        <input type="checkbox" :checked="escuro" @change="alternarTema">
        Modo escuro
      </label>
    </section>

    <div class="sair">
      <p v-if="fila.length" class="dica">Há {{ fila.length }} venda(s) ainda não enviadas. Conecte-se antes de sair.</p>
      <button class="btn btn-contorno" :disabled="!!fila.length" @click="deslogar">Sair</button>
    </div>
  </section>
</template>

<style scoped>
.tela { display: flex; flex-direction: column; gap: 8px; }
.titulo-pagina { margin: 0; font-size: 32px; font-weight: 600; letter-spacing: -1px; line-height: 1.05; }
.form { display: flex; flex-direction: column; gap: 8px; }
.nota { padding: 0 8px; }
.btn-seta .bolinha-seta { width: 48px; height: 48px; border-radius: 50%; background: #111111; color: var(--destaque); display: flex; align-items: center; justify-content: center; }
.foto-linha { display: flex; align-items: center; gap: 16px; }
.bolinha { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: var(--cinza); }
.bolinha img { width: 100%; height: 100%; object-fit: cover; display: block; }
.foto-acoes { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.foto-acoes .btn { min-height: 48px; font-size: 14px; }
.foto-acoes label:focus-within { outline: 3px solid var(--destaque-texto); outline-offset: 2px; }
.btn-cinza-claro { background: var(--fundo); color: var(--texto); }
.desligado { opacity: .45; cursor: not-allowed; }
.dois { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.tema { min-height: 56px; padding: 0 18px; font-weight: 600; cursor: pointer; }
.tema input { width: 24px; height: 24px; accent-color: var(--destaque); }
.sair { display: flex; flex-direction: column; gap: 8px; }
.sair .dica { padding: 0 8px; }
</style>
