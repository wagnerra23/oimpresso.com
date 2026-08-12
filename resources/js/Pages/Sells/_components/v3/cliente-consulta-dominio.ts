/**
 * Domínio da consulta de clientes — extra do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-create.jsx:515` (o modal de 880px).
 * A âncora de design está declarada em `CreateV3.charter.md::related_prototype`.
 *
 * Arquivo separado do componente **de propósito**: `react-refresh/only-export-components`
 * reprova quando um módulo exporta componente E constante/função no mesmo lugar, e a
 * catraca `lint:baseline:check` mordeu exatamente isso na onda 2. Separar também deixa
 * a regra testável sem montar React.
 *
 * ⚠️ TIER 0 — VALOR: **nada aqui calcula dinheiro, e trocar de cliente não reprecifica.**
 * Medido nesta onda (`grep` em `CreateV3.tsx`): `tabelaCadastro`/`tabelaAtiva`/`tabelaTrocada`
 * aparecem SÓ no cartão "Tabela de preço" — nunca em `linhaTotal`, `subtotal` ou `total`.
 * O preço unitário mora em `itens`, que a seleção não toca. Então escolher outro cliente
 * muda o que a tela DIZ (tabela do cadastro, grupo, prazo) e não muda nenhum número.
 * Isso é deliberado: a lápide de 2026-07-15 (o `CustomerSearchAutocomplete` **entrega**
 * `selling_price_group_id` no `onSelect`, e o parent `Sells/Create.tsx:500` reaplica via
 * `handlePriceGroupChange`) é exatamente o caminho que esta onda não anda.
 * `ClienteConsulta` nem carrega campo de preço — a ausência é a defesa.
 */

/** A linha da consulta é o cadastro inteiro: quem escolhe traz tudo junto. */
export type ClienteConsulta = {
  cod: string;
  nome: string;
  padrao: boolean;
  doc: string;
  ie: string;
  contrib: 'sim' | 'isento' | 'nao';
  regime: string;
  fone: string;
  email: string;
  emailNfe: string;
  contato: string;
  endereco: string;
  cidade: string;
  uf: string;
  grupo: string;
  prazo: string;
  /** `null` = sem tabela no cadastro → vale o padrão do balcão. */
  tabela: string | null;
};

/**
 * Grupos de preço do cadastro mínimo.
 *
 * Enumeração de domínio, e por isso mora aqui e não na cena — mesma divisão que
 * `entrega-dominio.ts` já usa (`UFS`/`MODALIDADES` são const; as transportadoras,
 * que são REGISTROS, vêm do controller). Derivar a lista dos clientes existentes
 * seria pior: um business sem cliente Governo perderia a opção de cadastrar um.
 */
export const GRUPO_PADRAO = 'Varejo';

export const GRUPOS_DE_PRECO = [GRUPO_PADRAO, 'Atacado', 'Governo'];

/* ─── rótulos de ICMS ─────────────────────────────────────────────────────── */

/**
 * O mesmo estado em três estados, com DUAS grafias — e é de propósito.
 * A tabela da consulta é densa e usa a forma curta (`não contrib.`); a grade de
 * detalhes do destinatário tem espaço e usa a longa (`Não contribuinte`). O
 * protótipo faz essa distinção nos dois lugares, então ela é contrato, não desleixo.
 */
export function rotuloIcmsCurto(contrib: ClienteConsulta['contrib']): string {
  return contrib === 'sim' ? 'contribuinte' : contrib === 'isento' ? 'isento' : 'não contrib.';
}

export function rotuloIcmsLongo(contrib: ClienteConsulta['contrib']): string {
  return contrib === 'sim' ? 'Contribuinte' : contrib === 'isento' ? 'Isento' : 'Não contribuinte';
}

/** Tom do `Pill` — `success` contribui, `warning` é isento, neutro o resto. */
export function tomIcms(contrib: ClienteConsulta['contrib']): 'success' | 'warning' | 'neutro' {
  return contrib === 'sim' ? 'success' : contrib === 'isento' ? 'warning' : 'neutro';
}

/* ─── busca ───────────────────────────────────────────────────────────────── */

/**
 * Sem acento e em minúscula — `Itajaí` tem de casar com `itajai`.
 *
 * `\p{M}` (categoria Mark) e não um range de caracteres: depois do `NFD` o acento
 * vira marca combinante, e escrever essas marcas literais no arquivo deixa bytes
 * invisíveis no editor, que somem no primeiro salvamento com normalização errada.
 * A classe nomeada diz o que faz e sobrevive a qualquer encoding.
 */
function semAcento(s: string): string {
  return s.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}

/** Só os dígitos — documento com máscara tem de casar com o termo digitado sem ela. */
function soDigitos(s: string): string {
  return s.replace(/\D/g, '');
}

/**
 * Filtro da consulta: código, nome/razão social, CNPJ/CPF ou cidade.
 *
 * ⚠️ **Divergência CONSCIENTE do protótipo, e ela cumpre a promessa que o protótipo faz.**
 * A fonte compara `(cod + nome + doc + cidade).toLowerCase().includes(termo)` — literal.
 * Com isso o placeholder promete "Buscar por nome, CNPJ/CPF, cidade ou código…" e então
 * NÃO acha `83169623` (o operador digita o documento sem máscara, que é como ele vem do
 * papel) nem `itajai` (sem acento). Aqui o documento casa por dígito e o texto casa sem
 * acento — a busca passa a fazer o que a própria copy diz. O que não muda: continua
 * `includes`, não fuzzy; termo vazio devolve a lista inteira; e a ordem é preservada.
 */
export function filtrarClientes(lista: ClienteConsulta[], termo: string): ClienteConsulta[] {
  const q = termo.trim();
  if (!q) return lista;

  const texto = semAcento(q);
  const digitos = soDigitos(q);

  return lista.filter((c) => {
    const alvo = semAcento(`${c.cod} ${c.nome} ${c.doc} ${c.cidade} ${c.uf}`);
    if (alvo.includes(texto)) return true;
    // só cai no eixo numérico quando o termo TEM dígito — senão `''.includes` casaria tudo
    return digitos.length > 0 && soDigitos(`${c.cod}${c.doc}`).includes(digitos);
  });
}

/* ─── cadastro mínimo ─────────────────────────────────────────────────────── */

/** O que o formulário "Novo cliente — sem sair da venda" realmente pede. */
export type CadastroMinimo = {
  nome: string;
  doc?: string;
  fone?: string;
  grupo?: string;
};

/** Só o nome é obrigatório — é o que o protótipo marca no campo. */
export function cadastroMinimoValido(dados: CadastroMinimo): boolean {
  return dados.nome.trim().length > 0;
}

/**
 * Próximo código livre, com a largura preservada (`0288` → `0289`, nunca `289`).
 * Lista vazia começa em `0001`.
 */
export function proximoCodigo(lista: ClienteConsulta[]): string {
  const numeros = lista.map((c) => Number(soDigitos(c.cod)) || 0);
  const largura = lista.reduce((m, c) => Math.max(m, c.cod.length), 4);
  return String(Math.max(0, ...numeros) + 1).padStart(largura, '0');
}

/**
 * Monta o cadastro a partir do mínimo. O que não foi perguntado entra como `—`,
 * **nunca inventado** — campo em branco é honesto, dado fabricado vira NF-e errada.
 *
 * ⚠️ `tabela: null` é decisão, não descuido: cliente novo NÃO nasce com tabela de
 * preço, então cai no padrão do balcão. Inventar tabela aqui seria reprecificar a
 * venda por um cadastro que ninguém conferiu — o caminho que o cabeçalho recusa.
 */
export function criarClienteDeCadastroMinimo(
  dados: CadastroMinimo,
  lista: ClienteConsulta[],
): ClienteConsulta {
  const nome = dados.nome.trim();
  const doc = (dados.doc ?? '').trim();

  return {
    cod: proximoCodigo(lista),
    nome,
    padrao: false,
    doc: doc || '—',
    ie: 'ISENTO',
    // sem documento não dá pra afirmar situação de ICMS — o menos comprometido é `nao`
    contrib: 'nao',
    regime: '—',
    fone: (dados.fone ?? '').trim() || '—',
    email: '—',
    emailNfe: '—',
    contato: nome,
    endereco: 'Sem endereço no cadastro — informe na entrega',
    cidade: '—',
    uf: '—',
    grupo: dados.grupo ?? GRUPO_PADRAO,
    prazo: 'À vista',
    tabela: null,
  };
}
