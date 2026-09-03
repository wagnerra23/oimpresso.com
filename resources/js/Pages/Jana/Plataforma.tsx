// Jana/Plataforma — /ia/superadmin/metas (F3 do MWART, ADR 0104).
//
//   F1      : memory/requisitos/Jana/RUNBOOK-plataforma.md
//   design  : prototipo-ui/cowork/jana-telas-novas.jsx §JmPlataforma (+ .css), descido no #6379
//   PT      : PT-01 Lista (duas listas de entidade em seções — RUNBOOK §5)
//   Tier 0  : ADR 0093 — esta tela mostra dado de OUTROS tenants POR DESENHO. O
//             `withoutGlobalScope` do controller é o caso legítimo; o gate é o QUEM.
//
// Substitui `copiloto::superadmin.metas` (Blade AdminLTE cru). O contrato da Blade —
// títulos das duas seções, colunas e as DUAS copies de vazio — está preservado literal
// (RUNBOOK §3) e pinado no contrato de tela `prototipo-ui/contrato/jana-plataforma.contract.json`.
//
// ⚠️ DUAS coisas do protótipo NÃO entram, e não é esquecimento (RUNBOOK §3.2):
//   1. o `<Alert tone="danger">` sobre o gate — ele descreve o vazamento que o #6421
//      FECHOU em 28/08, um dia depois de o protótipo ser desenhado. Renderizar hoje um
//      aviso de vulnerabilidade já corrigida é a classe LC-10 (artefato afirmando em
//      presente um estado que já é falso). A fonte é soberana na FORMA, não em fato datado.
//   2. a seção "Instalação do módulo" — ela é de `/ia/install` (outro grupo de rotas,
//      outro controller), e `uninstall` derruba as tabelas `jana_*`. Superfície
//      destrutiva de outra rota não entra de carona; é F1 própria.
import type { ReactNode } from 'react'
import { Deferred } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import EmptyState from '@/Components/shared/EmptyState'
import { Inline, Stack } from '@/Components/layout'
import { Skeleton } from '@/Components/ui/skeleton'
import { JanaAreaHeader } from '@/Pages/Jana/_components/JanaAreaHeader'

// ── Props (de SuperadminController@metas) ────────────────────────────────────
// As duas chegam por `Inertia::defer`, logo são `undefined` no primeiro paint —
// é isso que dá à tela um estado de CARREGANDO real, e não um spinner decorativo.
interface MetaDaPlataforma {
  id: number
  slug: string
  nome: string
  unidade: string
  origem: string
}

interface MetaDeCliente {
  id: number
  business_id: number
  slug: string
  nome: string
  unidade: string
  periodo_atual: { data_ini: string | null; data_fim: string | null } | null
  ultima_apuracao: string | null
}

interface Props {
  metasPlataforma?: MetaDaPlataforma[]
  metasDeClientes?: MetaDeCliente[]
}

// ── Datas ────────────────────────────────────────────────────────────────────
// Parse MANUAL da string ISO, sem `new Date(iso)`: o construtor trata "YYYY-MM-DD"
// como UTC e, num fuso negativo como o BRT, devolve o DIA ANTERIOR. Numa tela de
// auditoria de plataforma isso não é detalhe cosmético — é a data errada.
// (E `format_date` do app está fora de questão: carrega o shift +3h preservado
// pra clientes legados, ADR 0066.)
const diaMes = (iso: string | null | undefined): string => {
  if (!iso) return '—'
  const [, m, d] = iso.split('-')
  return m && d ? `${d}/${m}` : '—'
}

const diaMesAno = (iso: string | null | undefined): string => {
  if (!iso) return '—'
  const [a, m, d] = iso.split('-')
  return a && m && d ? `${d}/${m}/${a}` : '—'
}

const periodoLegivel = (p: MetaDeCliente['periodo_atual']): string => {
  if (!p?.data_ini || !p?.data_fim) return '—'
  return `${diaMes(p.data_ini)}–${diaMes(p.data_fim)}`
}

// O protótipo mostra a origem por extenso; o dado gravado é a chave curta.
//
// ⚠️ AS CHAVES VÊM DO ENUM DO BANCO, NÃO DO PROTÓTIPO. A migration declara
// `enum('origem', ['chat_ia','manual','seed'])->default('manual')`; o protótipo usa
// `origem: "sistema"`, que NÃO EXISTE no schema. Este mapa nasceu com `sistema`/`manual`
// — ou seja, errado para 2 dos 3 valores reais, que cairiam no fallback e mostrariam a
// chave crua ao superadmin. Pego pelo `UC-PLATAF-02` no primeiro run da lane MySQL
// (2026-09-03), que reprovou com `-'sistema' +''`: o MySQL não-estrito grava string
// VAZIA quando o valor não está no enum — falha silenciosa, sem erro.
//
// A fonte de design é soberana na FORMA (mostrar por extenso), nunca no DOMÍNIO DE DADOS.
// Derivar chave de banco do protótipo é derivar da fonte errada.
const ORIGEM_LEGIVEL: Record<string, string> = {
  chat_ia: 'proposta pela Jana',
  manual: 'cadastro manual',
  seed: 'carga inicial',
}

// ── Peças de tabela (classes canônicas — Pages/Auditoria/Index.tsx é o precedente) ──
const TH = 'px-4 py-2 font-semibold text-muted-foreground'
const TD = 'px-4 py-2'

function TabelaSkeleton(): ReactNode {
  return (
    <Stack gap={2} className="p-2">
      {Array.from({ length: 4 }).map((_, i) => (
        <Skeleton key={i} className="h-10 w-full" />
      ))}
    </Stack>
  )
}

/**
 * Cabeçalho de seção — título + contagem.
 *
 * ⚠️ A contagem é do que ESTÁ LISTADO, e só. Não é agregado de plataforma: a agregação
 * cross-business que o docblock antigo do controller prometia não existe (medido 27/08 e
 * re-medido 31/08 — zero sum/count/groupBy), e somar aqui inventaria um total que ninguém
 * definiu. O protótipo toma a mesma posição e escreve a razão na tela (ver a nota abaixo
 * da 2ª tabela). RUNBOOK-plataforma.md §6.1.
 */
function SecaoHeader({ titulo, meta }: { titulo: string; meta: string }): ReactNode {
  return (
    <Inline gap={2} align="baseline">
      <h2 className="text-sm font-semibold text-foreground">{titulo}</h2>
      <small className="font-mono text-[10.5px] text-muted-foreground">{meta}</small>
    </Inline>
  )
}

/** Estado de erro: prop deferida que voltou com forma inesperada. */
function TabelaQuebrada({ o_que }: { o_que: string }): ReactNode {
  return (
    <EmptyState
      variant="error"
      icon="alert-triangle"
      title="Não foi possível carregar esta lista."
      description={`${o_que} Recarregue a página; se persistir, o payload do servidor mudou de forma.`}
    />
  )
}

// ── Seção 1 — metas da plataforma (business_id NULL) ─────────────────────────
function MetasDaPlataforma({ metas }: { metas?: MetaDaPlataforma[] }): ReactNode {
  if (!Array.isArray(metas)) {
    return <TabelaQuebrada o_que="A lista de metas da plataforma não veio como esperado." />
  }

  if (metas.length === 0) {
    // Copy LITERAL da Blade (RUNBOOK §3) — é o que a tela mostra em produção hoje.
    return <EmptyState title="Nenhuma meta da plataforma cadastrada." />
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 border-b border-border">
          <tr className="text-left">
            <th className={TH}>Nome</th>
            <th className={TH}>Unidade</th>
            <th className={TH}>Origem</th>
          </tr>
        </thead>
        <tbody>
          {metas.map((m) => (
            <tr key={m.id} className="border-b border-border last:border-0 hover:bg-muted/40 transition-colors">
              <td className={TD}>
                <span className="block text-foreground">{m.nome}</span>
                <span className="block font-mono text-[10.5px] text-muted-foreground">{m.slug}</span>
              </td>
              <td className={`${TD} font-mono tabular-nums text-muted-foreground`}>{m.unidade}</td>
              <td className={`${TD} text-muted-foreground`}>{ORIGEM_LEGIVEL[m.origem] ?? m.origem}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

// ── Seção 2 — metas de clientes (cross-business) ─────────────────────────────
function MetasDeClientes({ metas }: { metas?: MetaDeCliente[] }): ReactNode {
  if (!Array.isArray(metas)) {
    return <TabelaQuebrada o_que="A lista de metas de clientes não veio como esperado." />
  }

  if (metas.length === 0) {
    // Copy LITERAL da Blade (RUNBOOK §3).
    return <EmptyState title="Nenhum cliente configurou metas ainda." />
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 border-b border-border">
          <tr className="text-left">
            <th className={TH}>Business</th>
            <th className={TH}>Nome</th>
            <th className={TH}>Unidade</th>
            <th className={TH}>Período atual</th>
            <th className={TH}>Última apuração</th>
          </tr>
        </thead>
        <tbody>
          {metas.map((m) => {
            // `archived` do protótipo: meta que nunca foi apurada fica esmaecida.
            const nuncaApurada = !m.ultima_apuracao
            return (
              <tr
                key={m.id}
                className={`border-b border-border last:border-0 hover:bg-muted/40 transition-colors${
                  nuncaApurada ? ' opacity-60' : ''
                }`}
              >
                {/* O `#` antes do id é literal da Blade — contrato (RUNBOOK §3). */}
                <td className={`${TD} font-mono tabular-nums`}>#{m.business_id}</td>
                <td className={TD}>
                  <span className="block text-foreground">{m.nome}</span>
                  <span className="block font-mono text-[10.5px] text-muted-foreground">{m.slug}</span>
                </td>
                <td className={`${TD} font-mono tabular-nums text-muted-foreground`}>{m.unidade}</td>
                <td className={`${TD} font-mono tabular-nums text-muted-foreground`}>
                  {periodoLegivel(m.periodo_atual)}
                </td>
                <td className={`${TD} font-mono tabular-nums text-muted-foreground`}>
                  {nuncaApurada ? 'nunca apurada' : diaMesAno(m.ultima_apuracao)}
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

export default function Plataforma({ metasPlataforma, metasDeClientes }: Props) {
  const nPlataforma = Array.isArray(metasPlataforma) ? metasPlataforma.length : 0
  const nClientes = Array.isArray(metasDeClientes) ? metasDeClientes.length : 0
  const nEmpresas = Array.isArray(metasDeClientes)
    ? new Set(metasDeClientes.map((m) => m.business_id)).size
    : 0

  return (
    <>
      {/* `data-contract` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <JanaAreaHeader active="plataforma" />
      </div>

      <Stack gap={4} className="p-4">
        <Stack asChild gap={2}>
          <section data-contract="metas-plataforma">
          <SecaoHeader
            titulo="Metas da plataforma (business_id NULL)"
            meta={`business_id NULL · ${nPlataforma} ${nPlataforma === 1 ? 'meta' : 'metas'}`}
          />
          <Deferred data="metasPlataforma" fallback={<TabelaSkeleton />}>
            <MetasDaPlataforma metas={metasPlataforma} />
          </Deferred>
          </section>
        </Stack>

        <Stack asChild gap={2}>
          <section data-contract="metas-clientes">
          <SecaoHeader
            titulo="Metas de clientes (cross-business)"
            meta={`cross-business · ${nClientes} ${nClientes === 1 ? 'meta' : 'metas'} em ${nEmpresas} ${
              nEmpresas === 1 ? 'empresa' : 'empresas'
            }`}
          />
          <Deferred data="metasDeClientes" fallback={<TabelaSkeleton />}>
            <MetasDeClientes metas={metasDeClientes} />
          </Deferred>
          <p className="m-0 max-w-[82ch] text-[11px] leading-relaxed text-muted-foreground">
            Listagem crua, de propósito: a <b>agregação cross-business</b> que o docblock antigo
            prometia não existe no controller (nenhum <code>sum</code>/<code>count</code>/
            <code>groupBy</code>, medido em 27/08/2026 e re-medido em 31/08/2026). Somar aqui na
            tela seria inventar total de plataforma no cliente — a pendência fica declarada até
            alguém decidir o que a plataforma quer medir.
          </p>
          </section>
        </Stack>
      </Stack>
    </>
  )
}

Plataforma.layout = (page: ReactNode) => <AppShellV2 title="Jana — Superadmin">{page}</AppShellV2>
