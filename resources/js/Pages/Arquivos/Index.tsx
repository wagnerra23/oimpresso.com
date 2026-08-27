// @arquivos
//   tela: /arquivos
//   adrs: 0123 (Modules/Arquivos DMS backbone), 0093 (multi-tenant Tier 0), 0360 (Admin Center deprecado)
//   spec: memory/requisitos/Arquivos/SPEC.md US-ARQ-013
//   runbook: memory/requisitos/Arquivos/RUNBOOK-index.md
//   charter: ./Index.charter.md · casos: ./Index.casos.md
//
// ONDA 1 COMPLETA — as 4 vistas do charter: ACERVO · RETENÇÃO · COFRE · TRILHA.
// Leitura pura: nenhum caminho aqui escreve, apaga ou dispara job. A Retenção MOSTRA o que
// o `arquivos:retention-cleanup` faria; rodá-lo pela tela, avisar titular e purgar são a
// onda 3, que depende da proposta de ADR `arquivos-retencao-ui-aviso-titular`.
//
// A BARRA DE ABAS nasceu no PR-2, com a segunda vista — não antes: aba que não leva a
// lugar nenhum é promessa, não navegação. Ela navega por rota (`?tab=`), como Financeiro,
// Fiscal/Dfe e Cliente — não por estado local, senão o link não é compartilhável e o
// botão voltar do navegador mente. A ordem das abas é a do protótipo (acervo · retenção ·
// cofre · trilha), com o buraco da retenção guardado no lugar em vez de emendado no fim.
//
// Layout por PRIMITIVOS (ADR 0253): nada de `<div className="flex gap-4">` solto — o
// layout-primitives-guard é catraca e reprova adotante novo.

// Bundle Cowork do modulo (ETAPA 1 — proibicoes.md Tier 0 "Design System / Pacote Cowork
// novo"): o CSS desce e o build o carrega ANTES de qualquer classe ser usada. A tela
// segue DS canon; aplicar o visual `.arq-*` e PR proprio, com smoke separado.
import '../../../css/cowork-arquivos-bundle.css'

import { Deferred, Link, router } from '@inertiajs/react'
import { Download, File } from 'lucide-react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs'
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable'
import EmptyState from '@/Components/shared/EmptyState'
import { Stack, Inline, Grid, Box } from '@/Components/layout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import StatusBadge from '@/Components/shared/StatusBadge'
import { Skeleton } from '@/Components/ui/skeleton'
import type { ColumnDef } from '@tanstack/react-table'
import type { ReactNode } from 'react'

interface LinhaAcervo {
  id: number
  nome: string
  sub_destination: string | null
  bucket: string | null
  visibility: string | null
  disk: string | null
  encrypted: boolean
  size_bytes: number
  classified_by: string | null
  orfao: boolean
  /** Nome da classe Eloquent (`ServiceOrder`) — valor TÉCNICO, vai na sub-linha em mono. */
  dono_tipo: string | null
  dono_id: number | null
  /** Nome de NEGÓCIO do dono (`Ordem de serviço`). Cai no `dono_tipo` cru se o tipo não tem entrada no mapa do servidor. */
  dono_rotulo: string | null
  /** Tela do dono. `null` = o servidor não achou rota provada pra este tipo — vira texto, nunca link morto. */
  dono_url: string | null
  /** Link assinado de 60 min pro `DownloadController`. `null` = nada a servir (apagado, ou sem conteúdo no storage). */
  download_url: string | null
  vence_em: string | null
  dias_restantes: number | null
  excluido_em: string | null
}

/** Uma linha de `arquivos_audit_log`. O arquivo é `#id` — nunca o nome (ver charter). */
interface LinhaTrilha {
  id: number
  quando: string
  acao: string
  arquivo: number
  quem: string | null
  detalhe: string | null
}

/** Faceta de ação: só existe chip pra ação que este business realmente registrou. */
interface FacetaAcao {
  acao: string
  total: number
}

interface Politica {
  sub: string
  dias: number
  lei: string
}

interface Filtros {
  tab: 'acervo' | 'trilha' | 'cofre' | 'retencao'
  bucket: string | null
  owner_type: string | null
  mime: string | null
  from: string | null
  to: string | null
  q: string | null
  acao: string | null
  with_trashed: boolean
  per_page: number
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  links: Array<{ url: string | null; label: string; active: boolean }>
}

interface TrilhaPayload {
  eventos: Paginator<LinhaTrilha>
  acoes: FacetaAcao[]
}

/** Um disco do acervo. Os discos saem de `GROUP BY` — não há lista escrita no servidor. */
interface Disco {
  disco: string
  arquivos: number
  bytes: number
  cifrados: number
}

/** Um achado do cofre. `exemplos` é amostra (5), nunca a lista inteira — a lista é o acervo. */
interface AchadoPayload<T> {
  total: number
  exemplos: T[]
}

interface GrupoDuplicado {
  copias: number
  /** Caminhos de storage distintos no grupo. 1 = registro repetido; >1 = disco ocupado 2×. */
  caminhos: number
  bytes: number
  nomes: string[]
}

interface CofrePayload {
  /** `false` = não foi possível medir (sem tenant na sessão, ou módulo sem migrate). */
  disponivel: boolean
  cap_mb: number
  discos: Disco[]
  acima_do_cap: AchadoPayload<{ id: number; nome: string; bytes: number; disco: string | null; cifrado: boolean }>
  orfaos: AchadoPayload<{ id: number; nome: string; bytes: number }>
  duplicados: { grupos: number; registros: number; truncado: boolean; exemplos: GrupoDuplicado[] }
}

/**
 * Resumo do acervo do business — eager, e é o que alimenta o subtítulo e os contadores das
 * abas. Não é o número da PÁGINA: é o do acervo inteiro, como o protótipo mostra.
 */
interface Resumo {
  arquivos: number
  bytes: number
  cifrados: number
  eventos: number
  por_bucket: Record<string, number>
}

/** A 4ª vista: o que vence, o que está no grace, o que passou do prazo. Leitura pura. */
interface RetencaoPayload {
  disponivel: boolean
  grace_dias: number
  notice_dias: number
  estrategia: string
  /** O `arquivos:retention-cleanup` está agendado? Medido no runtime, não deduzido do fonte. */
  agendado: boolean
  kpis: { vence_30: number; vence_90: number; no_grace: number; passou_do_prazo: number }
  por_contexto: Record<string, number>
}

interface Props {
  filtros: Filtros
  politica: Politica[]
  resumo: Resumo
  /** Só chega quando `tab=acervo` — a vista fechada não é computada no servidor. */
  acervo?: Paginator<LinhaAcervo>
  /** Só chega quando `tab=trilha`. */
  trilha?: TrilhaPayload
  /** Só chega quando `tab=cofre`. */
  cofre?: CofrePayload
  /** Só chega quando `tab=retencao`. */
  retencao?: RetencaoPayload
}

/** Os buckets que o Curador de fato grava (`CuradorEngine`), na ordem em que interessam a
 *  quem responde por conformidade. `common` e `public` NAO existem — vieram de um palpite meu
 *  e filtravam por valor que o banco nao tem, entao a lista voltava sempre vazia (medido no
 *  smoke de producao, 2026-08-25). O enum do banco tem 7; `user`/`spec`/`ambiguous` nao sao
 *  escritos por nenhum caminho vivo, entao ficam de fora do filtro ate que sejam. */
const BUCKETS = ['sensitive', 'active', 'memory', 'discard'] as const

/**
 * Rótulo PT-BR do bucket. **O valor do enum é do BANCO, não da tela** — quem lê "Sensível"
 * na tela vê o técnico (`sensitive`) no `title`, que é onde ele serve pra depurar.
 *
 * Portado do protótipo vivo, lido do projeto Cowork em 2026-08-25. A produção mostrava o
 * valor cru, o que era fiel ao espelho da época mas viola o charter ("PT-BR em todo
 * label/placeholder/mensagem") — o protótipo ganhou os rótulos depois, e o espelho não
 * acompanhou porque o bundle dele é de 24/08.
 */
const BUCKET_PT: Record<string, string> = {
  sensitive: 'Sensível',
  active: 'Em uso',
  memory: 'Histórico',
  discard: 'Descartar',
}

/** Visibilidade é outro eixo — QUEM vê, não o que é. Mesmo tratamento: PT-BR na tela, enum no title. */
const VIS_PT: Record<string, string> = {
  private: 'Restrito',
  internal: 'Equipe',
  business: 'Equipe',
  public: 'Aberto',
}

/**
 * Rótulo PT-BR do contexto de retenção (`sub_destination`).
 *
 * A lei aqui é o **charter** (L88 — "PT-BR em todo label/placeholder/mensagem") e o protótipo,
 * que desenha rótulo + `sub` em mono na sub-linha. Mesmo tratamento de bucket, visibilidade e
 * disco: negócio na tela, técnico ao lado.
 *
 * ⚠️ **NÃO conte com a `ds/no-db-jargon-in-ui` pra defender isto.** Medido em 2026-08-26: o
 * seletor dela casa `JSXText` LITERAL e exclui `<code>`/`<pre>`/`<kbd>` por opção declarada — e
 * o valor aqui é data-driven dentro de `<code>`. Ela não pegava o jargão cru que existia antes
 * desta linha e não vai pegar a regressão. A defesa deste rótulo é humana, não mecânica.
 *
 * As chaves são as do `entities` de `Modules/Arquivos/Config/retention.php`, que é o dono do
 * vocabulário: contexto novo cadastrado lá aparece com o valor cru, nunca some.
 */
const CONTEXTO_PT: Record<string, string> = {
  'nfe-xml': 'XML de NF-e',
  'nfse-xml': 'XML de NFS-e',
  'documentos-fiscais': 'Documentos fiscais',
  contratos: 'Contratos',
  'repair-foto': 'Foto de reparo',
  'os-anexo': 'Anexo de OS',
  'ticket-anexo': 'Anexo de ticket',
  default: 'Sem contexto mapeado',
}

/**
 * Rótulo PT-BR da estratégia de expurgo (`retention.strategy`).
 *
 * As duas chaves são as que `Modules/Arquivos/Config/retention.php` admite — conferidas contra
 * o config, não supostas. Mesmo desenho do `CONTEXTO_PT` e do `ACAO_PT`: o dono do vocabulário
 * é o config, então estratégia nova aparece com o valor cru em vez de sumir da tela.
 */
const ESTRATEGIA_PT: Record<string, string> = {
  hard_delete: 'Apagar de verdade',
  anonymize: 'Anonimizar',
}

/**
 * Rótulo PT-BR da ação da trilha.
 *
 * ⚠️ **O fallback não é detalhe: é o que mantém a regra do vocabulário.** O dono das ações é
 * o ENUM da coluna, que já cresceu 2× por migration — ação nova aparece com o valor cru, em
 * vez de sumir ou quebrar a tela. Traduzir o que se conhece e mostrar o resto como veio é o
 * oposto de restatear a lista em PHP.
 */
const ACAO_PT: Record<string, string> = {
  upload: 'Envio',
  download: 'Baixa',
  classify: 'Classificação',
  reclassify: 'Reclassificação',
  soft_delete: 'Exclusão',
  restore: 'Restauração',
  hard_delete: 'Exclusão definitiva',
  signed_url: 'Link assinado',
  signed_url_issued: 'Link assinado',
  signed_url_consumed: 'Link consumido',
  exported: 'Exportação',
  anonymize: 'Anonimização',
  notice: 'Aviso ao titular',
}

/**
 * Estado do prazo de guarda → `kind` do `StatusBadge` canon.
 *
 * As fronteiras (0 e 30 dias) são as MESMAS que a coluna já usava pra decidir a cor do texto;
 * o que muda é a forma (pílula em vez de texto colorido), que é o que o protótipo desenha.
 */
function estadoDoPrazo(dias: number | null): 'no_prazo' | 'vencendo' | 'vencido' {
  if (dias === null) return 'no_prazo'
  if (dias <= 0) return 'vencido'
  return dias <= 30 ? 'vencendo' : 'no_prazo'
}

/**
 * Tom por ação — espelha o mapa `ACAO` do protótipo (`arquivos-page.jsx`), traduzido
 * pras variantes de status do Badge canon.
 *
 * O `default` existe porque o vocabulário é do ENUM da coluna, que já cresceu 2× por
 * migration: ação nova aparece em cinza, nunca quebra a tela.
 */
const TOM_ACAO: Record<string, 'info' | 'neutral' | 'warning' | 'success' | 'danger'> = {
  upload: 'info',
  download: 'neutral',
  classify: 'info',
  reclassify: 'info',
  soft_delete: 'warning',
  restore: 'success',
  hard_delete: 'danger',
  signed_url_issued: 'warning',
  signed_url_consumed: 'warning',
  exported: 'warning',
}

function tamanho(bytes: number): string {
  if (bytes >= 1_073_741_824) return `${(bytes / 1_073_741_824).toFixed(2).replace('.', ',')} GB`
  if (bytes >= 1_048_576) return `${Math.round(bytes / 1_048_576)} MB`
  return `${Math.round(bytes / 1024)} KB`
}

/**
 * ISO do servidor → `dd/mm/aaaa`. O ISO fica no `title`, pra depurar.
 *
 * Mesmo tratamento que bucket, visibilidade, disco e contexto já recebiam: negócio na tela,
 * técnico ao lado. A data escapou da regra porque chega pronta do backend e "parecia" texto
 * de negócio — mas `2026-09-07` é formato de máquina, e o protótipo desenha `dd/mm/aaaa`
 * (medido: 10 datas na vista Acervo dele, zero em ISO).
 *
 * Sem `Date`: a string do servidor já é a data de negócio, e passá-la por `new Date()` a
 * reinterpretaria em UTC — o mesmo tipo de deslocamento que o `format_date` legado carrega
 * de propósito (ADR 0066). Fatiar não desloca nada.
 */
function dataBR(iso: string): string {
  const d = iso.split(' ')[0] ?? iso
  const [a, m, dia] = d.split('-')
  return dia && m && a ? `${dia}/${m}/${a}` : iso
}

/** `2026-06-09 18:08:31` → `09/06/2026 18:08`. Segundos não decidem nada numa trilha. */
function dataHoraBR(iso: string): string {
  const [d, h] = iso.split(' ')
  if (!d) return iso
  return h ? `${dataBR(d)} ${h.slice(0, 5)}` : dataBR(d)
}

/**
 * `size=207560` → `203 KB`.
 *
 * O `detalhe` do log é `key=value`, que é formato de LOG, não de tela. Só o par `size` é
 * traduzido: o dono do vocabulário é a coluna `detalhe` do `arquivos_audit_log`, não este
 * mapa — par desconhecido passa como veio, e o cru fica sempre no `title`.
 */
function detalheBR(detalhe: string): string {
  const m = detalhe.match(/^size=(\d+)$/)
  return m ? tamanho(Number(m[1])) : detalhe
}

/** Prazo NUNCA aparece sozinho: o charter exige a lei ao lado — número sozinho não ensina o domínio. */
function leiDe(sub: string | null, politica: Politica[]): string | null {
  if (!sub) return null
  return politica.find((p) => p.sub === sub)?.lei ?? null
}

/** `filters` do DataTable aceita string|number|null|undefined — `with_trashed` e boolean,
 *  entao vira 1/undefined (o mesmo que a ListArquivosRequest ja aceita como boolean). */
function paraQuery(f: Filtros): Record<string, string | number | null | undefined> {
  return {
    bucket: f.bucket,
    owner_type: f.owner_type,
    mime: f.mime,
    from: f.from,
    to: f.to,
    q: f.q,
    per_page: f.per_page,
    with_trashed: f.with_trashed ? 1 : undefined,
  }
}

/**
 * Query dos chips de navegação.
 *
 * `with_trashed` NÃO pode viajar como boolean cru, e isto foi MEDIDO (2026-08-25), não
 * deduzido: `qs.stringify({with_trashed:false})` — o serializador do Inertia — produz
 * `with_trashed=false`, e a regra `boolean` do Laravel **reprova a string "false"**
 * (aceita só `true|false` nativos, `0|1` e `"0"|"1"`). O `paraQuery` do DataTable já
 * normalizava pra `1|undefined`; o caminho dos chips não, então clicar num chip de bucket
 * voltava com erro de validação em vez de filtrar. Uma normalização só pros dois.
 */
type ValorDeQuery = string | number | null | undefined

function paraNavegacao(f: Filtros, patch: Record<string, ValorDeQuery>): Record<string, ValorDeQuery> {
  return {
    ...f,
    with_trashed: f.with_trashed ? 1 : undefined,
    ...patch,
  }
}

/** A paginação da trilha precisa carregar `tab` — sem ele, a página 2 volta pro acervo. */
function paraQueryTrilha(f: Filtros): Record<string, string | number | null | undefined> {
  return {
    tab: 'trilha',
    acao: f.acao,
    from: f.from,
    to: f.to,
    per_page: f.per_page,
  }
}

/**
 * Chip de filtro — ETAPA 2 do bundle (`cowork-arquivos-bundle.css`).
 *
 * O bundle desceu em 2026-08-25 declarando "ETAPA 1 de 2 — a tela AINDA NÃO usa as classes
 * `.arq-*`", e a etapa 2 não veio: o CSS viajou morto no build desde então, e o chip inativo
 * ficou sem fundo e sem borda, transparente sobre o card. `.arq-chip` traz os três estados
 * (superfície + borda + pill 999px, hover na borda accent, `.active` em accent 8%).
 *
 * ⚠️ NADA de utilitária de cor/espaço aqui junto. O `cowork-arquivos-bundle.css` entra
 * UNLAYERED (`@import` sem `@layer`) e as utilitárias do Tailwind v4 vivem em
 * `@layer utilities` — unlayered vence layered sem olhar especificidade. Um `bg-primary/10`
 * que sobrasse nesta tag morreria em silêncio, e quem depurasse ia procurar especificidade.
 * É o mesmo mecanismo que comeu o `pl-9` da lupa (documentado no `DataTable.tsx`).
 * Histórico do que estas classes substituem: o helper anterior pintava `bg-primary/10` no
 * ativo e deixava o inativo sem fundo nenhum.
 */
const chip = (ativo: boolean) => (ativo ? 'arq-chip active' : 'arq-chip')

/**
 * Conteúdo servível — a distinção que o payload NÃO manda como campo e que a linha precisa.
 *
 * `download_url: null` significa "nada a servir", e isso tem duas causas com consequências
 * opostas: **apagado** (soft-delete; o `DownloadController` faz `find()` sem `withTrashed`,
 * mas `size_bytes` continua descrevendo um arquivo que existe) e **anonimizado** (o que a
 * estratégia `anonymize` da retenção produz: o conteúdo foi embora e `size_bytes` deixou de
 * descrever qualquer coisa). O `excluido_em` separa os dois — é a mesma leitura que o comentário
 * da coluna de ações já fazia, agora com nome.
 *
 * Derivado, não inventado: se um dia o servidor mandar o estado explícito, estas duas funções
 * são o único lugar a trocar.
 */
const anonimizado = (a: LinhaAcervo) => a.download_url === null && a.excluido_em === null
const semConteudo = (a: LinhaAcervo) => a.download_url === null

/**
 * Estado visual da linha — o `rows[].state` do protótipo (`!dono || restam <= 30 ? urgent :
 * anon ? archived : undefined`), traduzido pros campos que o servidor manda.
 *
 * `urgent` (trilha vermelha) é ACHADO: órfão — ninguém alcança o arquivo pela tela do dono — ou
 * prazo apertando. `archived` (esmaecido) é o oposto: a linha não é acionável, nada a baixar.
 * A ordem importa e é a do protótipo: urgente ganha de arquivado.
 */
function estadoDaLinha(a: LinhaAcervo): EstadoDaLinha | undefined {
  if (a.orfao || (a.dias_restantes !== null && a.dias_restantes <= 30)) return 'urgent'
  if (semConteudo(a)) return 'archived'
  return undefined
}

/**
 * GEOMETRIA — as larguras, o alinhamento e o `mono` que o protótipo declara em
 * `columns[] = { width, align, mono }`.
 *
 * Até 2026-08-27 nenhuma das 7 colunas declarava geometria, e não por decisão: o `ColumnDef`
 * do TanStack não tinha onde pousar esses campos, então eles eram descartados em silêncio na
 * travessia. O `meta` agora existe (ver `DataTable.tsx`) e vira `<colgroup>` + `table-layout:
 * fixed`, que é a única forma que o navegador respeita.
 *
 * `arquivo` fica SEM largura de propósito — é a coluna fluida, que absorve a sobra. Era ela
 * que estava sendo roubada: sem largura declarada em ninguém, o `Vinculado a` esticava em
 * `nowrap` ou colapsava o `truncate` em reticências (os "tracinhos"), porque `truncate` é
 * `overflow:hidden` e só funciona contra uma largura que alguém declarou.
 */
function colunas(politica: Politica[]): ColumnDef<LinhaAcervo, unknown>[] {
  return [
    {
      id: 'arquivo',
      header: 'Arquivo',
      cell: ({ row }) => {
        const a = row.original
        const lei = leiDe(a.sub_destination, politica)
        return (
          // ETAPA 2 — `.arq-file`/`.arq-file-ic`/`.arq-file-m` do bundle, no lugar do
          // `Stack` + utilitarias. O glyph (o "plate" de 30px com o icone de arquivo) e o que
          // da a leitura de LINHA DE ACERVO, e nunca tinha sido portado: a celula era so nome
          // + sub-linha. Nao foi decisao declarada em lugar nenhum — sumiu na travessia.
          //
          // `File` do lucide a 15px, exatamente o tamanho do `IcFile` do prototipo, dentro do
          // plate mudo (`aria-hidden`): ele nao acrescenta informacao pra quem usa leitor de
          // tela, o nome ao lado ja diz o que e.
          //
          // ⚠️ Uma familia por tag: `.arq-file-m b` e `small` ja definem tamanho e cor. O
          // `break-words` fica porque nao e cor nem espaco — sob `table-layout: fixed` a
          // celula nao cresce, e nome longo sem espaco vazaria; `truncate` aqui esconderia o
          // nome, que e a informacao principal da linha.
          <span className="arq-file">
            <span className="arq-file-ic" aria-hidden="true">
              <File size={15} />
            </span>
            <span className="arq-file-m">
            <b className="break-words">{a.nome}</b>
            <small>
              {/* Mesmo vocabulário da Retenção, que já usava o `CONTEXTO_PT`: rótulo PT-BR na
                  tela, slug no `title`. Antes o slug ia cru dentro de `<code>` — e `<code>` é
                  pra valor técnico, não pra prosa como "sem contexto". */}
              <span title={a.sub_destination ?? undefined}>
                {a.sub_destination
                  ? (CONTEXTO_PT[a.sub_destination] ?? a.sub_destination)
                  : 'Sem contexto mapeado'}
              </span>
              {lei ? <> · {lei}</> : null}
              {/* `no_rule_matched` e o fallback do CuradorEngine quando nenhuma regra casa
                  (CuradorEngine:248) — nao e um classificador, e a ausencia de um. Mostrar o
                  identificador tecnico na tela foi o que o smoke de producao pegou.
                  A ausência só INFORMA quando há contexto: sem contexto, "sem classificação
                  humana" ao lado de "Sem contexto mapeado" são duas ausências dizendo a mesma
                  coisa — era a sub-linha inteira do caso mais comum do acervo. */}
              {a.classified_by && a.classified_by !== 'no_rule_matched' ? (
                <> · classificado por {a.classified_by}</>
              ) : a.sub_destination ? (
                <> · sem classificação humana</>
              ) : null}
            </small>
            </span>
          </span>
        )
      },
    },
    {
      id: 'dono',
      // 160px — a largura que o protótipo declara. É ela que faz o `truncate` das duas linhas
      // abaixo FUNCIONAR: sem largura declarada, `overflow:hidden` não tem contra o que cortar,
      // e o resultado era ou a coluna esticando em `nowrap` ou tudo virando reticências.
      meta: { width: 160 },
      // "Vinculado a" é a copy do protótipo vivo. O rótulo anterior veio do espelho de
      // 24/08 e foi portado fielmente na época — o protótipo renomeou depois, e o contrato
      // de tela (gerado da tela antiga) foi atualizado junto neste PR.
      //
      // ⚠️ Não repita aqui o rótulo antigo: o `contrato-de-tela` procura a copy como TEXTO
      // no arquivo, comentário incluído. Enquanto esta linha o citava, o gate achava a
      // string na prosa e passava verde com a tela já renomeada — falso-verde medido em
      // 2026-08-25, com canário (tirei a string do comentário e ele reprovou).
      header: 'Vinculado a',
      cell: ({ row }) => {
        const a = row.original
        // Órfão é ACHADO, não item de lista — o charter trata assim e o casos.md defende.
        if (a.orfao) {
          // `danger` (par SOFT), não `destructive` (fill sólido): o #6268 corrigiu 9 badges
          // de ESTADO no repo, e este é um deles — órfão é um estado do arquivo, não uma
          // ação destrutiva. Reaplicado à mão porque o merge por `--ours` descartou a
          // mudança da main junto com a versão antiga do resto do arquivo.
          return (
            <Badge variant="danger" title="Sem arquivable — ninguém alcança pela tela do dono.">
              órfão
            </Badge>
          )
        }
        // Duas linhas, como o protótipo desenha: o nome de NEGÓCIO em cima, o tipo TÉCNICO
        // embaixo em mono. A produção imprimia só `ServiceOrder #4` — que é o nome da classe
        // Eloquent, não o nome da coisa. O `dono_rotulo` vem do servidor; o mapa de tradução
        // e a prova de que a rota existe moram lá, onde o roteador pode ser perguntado.
        //
        // Link só quando o servidor mandou `dono_url` — e ele só manda pra tipo cuja rota foi
        // provada em `route:list`. Sem URL, o valor é texto: link morto é pior que texto.
        //
        // `<a>` cru, não `<Link>` do Inertia: os destinos moram em OUTROS módulos e nem todos
        // são páginas Inertia — uma visita XHR numa página Blade quebra. O protótipo usa
        // `<button>` porque o mock dele não tem roteador de verdade; aqui tem.
        // ETAPA 2 — `.arq-dono` (as duas linhas) e `.arq-dono-lk` (o link) do bundle.
        //
        // Os "tracinhos" que apareciam aqui NÃO viraram corte-com-largura-melhor: a classe de
        // corte SAIU. Ela nunca foi decisão de design — o protótipo deixa o texto quebrar. Ela
        // tinha sido portada sozinha, sem a `width: 160` que a sustenta, e `overflow:hidden`
        // sem largura declarada não tem contra o que cortar: ou a coluna esticava em `nowrap`
        // roubando a fluida, ou tudo virava reticências. Com a largura declarada (commit
        // anterior) e sem ela, o texto quebra — que é o que a fonte faz.
        //
        // `break-words` fica na sub-linha: nome de classe Eloquent não tem espaço onde quebrar,
        // e sem isso vazaria da célula de 160px. Não é cor nem espaço, então convive.
        return (
          <span className="arq-dono">
            {a.dono_url ? (
              <a
                href={a.dono_url}
                className="arq-dono-lk"
                title={`Abrir ${a.dono_rotulo ?? a.dono_tipo} #${a.dono_id}`}
              >
                {a.dono_rotulo ?? a.dono_tipo} #{a.dono_id}
              </a>
            ) : (
              <b>
                {a.dono_rotulo ?? a.dono_tipo} #{a.dono_id}
              </b>
            )}
            <small
              className="mono break-words"
              title="Tipo do arquivable (classe Eloquent)."
            >
              {a.dono_tipo}
            </small>
          </span>
        )
      },
    },
    {
      id: 'classificacao',
      header: 'Classificação',
      meta: { width: 120 },
      cell: ({ row }) => {
        const a = row.original
        // PT-BR na tela, valor do enum no `title`: quem opera lê a palavra, quem depura
        // passa o mouse. Bucket desconhecido cai no valor cru — nunca vira "—" mudo.
        // `danger` (par SOFT), não `destructive` (fill sólido), pelo mesmo motivo do órfão acima:
        // sensível é ESTADO do arquivo, não ação destrutiva — e numa lista onde a maioria das
        // linhas de NF-e/contrato é `sensitive`, o fill pintaria metade da coluna de vermelho cheio.
        return (
          <Stack gap={1} align="start">
            <Badge
              variant={a.bucket === 'sensitive' ? 'danger' : 'secondary'}
              title={a.bucket ?? undefined}
            >
              {a.bucket ? (BUCKET_PT[a.bucket] ?? a.bucket) : '—'}
            </Badge>
            <span className="text-xs text-muted-foreground" title={a.visibility ?? undefined}>
              {a.visibility ? (VIS_PT[a.visibility] ?? a.visibility) : '—'}
            </span>
          </Stack>
        )
      },
    },
    {
      id: 'disco',
      header: 'Disco',
      meta: { width: 88 },
      // A justificativa do `rotuloDisco()` já estava escrita ("o nome técnico do disco é
      // detalhe de infra"), mas só os cards do Cofre a aplicavam — a coluna ficou com o valor
      // cru e imprimia `arquivos`, que é o nome do disco Laravel do CT, não informação de
      // negócio. `vault` era enum pelo mesmo motivo.
      cell: ({ row }) =>
        row.original.encrypted ? (
          <span className="text-xs font-medium" title="vault">
            Cofre · cifrado
          </span>
        ) : (
          <span className="text-xs text-muted-foreground" title={row.original.disk ?? undefined}>
            {row.original.disk ? rotuloDisco(row.original.disk).titulo : '—'}
          </span>
        ),
    },
    {
      id: 'tamanho',
      header: 'Tamanho',
      // As três marcas que o protótipo declara nesta coluna — `width: 84`, `align: "right"`,
      // `mono: true` — agora ficam na COLUNA, que é onde valem.
      //
      // O `align` era o defeito mais fino dos três: ele estava escrito como `text-right` num
      // <span> DENTRO da célula. Isso empurra o texto pra direita só enquanto o span ocupa a
      // largura toda, e não alinha o <td> nem o <th> — o cabeçalho "Tamanho" ficava à esquerda
      // sobre números à direita. Sob `table-layout: fixed` o span nem ocupa mais a célula
      // inteira, então o `text-right` no filho vira decoração pura.
      meta: { width: 84, align: 'right', mono: true },
      // Anonimizado não mostra tamanho: a estratégia `anonymize` levou o conteúdo embora e
      // `size_bytes` deixou de descrever qualquer coisa. É a regra do protótipo (`a.anon ? "—"`),
      // e o travessão diz a verdade onde o número mentiria. Apagado é outro caso — lá o
      // arquivo existe e o número segue válido.
      cell: ({ row }) => (anonimizado(row.original) ? '—' : tamanho(row.original.size_bytes)),
    },
    {
      id: 'vence',
      header: 'Vence em',
      // 130px. SEM `mono: true` de coluna, e isso e fiel ao prototipo, nao esquecimento: la o
      // mono esta no <b> da DATA (`<b className="mono">`), nao na celula. O selo e a contagem
      // ao lado sao prosa e seguem na fonte de texto.
      meta: { width: 130 },
      cell: ({ row }) => {
        const a = row.original
        if (!a.vence_em) return <span className="text-muted-foreground">—</span>
        const d = a.dias_restantes
        // A contagem só aparece quando DECIDE algo (≤90 dias, ou já vencido). É a regra do
        // protótipo, e ele explica: "em 1824 dias" ao lado da data é o mesmo número dito
        // duas vezes — e era essa string que estourava a célula.
        const mostraContagem = d !== null && d <= 90
        // Prazo vencido nunca vira contagem negativa — o casos.md declara isso.
        const contagem = d !== null && d <= 0 ? `${-d}d vencido` : `${d}d`
        return (
          <Stack gap={1} align="start">
            {/* Data em mono, como o protótipo (`<b className="mono">`). O selo e a contagem
                ao lado seguem na fonte de texto: eles são prosa, não valor. */}
            <span className="font-mono tabular-nums" title={a.vence_em}>
              {dataBR(a.vence_em)}
            </span>
            <Inline gap={2} className="items-center">
              <StatusBadge kind="arquivo_prazo" value={estadoDoPrazo(d)} />
              {mostraContagem && (
                <span className="text-xs tabular-nums text-muted-foreground">{contagem}</span>
              )}
            </Inline>
          </Stack>
        )
      },
    },
    {
      id: 'acoes',
      // 140px e alinhada a direita, como o prototipo (`{ key: "acao", width: 190, align: "right" }`
      // — a largura menor porque aqui a linha tem 1 botao, nao os 4 do prototipo).
      meta: { width: 140, align: 'right' },
      // Cabeçalho VAZIO, como o protótipo (`{ key: "acao", label: "" }`) — "Ações" repetiria
      // o que os botões já dizem, e o contrato de tela não pina copy que não existe.
      header: '',
      cell: ({ row }) => {
        const a = row.original
        return (
          <Inline gap={2} justify="end">
            {a.download_url ? (
              // SÓ-ÍCONE com `aria-label`, e a razão é a do protótipo: com texto, os quatro
              // rótulos estouravam a célula. O nome do arquivo entra no rótulo acessível porque
              // numa tabela o verbo sozinho não diz QUAL — é o mesmo motivo de o `title` existir.
              //
              // `<a>` cru dentro do Button (`asChild`): a URL é assinada e aponta pro
              // `DownloadController`, que responde um stream. `<Link>` do Inertia faria XHR e
              // engasgaria no anexo. Sem `target="_blank"`: o navegador trata anexo como
              // download e não navega pra fora da tela.
              <Button variant="ghost" size="icon-sm" asChild>
                <a
                  href={a.download_url}
                  aria-label={`Baixar ${a.nome}`}
                  title={`Baixar ${a.nome} — link assinado, vale 60 min e passa pelo DownloadController.`}
                >
                  <Download aria-hidden="true" />
                </a>
              </Button>
            ) : (
              // Nada a servir: apagado (o `DownloadController` faz `find()` sem `withTrashed`,
              // então baixaria 404) ou sem conteúdo no storage — o que a estratégia
              // `anonymize` da retenção produz. Botão desabilitado seria uma promessa; o
              // travessão diz a verdade e o `title` explica.
              <span
                className="text-xs text-muted-foreground"
                title="Sem conteúdo pra baixar — arquivo excluído ou anonimizado pela retenção."
              >
                —
              </span>
            )}
          </Inline>
        )
      },
    },
  ]
}

/**
 * Colunas da trilha — a ordem é a do protótipo: Quando · Ação · Arquivo · Quem · Detalhe.
 *
 * Não há coluna de ação-de-linha, e isso é contrato, não esquecimento: `arquivos_audit_log`
 * é append-only e a tela não oferece editar nem apagar. Alterar auditoria é incidente.
 */
const COLUNAS_TRILHA: ColumnDef<LinhaTrilha, unknown>[] = [
  {
    id: 'quando',
    header: 'Quando',
    cell: ({ row }) => (
      <span className="tabular-nums text-xs" title={row.original.quando}>
        {dataHoraBR(row.original.quando)}
      </span>
    ),
  },
  {
    id: 'acao',
    header: 'Ação',
    cell: ({ row }) => (
      // PT-BR na tela, enum no `title`. Ação que a 3ª migration criar aparece com o valor
      // cru — o dono do vocabulário continua sendo a coluna, não este mapa.
      <Badge variant={TOM_ACAO[row.original.acao] ?? 'neutral'} title={row.original.acao}>
        {ACAO_PT[row.original.acao] ?? row.original.acao}
      </Badge>
    ),
  },
  {
    id: 'arquivo',
    header: 'Arquivo',
    cell: ({ row }) => (
      <span
        className="tabular-nums text-xs text-muted-foreground"
        title="A trilha guarda o id — o nome do arquivo se vê no Acervo."
      >
        #{row.original.arquivo}
      </span>
    ),
  },
  {
    id: 'quem',
    header: 'Quem',
    cell: ({ row }) =>
      row.original.quem ? (
        <span className="text-xs">{row.original.quem}</span>
      ) : (
        // Sem usuário = comando/job (CLI não tem sessão). Não é anônimo suspeito por si só:
        // o `arquivos:audit-log --suspicious` é quem separa link vazado de rotina agendada.
        <span className="text-xs text-muted-foreground" title="Sem usuário na sessão — comando ou job.">
          sistema
        </span>
      ),
  },
  {
    id: 'detalhe',
    header: 'Detalhe',
    cell: ({ row }) => (
      <span className="text-xs text-muted-foreground" title={row.original.detalhe ?? undefined}>
        {row.original.detalhe ? detalheBR(row.original.detalhe) : '—'}
      </span>
    ),
  },
]

function TabelaSkeleton() {
  return (
    <Stack gap={2} className="p-2">
      {Array.from({ length: 6 }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </Stack>
  )
}

function Acervo({ acervo, politica, filtros }: { acervo?: Paginator<LinhaAcervo>; politica: Politica[]; filtros: Filtros }) {
  if (!acervo || acervo.data.length === 0) {
    return (
      <EmptyState
        title="Nenhum arquivo guardado ainda."
        description="O acervo enche sozinho: XML de NF-e autorizada, foto de OS, anexo de ticket. Nada é enviado por esta tela — ela administra o que os módulos guardaram."
      />
    )
  }
  return (
    <DataTable
      columns={colunas(politica)}
      data={acervo.data}
      pagination={acervo}
      endpoint="/arquivos"
      filters={paraQuery(filtros)}
      searchPlaceholder="Buscar por nome, dono ou contexto…"
      initialSearch={filtros.q ?? ''}
      rowKey={(a) => a.id}
      rowState={estadoDaLinha}
      // ETAPA 2 — `.arq-lista` do bundle SUBSTITUI o wrapper canon do DataTable (superfície +
      // borda + raio 12 + rolagem horizontal). Somar as duas renderizaria duas molduras.
      tableWrapperClassName="arq-lista"
      // 1020px é do próprio bundle (`.arq-lista table{min-width:1020px}`) — não é número meu.
      // Precisa vir explícito porque o `style` inline do DataTable vence qualquer seletor:
      // sem isso o default (soma = 722) sobrescreveria a regra do bundle e as duas fontes
      // discordariam em silêncio.
      minTableWidth={1020}
    />
  )
}

function Trilha({ trilha, filtros }: { trilha?: TrilhaPayload; filtros: Filtros }) {
  if (!trilha || trilha.eventos.data.length === 0) {
    // Filtrada-vazia e vazia-de-verdade contam histórias diferentes: com um chip ativo,
    // quem explica o vazio é o filtro; sem chip, é o módulo que ainda não registrou nada.
    return filtros.acao ? (
      <EmptyState
        title={`Nenhum evento de ${(ACAO_PT[filtros.acao] ?? filtros.acao).toLowerCase()} no período.`}
        description="A trilha guarda o que aconteceu — se não aconteceu, não há linha. Tire o filtro pra ver as outras ações."
      />
    ) : (
      <EmptyState
        title="Nenhum evento registrado ainda."
        description="A trilha enche sozinha: cada upload, download, link assinado, exclusão e restauração vira uma linha. Ela é append-only e nunca é purgada — nem quando o arquivo é."
      />
    )
  }

  return (
    <DataTable
      columns={COLUNAS_TRILHA}
      data={trilha.eventos.data}
      pagination={trilha.eventos}
      endpoint="/arquivos"
      filters={paraQueryTrilha(filtros)}
      showSearch={false}
      rowKey={(t) => t.id}
    />
  )
}

/**
 * Rótulo do disco — a copy é a do protótipo: `vault` vira "Cofre (cifrado)", o resto vira
 * "Disco comum".
 *
 * Minha primeira versão mostrava `Disco {nome}` (ex.: "Disco arquivos"), com a justificativa
 * de que o nome varia por ambiente (`local` em dev, `arquivos` no CT 100). A justificativa
 * era verdadeira e a conclusão, errada: quem lê a tela quer saber se o arquivo está no cofre
 * cifrado ou no disco comum — o nome técnico do disco é detalhe de infra. Ele não se perde,
 * vai no `title`, que é o mesmo tratamento que bucket e visibilidade recebem aqui.
 */
function rotuloDisco(disco: string): { titulo: string; nota: string } {
  return disco === 'vault'
    ? { titulo: 'Cofre (cifrado)', nota: 'AES-256 via Crypt::encryptString — baixa pelo DownloadController' }
    // `Storage::url` é código, e este é justamente o card cuja justificativa acima diz que o
    // nome técnico vai no `title`. A nota agora descreve o COMPORTAMENTO, que é o que o leitor
    // da tela precisa saber pra entender a diferença pro cofre (que exige link temporário).
    : { titulo: 'Disco comum', nota: 'servido direto pelo navegador, sem link temporário' }
}

/** Um achado do cofre: título, o que ele significa, e a amostra de arquivos. */
function Achado({ titulo, children, exemplos }: { titulo: string; children: ReactNode; exemplos: string[] }) {
  return (
    <Stack gap={2} className="border-t border-border pt-4 first:border-t-0 first:pt-0">
      <span className="text-sm font-medium text-foreground">{titulo}</span>
      <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">{children}</p>
      {exemplos.length > 0 && (
        <Inline gap={2} wrap>
          {exemplos.map((e, i) => (
            <code key={i} className="rounded bg-muted px-2 py-1 text-xs text-muted-foreground">
              {e}
            </code>
          ))}
        </Inline>
      )}
    </Stack>
  )
}

/** Um KPI da retenção. Tom só onde ele decide algo — número neutro não vira alarme. */
function KpiRetencao({
  valor,
  rotulo,
  nota,
  tom,
}: {
  valor: number
  rotulo: string
  nota: string
  tom?: 'alerta' | 'perigo'
}) {
  const cor =
    tom === 'perigo' && valor > 0
      ? 'text-destructive'
      : tom === 'alerta' && valor > 0
        ? 'text-warning'
        : 'text-foreground'

  return (
    <Box bg="card" border rounded="lg" p={4}>
      <Stack gap={1}>
        <span className={`text-2xl font-medium tabular-nums ${cor}`}>{valor}</span>
        <span className="text-xs font-medium text-foreground">{rotulo}</span>
        <span className="text-xs text-muted-foreground">{nota}</span>
      </Stack>
    </Box>
  )
}

/**
 * Vista Retenção — o que vence, o que está no grace, o que passou do prazo.
 *
 * **Leitura pura, e a tela diz isso com todas as letras.** Ela mostra o que o comando FARIA;
 * quem apaga é o `arquivos:retention-cleanup`, com a política. Rodar pela tela é a onda 3 e
 * depende da proposta de ADR `arquivos-retencao-ui-aviso-titular`.
 */
function Retencao({ retencao, politica }: { retencao?: RetencaoPayload; politica: Politica[] }) {
  if (!retencao) return null

  if (!retencao.disponivel) {
    return (
      <EmptyState
        title="Não foi possível medir a retenção."
        description="Falta o business na sessão ou o módulo não foi migrado neste ambiente. Isto não quer dizer que está tudo em dia — quer dizer que ninguém olhou."
      />
    )
  }

  const { kpis } = retencao

  return (
    <Stack gap={4}>
      <Grid min="sm" gap={4} data-contract="retencao-kpis">
        <KpiRetencao valor={kpis.vence_30} rotulo="Vence em 30 dias" nota="ainda dá pra exportar" tom="alerta" />
        <KpiRetencao valor={kpis.vence_90} rotulo="Vence em 90 dias" nota="janela de planejamento" />
        {/* Este rótulo e o do card homônimo na grade de regras estavam em INGLÊS em UI
            cliente-facing — proibição dura, e a própria tela defende a regra em três lugares.
            Escapou porque veio desenhado assim no protótipo (`arquivos-page.jsx` usa o mesmo
            termo): a fonte carrega o defeito e a travessia o copiou fielmente. Aqui a lei é o
            charter (L88, PT-BR em todo label), que ganha do protótipo pela precedência —
            divergência declarada, não esquecida.
            ⚠️ NÃO cite o termo antigo aqui: o `contrato-de-tela` casa por substring no blob do
            arquivo, comentário incluído, e uma citação faz o gate passar VERDE com a tela já
            renomeada. O `_nota_limite` do contrato registra essa porta com canário rodado. */}
        <KpiRetencao
          valor={kpis.no_grace}
          rotulo="Na janela de restauro"
          nota={`${retencao.grace_dias} dias pra restaurar`}
        />
        <KpiRetencao
          valor={kpis.passou_do_prazo}
          rotulo="Passou do prazo"
          nota="e não foi deletado"
          tom="perigo"
        />
      </Grid>

      {/* O banner só aparece quando HÁ o problema — nota permanente vira paisagem. */}
      {kpis.passou_do_prazo > 0 && (
        <Box bg="card" border rounded="lg" p={4} className="border-destructive/40">
          <Stack gap={2}>
            <span className="text-sm font-medium text-destructive">
              {kpis.passou_do_prazo} {kpis.passou_do_prazo === 1 ? 'arquivo passou' : 'arquivos passaram'} do prazo e
              continuam no disco
            </span>
            <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
              É exatamente o WARN do <code>HealthCheckCommand</code> (check #4): guardar dado além da finalidade é o
              oposto do que a LGPD Art. 16 pede. Quem apaga é o <code>arquivos:retention-cleanup</code>, com{' '}
              <code>strategy={retencao.estrategia}</code>.
            </p>
          </Stack>
        </Box>
      )}

      {/* A política, com a LEI ao lado do prazo — é o Goal do charter, e o número sozinho
          não ensina o domínio. A contagem por contexto vem do mesmo leitor. */}
      <Box bg="card" border rounded="lg" p={0} data-contract="retencao-politica">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th className="px-4 py-3 font-medium">Contexto</th>
              <th className="px-4 py-3 text-right font-medium">Prazo</th>
              <th className="px-4 py-3 font-medium">Base legal</th>
              <th className="px-4 py-3 text-right font-medium">Arquivos</th>
            </tr>
          </thead>
          <tbody>
            {/* Ordena por nº de arquivos desc SEM esconder nada: a política inteira continua
                visível (é ela que ensina o domínio), mas o contexto que de fato tem arquivo
                deixa de cair na última linha. Cópia antes do sort — `politica` é prop, e
                `Array.prototype.sort` muta o array no lugar. */}
            {[...politica]
              .sort((x, y) => (retencao.por_contexto[y.sub] ?? 0) - (retencao.por_contexto[x.sub] ?? 0))
              .map((p) => (
              <tr key={p.sub} className="border-b border-border last:border-b-0">
                <td className="px-4 py-3">
                  <Stack gap={1} className="min-w-0">
                    <span className="font-medium text-foreground">{CONTEXTO_PT[p.sub] ?? p.sub}</span>
                    <code className="text-xs text-muted-foreground">{p.sub}</code>
                  </Stack>
                </td>
                <td className="px-4 py-3 text-right tabular-nums">
                  {/* `ticket-anexo` tem 365 dias e a tabela imprimia "1 anos". */}
                  {p.dias >= 365
                    ? `${Math.round(p.dias / 365)} ${Math.round(p.dias / 365) === 1 ? 'ano' : 'anos'}`
                    : `${p.dias} dias`}
                </td>
                <td className="px-4 py-3 text-xs text-muted-foreground">{p.lei}</td>
                <td className="px-4 py-3 text-right tabular-nums">{retencao.por_contexto[p.sub] ?? 0}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Box>

      <Grid min="sm" gap={4} data-contract="retencao-regras">
        <Box bg="card" border rounded="lg" p={4}>
          <Stack gap={1}>
            {/* Mesmo caso do KPI acima: inglês herdado do protótipo (termo antigo não citado
                aqui de propósito — ver a nota lá). */}
            <span className="text-xs text-muted-foreground">Janela de restauro</span>
            <span className="text-lg font-medium tabular-nums">{retencao.grace_dias} dias</span>
            <span className="text-xs text-muted-foreground">janela pra restaurar depois do prazo vencer</span>
          </Stack>
        </Box>
        <Box bg="card" border rounded="lg" p={4}>
          <Stack gap={1}>
            <span className="text-xs text-muted-foreground">Aviso ao titular</span>
            <span className="text-lg font-medium tabular-nums">{retencao.notice_dias} dias</span>
            <span className="text-xs text-muted-foreground">
              LGPD Art. 18 §VI — <strong>ainda não implementado</strong>, é config aspiracional
            </span>
          </Stack>
        </Box>
        <Box bg="card" border rounded="lg" p={4}>
          <Stack gap={1}>
            <span className="text-xs text-muted-foreground">Estratégia</span>
            {/* O enum do config chegava aqui CRU, como número-herói do card: a tela imprimia
                `hard_delete` em corpo grande. É o mesmo jargão que o card "Escopo do job"
                logo abaixo já trata com cuidado — só que este vinha do dado, não de um
                literal, e por isso nenhum lint o via (o `ds/no-db-jargon-in-ui` casa JSXText
                literal, como o comentário daquele card explica). O valor segue no `title`.
                O fallback preserva a regra do vocabulário: estratégia nova em
                `Config/retention.php` aparece crua em vez de sumir. */}
            <span className="text-lg font-medium" title={retencao.estrategia}>
              {ESTRATEGIA_PT[retencao.estrategia] ?? retencao.estrategia}
            </span>
            <span className="text-xs text-muted-foreground">
              {/* A nota antiga ("apagar de verdade; …") virou eco do título depois da
                  tradução — agora ela diz o que a outra estratégia faz, que é a informação
                  que o card não dava. */}
              o arquivo sai do disco; anonimizar é a alternativa por arquivo
            </span>
          </Stack>
        </Box>
        <Box bg="card" border rounded="lg" p={4}>
          <Stack gap={1}>
            <span className="text-xs text-muted-foreground">Escopo do job</span>
            {/* O protótipo escreve o nome da coluna aqui. A regra `ds/no-db-jargon-in-ui`
                proíbe jargão de banco em texto visível — e ela está certa: quem lê a tela
                quer saber que o job não atravessa empresas, não o nome do campo. Mesmo
                tratamento de bucket, visibilidade e disco: negócio na tela, técnico no
                `title`. Divergência do protótipo declarada, não esquecida. */}
            <span className="text-lg font-medium" title="business_id (ADR 0093)">
              Uma empresa por vez
            </span>
            <span className="text-xs text-muted-foreground">nunca atravessa empresas (ADR 0093)</span>
          </Stack>
        </Box>
      </Grid>

      {/* A frase que a proposta de ADR EXIGE que esta vista diga. Sem ela, a tela mostraria
          "o que o agendado faria hoje" para um agendado que ninguém marcou. O estado vem do
          runtime (`Schedule::events()`), não de leitura do Kernel. */}
      <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
        {retencao.agendado ? (
          <>
            O <code>arquivos:retention-cleanup</code> está <strong>agendado</strong> — os números acima são o que ele
            encontraria na próxima execução.
          </>
        ) : (
          <>
            ⚠️ O <code>arquivos:retention-cleanup</code> <strong>não está agendado</strong>: ele existe, está
            registrado, e só roda se alguém digitar o comando. Os números acima dizem o que ele encontraria — não o
            que vai acontecer sozinho. Ligar a execução (pela tela ou pelo agendado) é decisão que depende da ADR{' '}
            <code>arquivos-retencao-ui-aviso-titular</code>.
          </>
        )}{' '}
        Prazo é lei, não preferência: mudar um número aqui muda <code>Config/config.php</code> <strong>e</strong>{' '}
        <code>Config/retention.php</code> — são espelho, e divergir é achado de auditoria.
      </p>
    </Stack>
  )
}

function Cofre({ cofre }: { cofre?: CofrePayload }) {
  if (!cofre) return null

  // "Não medi" nunca vira "0 achados": zero com `disponivel` é acervo limpo; sem ele é
  // ausência de resposta, e afirmar saúde sem ter medido é o defeito clássico do gênero.
  if (!cofre.disponivel) {
    return (
      <EmptyState
        title="Não foi possível medir o cofre."
        description="Falta o business na sessão ou o módulo não foi migrado neste ambiente. Isto não quer dizer que não há achados — quer dizer que ninguém olhou."
      />
    )
  }

  if (cofre.discos.length === 0) {
    return (
      <EmptyState
        title="Nenhum arquivo guardado ainda."
        description="Sem acervo não há o que medir. O cofre enche junto com os módulos — e esta tela não envia arquivo."
      />
    )
  }

  const { acima_do_cap: acima, orfaos, duplicados } = cofre
  const semAchados = acima.total === 0 && orfaos.total === 0 && duplicados.grupos === 0

  return (
    <Stack gap={4}>
      {/* `min="sm"` (auto-fit) e não `cols` fixo: a grade se reflowa entre o 1280 da
          Larissa e o 1440 do Wagner sem media-query na tela (ADR 0253). */}
      <Grid min="sm" gap={4} data-contract="cofre-discos">
        {cofre.discos.map((d) => {
          const { titulo, nota } = rotuloDisco(d.disco)
          return (
            <Box key={d.disco} bg="card" border rounded="lg" p={4}>
              <Stack gap={1}>
                {/* O nome técnico do disco vai no `title` — some da leitura, fica pra quem depura. */}
                <span className="text-xs text-muted-foreground" title={d.disco}>
                  {titulo}
                </span>
                <span className="text-xl font-medium tabular-nums text-foreground">{tamanho(d.bytes)}</span>
                <span className="text-xs text-muted-foreground">
                  {d.arquivos} {d.arquivos === 1 ? 'arquivo' : 'arquivos'} ·{' '}
                  {/* No vault, cifrado abaixo do total é o mesmo sinal do check #5 do
                      `arquivos:health-check` — por isso o número aparece marcado. */}
                  {d.disco === 'vault' && d.cifrados < d.arquivos ? (
                    <span className="font-medium text-destructive">{d.cifrados} cifrados</span>
                  ) : (
                    <>{d.cifrados} cifrados</>
                  )}
                </span>
                <span className="text-xs text-muted-foreground">{nota}</span>
              </Stack>
            </Box>
          )
        })}
      </Grid>

      {/* SEM barra de progresso, ao contrário do protótipo — e a razão é medida, não
          gosto: lá a barra é `bytes / 5 GB`, e 5 GB é número do mock. Não existe quota
          por disco em `Config/config.php` (conferido), então a barra não teria
          denominador — ela sugeriria um teto que ninguém definiu. Se um dia houver
          quota configurada, a barra volta com significado. */}

      <Box bg="card" border rounded="lg" p={4} data-contract="cofre-achados">
        <Stack gap={4}>
          {/* "Achados" é o título que o protótipo dá ao bloco, e ele não é decoração: sem
              ele os 3 itens parecem 3 avisos soltos em vez de um relatório. Estava no
              contrato de tela do Cowork e não foi portado — falha minha, não decisão. */}
          <span className="text-sm font-semibold text-foreground">Achados</span>

          {semAchados ? (
            /* Cofre saudável gastava a dobra inteira em três ensaios técnicos (OOM do
               VaultEncryptionService, `attach()`, caminho derivado do hash) pra dizer que
               NADA foi encontrado. A explicação é boa e continua existindo — o que muda é o
               PESO quando o total é zero. Note que a condição é o zero dos TRÊS: achado com
               total 0 continua listado quando algum outro apareceu, porque aí o contraste
               é a informação. */
            <Stack gap={2}>
              <span className="text-sm font-medium text-foreground">Nada a apontar</span>
              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                Medimos os três sinais do <code>arquivos:health-check</code> que esta tela cobre — arquivo
                acima do cap de {cofre.cap_mb} MB, órfão sem vínculo e MD5 repetido — e nenhum apareceu.
                Quando um aparecer, ele abre aqui com a amostra e o que significa.
              </p>
            </Stack>
          ) : (
            <>
          <Achado
            titulo={`${acima.total} ${acima.total === 1 ? 'arquivo acima' : 'arquivos acima'} do cap de ${cofre.cap_mb} MB`}
            exemplos={acima.exemplos.map(
              (a) => `${a.nome} · ${tamanho(a.bytes)}${a.disco === 'vault' && !a.cifrado ? ' · no cofre SEM cifra' : ''}`,
            )}
          >
            O <code>VaultEncryptionService</code> carrega o arquivo inteiro em memória: acima do cap o
            processo entra em OOM e a cifragem é <strong>recusada</strong>, não silenciosa. Chunked
            encryption é Sprint 2 (ADR 0126). Vale pra qualquer disco: o cap morde na hora em que o arquivo
            vai pro cofre, então um arquivo comum grande é o que quebra a próxima reclassificação
            para <code>sensitive</code>.
          </Achado>

          <Achado
            titulo={`${orfaos.total} ${orfaos.total === 1 ? 'órfão' : 'órfãos'} (sem arquivable)`}
            exemplos={orfaos.exemplos.map((a) => `${a.nome} · ${tamanho(a.bytes)}`)}
          >
            Ninguém alcança pela tela do dono — ou vincula, ou apaga. Órfão que ninguém apaga é custo de
            disco com risco de PII.
          </Achado>

          <Achado
            titulo={`${duplicados.grupos}${duplicados.truncado ? '+' : ''} ${duplicados.grupos === 1 ? 'grupo' : 'grupos'} com MD5 repetido`}
            exemplos={duplicados.exemplos.map(
              (g) =>
                `${g.nomes.join(' = ')} · ${g.copias} registros · ${
                  g.caminhos > 1 ? `${g.caminhos} cópias em disco` : 'mesmo arquivo em disco'
                }`,
            )}
          >
            O upload já deduplica por hash dentro do business — <code>attach()</code> devolve o registro que
            existe em vez de gravar de novo. Então repetição aqui veio de outro caminho (o backfill de NF-e
            insere direto) ou de um dedupe que não pegou, e nem sempre é erro. <strong>Só ocupa disco duas
            vezes quando os caminhos diferem</strong>: o caminho é derivado do hash, então cópias do mesmo mês
            apontam para o mesmo arquivo físico — {duplicados.registros} registros envolvidos ao todo.
          </Achado>
            </>
          )}
        </Stack>
      </Box>
    </Stack>
  )
}

export default function Index({ filtros, politica, resumo, acervo, trilha, cofre, retencao }: Props) {
  const vista =
    filtros.tab === 'trilha' || filtros.tab === 'cofre' || filtros.tab === 'retencao' ? filtros.tab : 'acervo'

  const irPara = (patch: Record<string, ValorDeQuery>) =>
    router.get('/arquivos', paraNavegacao(filtros, patch), { preserveState: true, replace: true })

  // O cofre já vem agregado: o subtítulo dele soma os discos, sem query extra. Quando
  // `disponivel` é falso a frase diz isso — não inventa "0 arquivos", que seria a
  // resposta de um acervo vazio, e é outra coisa.
  const subtituloCofre = !cofre
    ? 'medindo o cofre…'
    : !cofre.disponivel
      ? 'não foi possível medir'
      : `${cofre.discos.reduce((s, d) => s + d.arquivos, 0)} arquivos · ${tamanho(
          cofre.discos.reduce((s, d) => s + d.bytes, 0),
        )} em ${cofre.discos.length} ${cofre.discos.length === 1 ? 'disco' : 'discos'}`

  // O subtítulo do acervo agora fala do ACERVO, não da página. Ele dizia "N nesta página",
  // que é outro número — e o `defer` fazia a frase nascer vazia até o payload chegar. O
  // `resumo` é eager: o cabeçalho pinta com o número certo no primeiro render.
  // A Retencao fala de PRAZO, nao de volume — por isso o subtitulo dela conta o que decide
  // acao (o que passou do prazo), nao quantos arquivos existem.
  const subtituloRetencao = !retencao
    ? 'medindo a retencao…'
    : !retencao.disponivel
      ? 'nao foi possivel medir'
      : retencao.kpis.passou_do_prazo > 0
        ? `${retencao.kpis.passou_do_prazo} passaram do prazo · ${retencao.kpis.vence_30} vencem em 30 dias`
        : `nada passou do prazo · ${retencao.kpis.vence_30} vencem em 30 dias`

  const subtitulo =
    vista === 'retencao'
      ? subtituloRetencao
      : vista === 'cofre'
      ? subtituloCofre
      : vista === 'trilha'
        ? `${resumo.eventos} eventos registrados`
        : `${resumo.arquivos} ${resumo.arquivos === 1 ? 'arquivo' : 'arquivos'} · ${tamanho(
            resumo.bytes,
          )} · ${resumo.cifrados} no cofre cifrado`

  return (
    <AppShellV2>
      {/* SEM cor de fundo aqui — de propósito, e isto foi MEDIDO (2026-08-25).
          Minha primeira versão punha `bg-page-cream`, com a justificativa de que "a tela
          nascia branca". Era falso: amostrando o pixel do render (GD, o mesmo motor do
          PixelBaselineTest), o fundo já era rgb(251,250,248) ANTES da mudança — porque o
          `.cockpit` pinta `background-color: var(--bg)`, e `--bg` é o MESMO
          `oklch(0.985 0.003 90)` do `--color-page-cream`.
          Pior que redundante: o `.cockpit` também pinta `background-image: var(--atmo)`,
          a atmosfera do ERP (dois gradientes radiais), e o comentário dele diz textual que
          "telas por cima ficam transparentes pra deixar passar". Um fundo OPACO aqui
          apagaria justamente o frescor que se queria. `.main-body` segue sem padding
          próprio — esse, sim, cada tela paga, e é o que o wrapper abaixo faz. */}
      <div className="flex-1 pb-8">
        <div data-contract="cabecalho">
          {/* O PageHeader canon já traz `pt-6 px-6 pb-3.5` por dentro — por isso ele fica
              FORA do wrapper de padding abaixo, senão o título ganharia 48px e desalinharia
              ao contrário. */}
          {/* "Auditoria" é a ação do cabeçalho no protótipo, e ela estava no espelho desde
              24/08 — não foi portada nos PR-1/2 por esquecimento meu, não por decisão. O
              destino existe: rota `auditoria.index` (Modules/Auditoria). São coisas
              diferentes de propósito — a Trilha aqui é só de arquivo; a Auditoria é a do
              sistema (`activity_log`). */}
          <PageHeader
            title="Arquivos"
            subtitle={subtitulo}
            actions={
              <Button variant="outline" size="sm" asChild>
                <Link href="/auditoria">Auditoria</Link>
              </Button>
            }
          />
        </div>

        {/* `px-6` casa com o px-6 interno do header: sem ele o header ficava recuado 24px e
            TODO o resto (abas, chips, tabela, rodapé) colava na borda da sidebar — visível
            no render do visreg. `space-y-6` dá o ritmo entre os blocos, no lugar dos
            `py-3`/`pb-3`/`pt-4` soltos que cada bloco carregava. */}
        <div className="w-full px-6 pt-5 space-y-6">
          <div data-contract="abas">
            {/* Contadores: o `badge` do PageHeaderTabs já é o pill do protótipo (cinza,
                roxo na aba ativa). O Cofre NÃO leva número — é o que o protótipo desenha,
                e faz sentido: cofre não é uma lista, é um retrato com 3 achados. */}
            <PageHeaderTabs
              ghosts={[
                { key: 'acervo', label: 'Acervo', href: '/arquivos?tab=acervo', badge: resumo.arquivos },
                { key: 'retencao', label: 'Retenção', href: '/arquivos?tab=retencao' },
                { key: 'cofre', label: 'Cofre', href: '/arquivos?tab=cofre' },
                { key: 'trilha', label: 'Trilha', href: '/arquivos?tab=trilha', badge: resumo.eventos },
              ]}
              activeGhostKey={vista}
            />
          </div>

          {vista === 'acervo' && (
            /* Ritmo interno da vista (16px) menor que o do wrapper (24px): abas e vista são
               blocos distintos; filtro, tabela e nota são a MESMA conversa. */
            <div className="space-y-4">
              <Inline gap={3} justify="between" wrap data-contract="acervo-filtros">
                {/* Chips em PT-BR com contagem, como o protótipo. A contagem vem do
                    `GROUP BY bucket` do resumo — o mesmo que já paga o subtítulo, então
                    não custa query nova. Bucket sem nenhuma linha mostra 0 em vez de
                    sumir: saber que "Descartar" está zerado É a informação. */}
                <Inline gap={2} wrap>
                  <button type="button" onClick={() => irPara({ bucket: null })} className={chip(!filtros.bucket)}>
                    Todos <span className="tabular-nums opacity-70">{resumo.arquivos}</span>
                  </button>
                  {BUCKETS.map((b) => (
                    <button
                      key={b}
                      type="button"
                      title={b}
                      onClick={() => irPara({ bucket: b })}
                      className={chip(filtros.bucket === b)}
                    >
                      {BUCKET_PT[b] ?? b} <span className="tabular-nums opacity-70">{resumo.por_bucket[b] ?? 0}</span>
                    </button>
                  ))}
                </Inline>
              </Inline>

              <div data-contract="acervo">
                <Deferred data="acervo" fallback={<TabelaSkeleton />}>
                  <Acervo acervo={acervo} politica={politica} filtros={filtros} />
                </Deferred>
              </div>

              {/* Largura de leitura: sem ela a nota virava uma linha de ponta a ponta do
                  monitor — no render de 1728px do visreg media ~1400px (~230 caracteres).
                  `ch` e não `max-w-3xl` porque o critério é caractere por linha, e 72ch cai
                  na faixa legível; o repo já tem precedente da forma (`max-w-[76ch]`,
                  `max-w-[80ch]`). NÃO usar `max-w-prose`: não há `prose` no theme deste
                  projeto (conferido), então a classe seria ignorada em silêncio. */}
              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                O acervo é administrativo: o arquivo continua sendo alcançado pela tela do dono. Baixar do
                cofre passa sempre pelo <code>DownloadController</code> — <code>Storage::url</code> direto
                não serve arquivo cifrado (ADR 0123 §6), e o link assinado expira em 60 min. Esta tela não
                envia arquivo: upload entra pelos módulos, via trait <code>HasArquivos</code>.
              </p>
            </div>
          )}

          {vista === 'retencao' && (
            <div className="space-y-4">
              <div data-contract="retencao">
                <Deferred data="retencao" fallback={<TabelaSkeleton />}>
                  <Retencao retencao={retencao} politica={politica} />
                </Deferred>
              </div>
            </div>
          )}

          {vista === 'cofre' && (
            <div className="space-y-4">
              {/* Sem filtros nesta vista, de propósito: o cofre é o retrato do acervo
                  inteiro do business. "Achados que sobram depois do filtro" responderia
                  outra pergunta — e a que interessa aqui é quanto está guardado e o que
                  está errado, não o recorte. */}
              <div data-contract="cofre">
                <Deferred data="cofre" fallback={<TabelaSkeleton />}>
                  <Cofre cofre={cofre} />
                </Deferred>
              </div>

              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                Os três achados são <strong>sinal, não fila de trabalho</strong>: esta tela não apaga, não
                vincula e não recifra nada. Quem apaga é o comando, com política —{' '}
                <code>arquivos:retention-cleanup</code>; quem recifra é{' '}
                <code>arquivos:reencrypt-vault</code>; e o retrato completo de saúde, com os 5 sinais de
                integridade, é <code>arquivos:health-check</code>. Cada achado mostra no máximo 5 arquivos:
                a lista inteira é o Acervo, na aba ao lado.
              </p>
            </div>
          )}

          {vista === 'trilha' && (
            <div className="space-y-4">
              {/* Os chips saem do próprio log (GROUP BY), então só existe filtro pra ação que
                  este business registrou de fato — nunca um chip que leva a lista vazia. */}
              <Inline gap={3} justify="between" wrap data-contract="trilha-filtros">
                <Inline gap={2} wrap>
                  <button type="button" onClick={() => irPara({ acao: null })} className={chip(!filtros.acao)}>
                    Todas
                  </button>
                  {(trilha?.acoes ?? []).map((f) => (
                    <button
                      key={f.acao}
                      type="button"
                      title={f.acao}
                      onClick={() => irPara({ acao: f.acao })}
                      className={chip(filtros.acao === f.acao)}
                    >
                      {ACAO_PT[f.acao] ?? f.acao} <span className="tabular-nums opacity-70">{f.total}</span>
                    </button>
                  ))}
                </Inline>
              </Inline>

              <div data-contract="trilha">
                <Deferred data="trilha" fallback={<TabelaSkeleton />}>
                  <Trilha trilha={trilha} filtros={filtros} />
                </Deferred>
              </div>

              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
                <code>arquivos_audit_log</code> é append-only e <strong>nunca purgado</strong> — nem quando o
                arquivo é apagado. Esta tela não oferece editar nem apagar linha: alterar auditoria é
                incidente, não conserto. Para varredura de padrão suspeito (link assinado sem usuário,
                exclusão em série, download repetido do mesmo IP), quem responde é o comando{' '}
                <code>arquivos:audit-log --suspicious</code>.
              </p>
            </div>
          )}
        </div>
      </div>
    </AppShellV2>
  )
}
