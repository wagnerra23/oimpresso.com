// ForjaHub — header ÚNICO do hub Forja, usado por TODAS as telas sob /forja/* e
// pelas absorvidas /team-mcp/*: o mesmo header em tudo.
//
// ── 2026-09-02 · PARIDADE §11 Onda 2 — o header É o do protótipo ──────────────
// Decisão [W] (ADR 0388, "réplica primeiro"): a aparência é a do `forja-page.jsx`.
// Markup copiado do protótipo (linhas 1105-1129): `.os-page-h` com título+subtítulo à
// esquerda e, À DIREITA NA MESMA LINHA, sino · busca ⌘K · 3 pílulas de grupo
// (Trabalho / Esteira / Histórico) com 6 destinos · primária "Novo issue". Antes o
// topnav era uma SEGUNDA linha inteira sob o PageHeader canon (medido em 2026-09-01:
// 13 destinos, 1447px, não cabia a 1280; o protótipo tem 6 em 784px).
//
// As classes vêm do bundle `cowork-forja-bundle.css` (Onda 1); `.fj-hub` é o root
// deste componente e o escopo das deps de render do shell (`.os-page-h*`, `.os-btn*`).
// Ícones: lucide, no tamanho do protótipo (11). Os glifos do protótipo (✦ ⚠ …) não
// existem neste header — só ⌘K, que já estava aqui.
//
// O que NÃO mudou de contrato:
//   · `FORJA_TABS` continua exportado com `href:` literal — o `ForjaRoutesSmokeTest`
//     (UC-FORJA-14) lê este arquivo como TEXTO e cruza com `config/core_topnavs.php`.
//     As duas listas TÊM que bater na mesma ordem (lápide §5 2026-08-06).
//   · `data-testid`: forja-sino · forja-busca · forja-novo-issue · forja-tabs ·
//     forja-grupo-<key> — os mesmos de antes, pra e2e/a11y não quebrar.
//
// Destinos que SAÍRAM do topo nesta onda (as rotas seguem vivas): Triagem (vira tipo
// Proposta em Aprovações, Onda 3), Handoffs (seção do MCP, Onda 8), Equipe (idem),
// CC Sessions (segmento Sessões do Changelog, Onda 9). Saúde aponta pro Scorecard
// até a Onda 7 construir a view do protótipo. Integrador NASCE nesta onda
// (`/forja/integrador`, `ForjaIntegrador`).

import { Link } from '@inertiajs/react';
import { Activity, Bell, Gavel, History, ListChecks, Plug, Plus, Search, ShieldCheck } from 'lucide-react';

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — aprovações da equipe, backlog, pipeline de telas F0→F3.5, tarefas de todas as frentes, changelog e atores (humano vs agente).';

export const FORJA_GRUPOS = [
  { key: 'trabalho',  label: 'Trabalho' },
  { key: 'esteira',   label: 'Esteira' },
  { key: 'historico', label: 'Histórico' },
] as const;

// Ordem = a do protótipo (grupo a grupo). `key` é o `active` que cada Page passa.
export const FORJA_TABS = [
  { key: 'aprovacoes', grupo: 'trabalho',  label: 'Aprovações', href: '/forja/aprovacoes',   icon: Gavel,
    hint: 'O que espera por uma decisão sua' },
  { key: 'trabalho',   grupo: 'trabalho',  label: 'Trabalho',   href: '/forja/trabalho',     icon: ListChecks,
    hint: 'Todas as tasks do time — lista, quadro e gantt' },
  { key: 'saude',      grupo: 'esteira',   label: 'Saúde',      href: '/team-mcp/scorecard', icon: Activity,
    hint: 'Semáforo do loop (hoje: scorecard do MCP; a view do protótipo é a Onda 7)' },
  { key: 'mcp',        grupo: 'esteira',   label: 'MCP',        href: '/forja/mcp',          icon: ShieldCheck,
    hint: 'Contrato de ferramentas, tokens e auditoria' },
  { key: 'changelog',  grupo: 'historico', label: 'Changelog',  href: '/forja/changelog',    icon: History,
    hint: 'O que shippou — PRs, ADRs, sessões e ondas' },
  { key: 'integrador', grupo: 'historico', label: 'Integrador', href: '/forja/integrador',   icon: Plug,
    hint: 'Forja ↔ TeamMcp: o que absorve, o que alinha, o que falta' },
] as const;

// Abre a command palette global (dona do AppShellV2, atalho ⌘K) sintetizando o
// keydown que o shell escuta no window.
function openCommandPalette() {
  window.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'k', metaKey: true, ctrlKey: true, bubbles: true }),
  );
}

export default function ForjaHub({
  active,
  triagemCount,
  pendencias,
}: {
  active: string;
  /** contagem viva da fila de triagem — vai pro sino (é a "minha fila" do protótipo) */
  triagemCount?: number;
  /** pendências da mesa de Aprovações — badge na aba, como no protótipo (`fj-tab-badge`) */
  pendencias?: number;
}) {
  const sino = triagemCount ?? 0;

  return (
    <div className="fj-hub" data-testid="forja-hub">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Forja</h1>
          <p>{COCKPIT_SUBTITLE}</p>
        </div>
        <div className="os-page-h-r">
          <button type="button" className="fj-bell" title="Minha fila" aria-label="Minha fila" data-testid="forja-sino">
            <Bell size={14} strokeWidth={1.7} />
            {sino > 0 && <span className="fj-bell-badge">{sino}</span>}
          </button>

          <button type="button" className="fj-kbtn" onClick={openCommandPalette} title="Paleta de comandos" aria-label="Buscar (⌘K)" data-testid="forja-busca">
            <Search size={11} />
            Buscar
            <kbd>⌘K</kbd>
          </button>

          <div className="fj-viewtabs grouped" data-testid="forja-tabs">
            {FORJA_GRUPOS.map((grupo) => {
              const tabs = FORJA_TABS.filter((t) => t.grupo === grupo.key);
              return (
                <div key={grupo.key} className="fj-navgroup" role="group" aria-label={grupo.label}>
                  <span className="fj-navgroup-lbl" data-testid={`forja-grupo-${grupo.key}`}>{grupo.label}</span>
                  {tabs.map((t) => {
                    const Icon = t.icon;
                    const isActive = t.key === active;
                    const badge = t.key === 'aprovacoes' ? pendencias : undefined;
                    return (
                      <Link
                        key={t.key}
                        href={t.href}
                        title={t.hint}
                        aria-current={isActive ? 'page' : undefined}
                        className={isActive ? 'active' : ''}
                      >
                        <Icon size={11} />
                        {t.label}
                        {badge != null && badge > 0 && <span className="fj-tab-badge">{badge}</span>}
                      </Link>
                    );
                  })}
                </div>
              );
            })}
          </div>

          <Link href="/forja" className="os-btn primary" data-testid="forja-novo-issue">
            <Plus size={11} />
            Novo issue
          </Link>
        </div>
      </div>
    </div>
  );
}
