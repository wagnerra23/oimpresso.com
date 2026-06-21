// @governance
//   tela: /governance/ds-rollout
//   adrs: 0209 (ratchet), 0235/0190 (roxo canônico), 0239 (gov DS git=SSOT), 0240 (evidência fecha task)
//
// Tradução F3 (PROTOCOL §2) do protótipo Cowork `DS Rollout - Ondas e Testes.html`
// (handoff claude.ai/design). O protótipo é um PLANO: quantas ondas pra portar o
// Design System inteiro + o **Ledger de Conformidade** que prova "tudo aplicado"
// mecanicamente (não na palavra de ninguém).
//
// Fidelidade visual SEM o `<style>` OKLCH cru do protótipo — esse seria justo o
// débito que o plano combate. Aqui o visual é reconstruído com primitivas do DS
// (PageHeader canon · KpiCard · Card · Badge) + paleta semântica Tailwind.
//
// O Ledger entra por prop `census` (a "1ª coluna" que o plano nomeia: rodar
// ds-report.mjs + conformance-gate por tela). Hoje é um snapshot estático no
// controller; o próximo passo é o script `scripts/ds-ledger.mjs` popular isso ao vivo.

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { Inline, Grid } from '@/Components/layout'
import {
  GitCommitHorizontal,
  ScanLine,
  CheckCircle2,
  Equal,
  Image as ImageIcon,
} from 'lucide-react'

/* ─────────────────────────────────────────────────────────────────────────────
   Tipos do Ledger (a parte "viva" — vem do controller, futuramente do census).
   ───────────────────────────────────────────────────────────────────────────── */
type CellState = 'yes' | 'no' | 'ref' | 'na'

interface LedgerRow {
  screen: string
  note?: string
  /** referência (a Caixa/ouro) destaca a linha */
  reference?: boolean
  cells: {
    tokens: CellState
    primitivos: CellState
    probe: CellState
    dark: CellState
    approved: CellState
  }
}

interface TreeGuard {
  pass: boolean
  violations: number | null
}

interface Census {
  ledger: LedgerRow[]
  progressPct: number
  /** rótulo do que o % mede (ex: "adoção tokens + primitivos") */
  progressLabel?: string
  /** true = veio do gate rodando (ds-ledger.mjs); false/ausente = snapshot estático */
  measured?: boolean
  measuredAgainstSha: string | null
  generatedAt: string | null
  treeGuard?: TreeGuard | null
  counts?: { screens: number; done: number; references: number }
}

interface Props {
  census: Census
}

/* ─────────────────────────────────────────────────────────────────────────────
   Conteúdo estático do PLANO (é o próprio design — não é dado de runtime).
   ───────────────────────────────────────────────────────────────────────────── */

const BLOCO_A: Array<{ id: string; entrega: ReactNode; piloto: string; teste: ReactNode }> = [
  { id: 'A1', entrega: <><b>Tokens de cor</b> (balde B)</>, piloto: 'base global', teste: <>Ledger registra <b>cor crua = 0</b> na baseline; computed-style resolve de token</> },
  { id: 'A2', entrega: <><b>Adotar existentes</b> — <code>.btn .search .avatar</code> + PageHeader (alias)</>, piloto: 'alias CSS', teste: <>Mapa de alias + <b>computed-style idêntico</b> antes/depois</> },
  { id: 'A3', entrega: <><b>Primitivo</b> <code>.workspace-3</code></>, piloto: 'ficha de CRM', teste: <>Piloto ok + <b>Caixa diff = 0</b> + probe verde</> },
  { id: 'A4', entrega: <><b>Primitivos de formulário</b> <code>.field .input</code></>, piloto: 'cadastro de produto', teste: <>Piloto ok + probe + <b>antes/depois aprovado</b></> },
  { id: 'A5', entrega: <><b>Avatar + glyph</b></>, piloto: 'lista de clientes', teste: <>Piloto ok + probe verde</> },
  { id: 'A6', entrega: <><b>Pílula de frescor / SLA</b> <code>.frescor</code></>, piloto: 'fila da Oficina', teste: <>Piloto ok + probe verde</> },
  { id: 'A7', entrega: <><b>Command palette</b> <code>.palette</code> ⌘K</>, piloto: 'global', teste: <>Abre em todas as rotas; navegação por teclado testada</> },
]

const BLOCO_B: Array<{ id: string; tela: ReactNode; adota: string; teste: ReactNode }> = [
  { id: 'B1', tela: <><b>Clientes</b> (lista + ficha)</>, adota: 'tokens · avatar · form', teste: <>probe G1–G13 verde · dark ok · ledger +1</> },
  { id: 'B2', tela: <><b>Orçamentos</b></>, adota: 'tokens · form · tabela', teste: <>probe verde · ledger +1</> },
  { id: 'B3', tela: <><b>Vendas</b> — lista + filtros</>, adota: 'tokens · tabela · frescor', teste: <>probe verde · totais conferem</> },
  { id: 'B4', tela: <><b>Vendas</b> — create / drawer</>, adota: 'tokens · form · workspace', teste: <>probe verde · fluxo de venda intacto</> },
  { id: 'B5', tela: <><b>OS / Oficina</b> — fila + board</>, adota: 'tokens · frescor · avatar', teste: <>probe verde · drag-drop intacto</> },
  { id: 'B6', tela: <><b>OS / Oficina</b> — drawer / detalhe</>, adota: 'tokens · workspace · form', teste: <>probe verde · FSM intacto</> },
  { id: 'B7', tela: <><b>Produção</b> (fila/acab./exped.)</>, adota: 'tokens · frescor', teste: <>probe verde · ledger +1</> },
  { id: 'B8', tela: <><b>Financeiro</b> — listas + sub-telas</>, adota: 'tokens (já alto) · tabela', teste: <>probe verde · números tabulares</> },
  { id: 'B9', tela: <><b>Financeiro</b> — drawer 9.75</>, adota: 'tokens · workspace', teste: <>probe verde · diff de valor = 0</> },
  { id: 'B10', tela: <><b>Boletos + Cobrança</b></>, adota: 'tokens · tabela · frescor', teste: <>probe verde · ledger +1</> },
  { id: 'B11', tela: <><b>Cobrança recorrente</b></>, adota: 'tokens · form · tabela', teste: <>probe verde · ledger +1</> },
  { id: 'B12', tela: <><b>Pagamentos</b> (gateways)</>, adota: 'tokens · form', teste: <>probe verde · ledger +1</> },
  { id: 'B13', tela: <><b>Compras</b></>, adota: 'tokens · tabela · form', teste: <>probe verde · ledger +1</> },
  { id: 'B14', tela: <><b>Equipe</b> + <b>CRM</b> funil</>, adota: 'tokens · avatar · workspace', teste: <>probe verde · ledger +1</> },
]

const BLOCO_C: Array<{ id: string; entrega: ReactNode; teste: ReactNode }> = [
  { id: 'C1', entrega: <><b>Migrar a Caixa</b> pra consumir os primitivos provados (em vez da cópia local <code>om-*</code>)</>, teste: <>A Caixa fica <b>visualmente idêntica</b> (diff de computed-style = 0) — mas agora roda <b>no DS</b>. Prova final de que o DS reproduz o ouro.</> },
  { id: 'C2', entrega: <><b>Tokenizar a cor da Caixa</b> — o verde vira <code>--ch-wa</code> governado</>, teste: <>Diff = 0 · placar de cor crua da Caixa <b>114 → 0</b> · dark intacto</> },
]

const MEDICAO_REAL: Array<{ item: ReactNode; estado: ReactNode; estadoTone: 'pos' | 'neg'; evidencia: ReactNode }> = [
  { item: <><b>Componentes do DS</b></>, estado: <>✓ usa</>, estadoTone: 'pos', evidencia: <><code>Input · Button · Label · Select · Card · Textarea</code> de <code>@/Components/ui</code> + <code>AppShellV2</code></> },
  { item: <><b>Cor por token</b></>, estado: <>✗ crua</>, estadoTone: 'neg', evidencia: <><code>bg-stone-* · text-stone-* · border-stone-* · text-rose-*</code> — Tailwind cru (stone/rose), não token semântico</> },
  { item: <><b>PageHeader</b></>, estado: <>✗ à mão</>, estadoTone: 'neg', evidencia: <>header <code>sticky</code> hand-rolled, mesmo existindo <code>shared/PageHeader.tsx</code></> },
  { item: <><b>Checkbox</b></>, estado: <>✗ cru</>, estadoTone: 'neg', evidencia: <><code>&lt;input type=checkbox className="rounded border-stone-*"&gt;</code>, existindo <code>ui/checkbox.tsx</code></> },
]

const PROOFS: Array<{ icon: ReactNode; title: string; body: ReactNode }> = [
  { icon: <ImageIcon className="h-4 w-4" />, title: 'Antes / depois', body: <>Screenshot pareado da tela. Você vê que ficou igual ou melhor.</> },
  { icon: <CheckCircle2 className="h-4 w-4" />, title: 'Probe automático', body: <><code>qa-conformance</code> G1–G13: cor, contraste, foco, dark, cor crua. Pega o que o olho deixa passar.</> },
  { icon: <Equal className="h-4 w-4" />, title: 'Sem regressão', body: <>Diff da Caixa (e das telas já feitas) = 0. Uma onda nova não pode quebrar onda velha.</> },
]

/* ─────────────────────────────────────────────────────────────────────────────
   Átomos de apresentação (Tailwind semântico · sem cor crua).
   ───────────────────────────────────────────────────────────────────────────── */

function Code({ children }: { children: ReactNode }) {
  return (
    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.85em] text-primary dark:text-primary">
      {children}
    </code>
  )
}

function GroupHeader({ tag, children, count }: { tag: string; children: ReactNode; count?: string }) {
  return (
    <Inline align="center" gap={2} className="mt-12 mb-4 border-b border-border pb-2.5 text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
      <span className="font-mono text-primary">{tag}</span>
      <span className="flex-1">{children}</span>
      {count && <span className="font-mono text-xs font-semibold normal-case tracking-normal text-muted-foreground">{count}</span>}
    </Inline>
  )
}

function Callout({
  tone = 'accent',
  title,
  children,
}: {
  tone?: 'accent' | 'warn' | 'pos'
  title: ReactNode
  children: ReactNode
}) {
  const border = {
    accent: 'border-l-primary',
    warn: 'border-l-warning',
    pos: 'border-l-success',
  }[tone]
  return (
    <Card className={`mt-4 border-l-[3px] ${border}`}>
      <CardContent className="p-4 sm:p-5">
        <h3 className="mb-2 text-sm font-semibold text-foreground">{title}</h3>
        <div className="space-y-1.5 text-[12.5px] leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          {children}
        </div>
      </CardContent>
    </Card>
  )
}

const PilotPill = ({ children }: { children: ReactNode }) => (
  <span className="inline-block whitespace-nowrap rounded bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-semibold text-primary">
    {children}
  </span>
)

const Th = ({ children, className = '' }: { children?: ReactNode; className?: string }) => (
  <th className={`border-b border-border bg-muted/60 px-3 py-2.5 text-left font-mono text-[9.5px] font-semibold uppercase tracking-wider text-muted-foreground ${className}`}>
    {children}
  </th>
)
const Td = ({ children, className = '' }: { children?: ReactNode; className?: string }) => (
  <td className={`border-b border-border/60 px-3 py-2.5 align-top leading-snug ${className}`}>{children}</td>
)

function WaveTable({ children }: { children: ReactNode }) {
  return (
    <Card className="overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-[12.5px] [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em] [&_code]:text-primary">
          {children}
        </table>
      </div>
    </Card>
  )
}

/** célula de check do Ledger */
function Ck({ state }: { state: CellState }) {
  const map = {
    yes: { cls: 'bg-success/15 text-success', glyph: '✓', title: 'conforme' },
    ref: { cls: 'bg-warning/15 text-warning', glyph: '★', title: 'referência (o ouro)' },
    no: { cls: 'border border-border bg-muted/50 text-muted-foreground', glyph: '·', title: 'falta' },
    na: { cls: 'border border-dashed border-border text-muted-foreground/50', glyph: '–', title: 'não medido por censo estático (probe G1–13 + dark = Camada 2 browser)' },
  }[state]
  return (
    <span
      title={map.title}
      className={`inline-grid h-[19px] w-[19px] place-items-center rounded-full font-mono text-[11px] font-bold ${map.cls}`}
    >
      {map.glyph}
    </span>
  )
}

/* ─────────────────────────────────────────────────────────────────────────────
   Página
   ───────────────────────────────────────────────────────────────────────────── */

const DsRollout: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ census }) => {
  const pct = Math.max(0, Math.min(100, census.progressPct))
  const measured = census.measured !== false && !!census.generatedAt

  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader
        title="Rollout do Design System"
        suffix=" · em ondas medíveis"
        subtitle={
          <>
            ≈16–18 ondas · 4 blocos — entrega aplicável + teste de saída por onda; o Ledger prova "tudo aplicado".
          </>
        }
        actions={
          <Badge variant="outline" className="border-primary/30 bg-primary/10 font-mono text-[10.5px] font-semibold text-primary">
            ≈16–18 ondas · 4 blocos
          </Badge>
        }
      />

      <div className="space-y-1 px-6 pb-24 pt-7">
        {/* Lede */}
        <h2 className="text-[22px] font-semibold leading-tight tracking-tight text-foreground">
          ≈ 16–18 ondas pro DS inteiro — <span className="text-primary">e um placar que prova</span>.
        </h2>
        <p className="max-w-[76ch] pt-3 text-[15px] leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          Resposta direta: <b>≈16–18 ondas, em 4 blocos</b> (re-baselinado contra o git nesta sessão).
          Fundação do DS <b>já existe</b> → portar as telas construídas → <b>Atendimento por último</b> → trava.
          Cada onda tem <b>entrega aplicável</b> e um <b>teste de saída</b>; e existe <b>um placar único — o Ledger</b> —
          que diz, a qualquer momento, quantas telas estão 100% no DS. Você nunca depende da minha palavra:
          depende do <b>verde do placar</b>.
        </p>

        {/* Metric band */}
        <div className="pt-6">
          <KpiGrid cols={4}>
            <KpiCard icon="layers" tone="default" label="Fundação (primitivos)" value="7" description="→ ~1–2 · o DS já existe no git" />
            <KpiCard icon="layout-grid" tone="default" label="Portar telas construídas" value="14" description="1 onda por tela (grandes = 2)" />
            <KpiCard icon="message-circle" tone="default" label="Atendimento (por último)" value="2" description="migrar a Caixa pro DS" />
            <KpiCard icon="lock" tone="default" label="Trava (guard)" value="1" description="liga o ratchet" />
          </KpiGrid>
        </div>

        <p className="pt-3 text-xs leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          <b>Importante:</b> as ~20 telas que ainda são stub (cv, repair, fiscal, projetos…) <b>não entram na conta</b> —
          elas <b>nascem no DS</b> quando forem construídas, sem onda de porte. A conta é só das telas que já existem com UI real.
        </p>

        {/* CORREÇÃO PÓS-GIT */}
        <Callout
          tone="warn"
          title={<>⚠ Correção pós-git — conferido <Code>@main 87726ae</Code></>}
        >
          <p>
            A primeira contagem foi medida no <b>protótipo Cowork</b>, não no repo. Conferindo o git,
            <b> o DS já está construído</b> — e isso encolhe muito o Bloco A.
          </p>
          <p>
            O <Code>main</Code> já tem <Code>ui/</Code> (button · avatar · badge · input · select · <b>command</b> · sheet ·
            segmented · field-state · form-section), <Code>layout/</Code> (Box/Stack/Inline/Grid/Text — ADR 0253),
            <Code>shared/</Code> (DataTable · KpiCard · <b>StatusBadge</b> · PageHeader · EmptyState · SubNav),
            <Code>cockpit/</Code> (<b>Thread</b> · Sidebar), <Code>CommandPalette.tsx</Code> e <Code>foundations.css</Code>.
            E os <b>gates do "teste" já existem</b>: <Code>conformance-gate</Code> · <Code>foundation-guard</Code> ·
            <Code>components-tree-guard</Code> · <Code>design-identity-grade</Code> · <Code>ds-report</Code> · <Code>a11y-ratchet</Code>.
          </p>
          <p>
            <b>O que muda:</b> "criar os primitivos" (Bloco A) é quase tudo <b>já feito</b>. O trabalho <b>real</b> vira
            <b> fazer cada tela USAR esses componentes</b> — várias ainda carregam CSS bespoke gigante
            (<Code>sells-cowork.css</Code> 159KB, <Code>cowork-canon-financeiro-bundle.css</Code> 191KB).
          </p>
        </Callout>

        {/* MEDIÇÃO REAL */}
        <GroupHeader tag="★" count="Produto/Create.tsx + inventário">Medição real — li o git nesta sessão</GroupHeader>
        <p className="mb-4 text-[15px] leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          Reli o repo. Li a fundo o <b>piloto A4</b> (<Code>Pages/Produto/Create.tsx</Code>) e inventariei
          <Code>ui/</Code>, <Code>shared/</Code>, <Code>layout/</Code>, <Code>css/</Code>, <Code>scripts/</Code>.
          O gap <b>não é "falta DS"</b> — é <b>adoção parcial + cor ainda crua</b>:
        </p>
        <WaveTable>
          <thead>
            <tr>
              <Th className="w-[30%]">No piloto <Code>Produto/Create.tsx</Code></Th>
              <Th className="w-[14%]">Estado</Th>
              <Th className="w-[56%]">Evidência (lida @main)</Th>
            </tr>
          </thead>
          <tbody>
            {MEDICAO_REAL.map((r, i) => (
              <tr key={i}>
                <Td className="[&_b]:font-semibold">{r.item}</Td>
                <Td className={r.estadoTone === 'pos' ? 'font-semibold text-success' : 'font-semibold text-destructive'}>
                  {r.estado}
                </Td>
                <Td className="text-muted-foreground">{r.evidencia}</Td>
              </tr>
            ))}
          </tbody>
        </WaveTable>
        <Callout title="O espectro real de adoção">
          <p>
            <b>Produto</b> = muito componente, <b>zero token de cor</b>. No outro extremo, <b>Sells/Vendas</b> carrega
            <Code>sells-cowork.css</Code> (159KB bespoke) e a <b>Caixa do git</b> já é V4 própria (<Code>ComposerV4</Code> 35KB).
            Ou seja: <b>cada tela está num ponto diferente</b> da adoção.
          </p>
          <p>
            Por isso o <b>1º passo medível não é "construir"</b> — é um <b>censo de adoção</b> (a 1ª coluna do Ledger):
            rodar <Code>ds-report.mjs</Code> + <Code>conformance-gate</Code> em cada Page e marcar onde está.
            Esse censo <b>já é possível hoje</b> com os scripts que existem no git.
          </p>
        </Callout>
        <Callout tone="pos" title="Contagem corrigida">
          <p>
            <b>Fundação cai de 7 → ~1–2 ondas</b> (o DS existe; no máximo faltam os <b>tokens de hue por canal</b> e
            talvez 1 wrapper de workspace). As <b>~14 telas continuam</b>, mas cada onda fica <b>menor e mais mecânica</b>:
            por tela vira <b>(1) trocar cor crua → token</b> [o grosso] + <b>(2) terminar a adoção</b> (header/checkbox →
            componente que já existe). <b>Atendimento</b>: a Caixa do git já é V4 completa → "portar" = alinhar token.
          </p>
          <p>
            <b>Total realista ≈ 16–18 ondas</b>, a maioria sendo <b>tokenização de cor</b> — não construção de DS.
            O método e os testes seguem idênticos; só o trabalho por onda é mais leve.
          </p>
        </Callout>

        {/* BLOCO A */}
        <GroupHeader tag="A" count="7 ondas">Fundação — construir o DS a partir da Caixa</GroupHeader>
        <WaveTable>
          <thead>
            <tr>
              <Th className="w-[8%]">Onda</Th>
              <Th className="w-[34%]">Entrega</Th>
              <Th className="w-[24%]">Piloto / alvo</Th>
              <Th className="w-[34%]">Teste de saída (mensurável)</Th>
            </tr>
          </thead>
          <tbody>
            {BLOCO_A.map((w) => (
              <tr key={w.id}>
                <Td className="whitespace-nowrap font-mono font-bold text-primary">{w.id}</Td>
                <Td className="[&_b]:font-semibold">{w.entrega}</Td>
                <Td><PilotPill>{w.piloto}</PilotPill></Td>
                <Td className="text-muted-foreground [&_b]:font-semibold [&_b]:text-success">{w.teste}</Td>
              </tr>
            ))}
          </tbody>
        </WaveTable>
        <Callout tone="pos" title="Os pilotos JÁ são o primeiro porte">
          <p>
            A4 (cadastro de produto) e A3 (ficha de CRM) não são testes jogados fora — <b>são a primeira tela portada</b>
            daquele primitivo. O piloto e o porte são a mesma onda. Por isso o Bloco B não reconta essas duas.
          </p>
        </Callout>

        {/* BLOCO B */}
        <GroupHeader tag="B" count="14 ondas">Portar as telas construídas — 1 onda por tela (grandes = 2)</GroupHeader>
        <WaveTable>
          <thead>
            <tr>
              <Th className="w-[8%]">Onda</Th>
              <Th className="w-[30%]">Tela</Th>
              <Th className="w-[28%]">Primitivos que adota</Th>
              <Th className="w-[34%]">Teste de saída</Th>
            </tr>
          </thead>
          <tbody>
            {BLOCO_B.map((w) => (
              <tr key={w.id}>
                <Td className="whitespace-nowrap font-mono font-bold text-primary">{w.id}</Td>
                <Td className="[&_b]:font-semibold">{w.tela}</Td>
                <Td className="text-muted-foreground">{w.adota}</Td>
                <Td className="text-muted-foreground [&_b]:font-semibold [&_b]:text-success">{w.teste}</Td>
              </tr>
            ))}
          </tbody>
        </WaveTable>
        <p className="pt-3 text-xs leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          Ordem por valor pra Larissa (balcão) primeiro. <b>KB</b> e <b>Financeiro</b> já estão em alto padrão → ondas leves.
          O número 14 sobe ou desce conforme dividirmos os módulos grandes (Vendas/Oficina/Financeiro) em 1 ou 2 ondas —
          por isso "≈".
        </p>

        {/* BLOCO C */}
        <GroupHeader tag="C" count="2 ondas">Atendimento por último — fechar o loop</GroupHeader>
        <WaveTable>
          <thead>
            <tr>
              <Th className="w-[8%]">Onda</Th>
              <Th className="w-[36%]">Entrega</Th>
              <Th className="w-[56%]">Teste de saída</Th>
            </tr>
          </thead>
          <tbody>
            {BLOCO_C.map((w) => (
              <tr key={w.id}>
                <Td className="whitespace-nowrap font-mono font-bold text-primary">{w.id}</Td>
                <Td className="[&_b]:font-semibold">{w.entrega}</Td>
                <Td className="text-muted-foreground [&_b]:font-semibold [&_b]:text-success">{w.teste}</Td>
              </tr>
            ))}
          </tbody>
        </WaveTable>
        <Callout tone="warn" title="Por que o Atendimento é o último, não o primeiro">
          <p>
            A Caixa é o <b>molde</b>. Enquanto as outras telas estão sendo portadas, ela fica <b>intocada</b> servindo de
            referência. Só quando o DS já provou que reproduz a qualidade dela em N telas, migra-se a própria Caixa pra
            rodar no DS — e o teste é justamente que <b>nada muda</b> (diff 0). Mexer nela antes seria perder a régua.
          </p>
        </Callout>

        {/* BLOCO D + LEDGER */}
        <GroupHeader tag="D" count="1 onda + medição contínua">A trava + o placar que prova "tudo aplicado"</GroupHeader>
        <p className="mb-4 max-w-[80ch] text-[15px] leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          A pergunta "como sei que <b>tudo</b> foi aplicado de verdade?" tem resposta mecânica: o <b>Ledger de Conformidade DS</b>.
          Cada tela é uma linha; cada coluna é um teste automático. O sistema só está 100% portado quando
          <b> todas as células ficam verdes</b> — e isso é o probe rodando, não a minha palavra.
        </p>

        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full border-collapse text-[12.5px]">
              <thead>
                <tr>
                  <Th className="w-[34%]">Tela</Th>
                  <Th className="text-center">Tokens 0 cru</Th>
                  <Th className="text-center">Primitivos</Th>
                  <Th className="text-center">Probe G1–G13</Th>
                  <Th className="text-center">Dark</Th>
                  <Th className="text-center">[W] aprovou</Th>
                </tr>
              </thead>
              <tbody>
                {census.ledger.map((row, i) => (
                  <tr key={i} className={row.reference ? 'bg-warning/5' : undefined}>
                    <Td className="font-semibold">
                      {row.screen}
                      {row.note && <span className="mt-0.5 block text-[10.5px] font-normal text-muted-foreground">{row.note}</span>}
                    </Td>
                    <Td className="text-center"><Ck state={row.cells.tokens} /></Td>
                    <Td className="text-center"><Ck state={row.cells.primitivos} /></Td>
                    <Td className="text-center"><Ck state={row.cells.probe} /></Td>
                    <Td className="text-center"><Ck state={row.cells.dark} /></Td>
                    <Td className="text-center"><Ck state={row.cells.approved} /></Td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        {/* placar: medição real vs TODO */}
        <Inline align="center" gap={3} className="mt-3.5">
          <span className="font-mono text-[11px] uppercase tracking-wide text-muted-foreground">{measured ? 'HOJE' : 'TODO'}</span>
          <div className="h-3 flex-1 overflow-hidden rounded-full border border-border bg-muted">
            <div
              className="h-full rounded-full bg-gradient-to-r from-warning to-success transition-[width]"
              style={{ width: `${pct}%` }}
            />
          </div>
          <span className="font-mono text-sm font-bold text-foreground tabular-nums">{pct}%</span>
        </Inline>

        {/* carimbo: a tela só mostra número que veio de gate rodando */}
        <Inline align="center" gap={2} wrap className="mt-2.5">
          {measured ? (
            <Badge variant="outline" className="border-success/30 bg-success/10 font-mono text-[10px] font-semibold text-success">
              ● medido @{census.measuredAgainstSha} · {census.generatedAt?.slice(0, 16).replace('T', ' ')} UTC
            </Badge>
          ) : (
            <Badge variant="outline" className="border-warning/40 bg-warning/10 font-mono text-[10px] font-semibold text-warning">
              ▲ snapshot estático · TODO ledger — rode npm run ds:ledger -- --write
            </Badge>
          )}
          {census.treeGuard && (
            <Badge
              variant="outline"
              className={
                census.treeGuard.pass
                  ? 'border-success/30 bg-success/10 font-mono text-[10px] font-semibold text-success'
                  : 'border-destructive/30 bg-destructive/10 font-mono text-[10px] font-semibold text-destructive'
              }
            >
              árvore Components/: {census.treeGuard.pass ? '✓ canônica' : `✗ ${census.treeGuard.violations ?? '?'} violação(ões)`}
            </Badge>
          )}
          {measured && census.counts && (
            <span className="font-mono text-[10px] text-muted-foreground tabular-nums">
              {census.counts.done}/{census.counts.screens} telas verdes · {census.counts.references} referência
            </span>
          )}
        </Inline>

        <p className="pt-2.5 text-xs leading-relaxed text-muted-foreground [&_b]:font-semibold [&_b]:text-foreground">
          <b>{census.progressLabel ?? 'adoção tokens + primitivos'}.</b> Cada onda acende as células da sua linha; a barra
          sobe sozinha. <b>100% = todas verdes</b> = a onda D (trava) liga o guard pra nunca regredir.
        </p>
        <Inline align="center" gap={2} wrap className="mt-1.5 text-[10.5px] text-muted-foreground/80">
          <span className="font-semibold uppercase tracking-wide">Legenda</span>
          <span className="inline-flex items-center gap-1"><Ck state="yes" /> conforme</span>
          <span className="inline-flex items-center gap-1"><Ck state="no" /> falta</span>
          <span className="inline-flex items-center gap-1"><Ck state="ref" /> referência (o ouro)</span>
          <span className="inline-flex items-center gap-1"><Ck state="na" /> não medido — probe G1–13 + dark são Camada 2 (probe de browser), fora do censo estático</span>
        </Inline>

        {/* BLOCO E — provas */}
        <GroupHeader tag="E">O teste de cada onda — 3 verdes objetivos</GroupHeader>
        <Grid min="md" gap={3}>
          {PROOFS.map((p, i) => (
            <Card key={i}>
              <CardContent className="p-4">
                <div className="mb-2.5 grid place-items-center h-7 w-7 rounded-md bg-success/15 text-success">
                  {p.icon}
                </div>
                <b className="mb-1 block text-[12.5px] font-semibold text-foreground">{p.title}</b>
                <p className="text-[11.5px] leading-snug text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-[10px] [&_code]:text-primary">
                  {p.body}
                </p>
              </CardContent>
            </Card>
          ))}
        </Grid>

        {/* Footer */}
        <div className="mt-12 border-t border-border pt-5 text-[11.5px] leading-relaxed text-muted-foreground [&_b]:text-foreground">
          <p>
            <b>Resumo:</b> ≈16–18 ondas — <b>~1–2</b> fundação (o DS já existe) · <b>14</b> portar telas ·
            <b> 2</b> Atendimento (último) · <b>1</b> trava. Conta só telas com UI real; stubs nascem no DS.
          </p>
          <p className="mt-1.5">
            <b>O teste de "tudo aplicado" é o Ledger</b> chegando a 100% verde — probe automático por tela +
            antes/depois + diff zero. Mecânico, não opinião.
          </p>
          <Inline align="center" gap={1} wrap className="mt-3 text-muted-foreground/80">
            <GitCommitHorizontal className="h-3.5 w-3.5" />
            <span>Oimpresso ERP · governança DS · rollout em ondas medíveis ·</span>
            {measured ? (
              <span>censo via <Code>scripts/ds-ledger.mjs</Code> @{census.measuredAgainstSha} (<ScanLine className="inline h-3 w-3" /> {census.counts?.screens ?? 0} telas)</span>
            ) : (
              <span>rode <Code>npm run ds:ledger -- --write</Code> pra medir (<ScanLine className="inline h-3 w-3" /> censo)</span>
            )}
          </Inline>
        </div>
      </div>
    </div>
  )
}

DsRollout.layout = (page: ReactNode) => <AppShellV2 children={page} />

export default DsRollout
