// Forja — aba Changelog. Porte 1:1 do `ChangelogFeed` de
// `prototipo-ui/cowork/forja-page.jsx` (PARIDADE §11 Onda 9 · ADR 0388 "réplica
// primeiro"): o protótipo é o contrato de LAYOUT, e a conformidade do DS que a
// cópia fiel viola vira linha em memory/requisitos/Forja/INCONSISTENCIAS-replica.md.
//
// Espelho provado SYNC contra o Cowork vivo em 2026-09-02 (hash normalizado do
// `forja-page.jsx` e do `forja-page.css` = o do `DesignSync.get_file`).
//
// ── O que muda em relação à versão anterior ──────────────────────────────────
// A medição de 2026-09-02 (forja-cockpit-visual-comparison.md) pegou a linha de
// produção com **5 colunas achatadas** (dot · id · título · ator · data) contra
// **2** do protótipo — dot + corpo, e o corpo em 3 blocos: topo (ref · flags ·
// data), resumo e meta (selo de ator + módulos). É essa estrutura que entra aqui.
//
// O CSS já estava no chão desde a Onda 1 (`.fj-changelog`, `.fj-clog-tab*`,
// `.fj-feed*`, `.fj-flag*`, `.fj-mod`, `.fj-role*` em cowork-forja-bundle.css) —
// esta onda é markup + shape de dado, zero CSS novo.
//
// ── Dado: real ou ausente, nunca sintético ───────────────────────────────────
// Tudo vem do `ForjaChangelogService` (ADRs/SPECs de mcp_memory_documents +
// sessões de mcp_cc_sessions). PRs e Ondas seguem OMITIDOS por falta de fonte
// confiável no DB — os chips existem porque são do protótipo, e filtrar por eles
// mostra o vazio honesto em vez de um dado inventado.

import { useMemo, useState } from 'react';
import ForjaRoleBadge from './ForjaRoleBadge';

export interface ChangelogEntry {
  // Tipo do evento. Define o dot colorido e o filtro de chip.
  kind: 'pr' | 'adr' | 'session' | 'onda';
  // ID curto/mono (slug da ADR, uuid curto da sessão) — vira `.fj-feed-ref`.
  id: string;
  // Resumo da linha (`.fj-feed-resumo`). VAZIO é legítimo: sessão sem
  // `summary_auto` e sem 1º prompt não ganha rótulo sintético.
  title: string;
  // Selo de ator ([W]/[CC]/[CL]…) — alimenta o <ForjaRoleBadge>.
  actor: string;
  // Data ISO 8601 (ordenação no backend + tooltip). Não é o que se lê na linha.
  date: string;
  // A MESMA data em dd/mm — é o que o protótipo desenha em `.fj-feed-when`.
  date_label: string;
  // Selos do protótipo (`tier-0`, `breaking`) vindos das tags reais do doc.
  flags: string[];
  // Módulos do doc (`.fj-mod sm`). Sessão não tem — vem [].
  modules: string[];
}

interface Props {
  // changelog chega via Inertia::defer (ForjaController@changelog) → undefined no
  // 1º paint. Default-guard `= []` no destructuring pra NÃO crashar antes do defer
  // (skill inertia-defer-default).
  changelog?: ChangelogEntry[];
}

// Cor do dot por tipo — literais do protótipo (`const dot` em ChangelogFeed),
// copiados sem edição. Cor crua em JSX é o que a ADR 0388 D-2 manda REPORTAR na
// lista de inconsistências, não bloquear.
const DOT: Record<ChangelogEntry['kind'], string> = {
  pr: 'oklch(0.52 0.10 195)',
  adr: 'oklch(0.55 0.16 270)',
  session: 'oklch(0.60 0.13 60)',
  onda: 'oklch(0.55 0.13 150)',
};

// Chips do protótipo, na ordem dele. `all` = sem filtro. O segmento **Sessões** é
// o destino que a Onda 2 prometeu ao tirar `CC Sessions` do topnav (comentário do
// FORJA_TABS em ForjaHub.tsx) — a rota /team-mcp/cc-sessions segue viva.
type Filter = 'all' | ChangelogEntry['kind'];
const TABS: ReadonlyArray<readonly [Filter, string]> = [
  ['all', 'Tudo'],
  ['pr', 'PRs'],
  ['adr', 'ADRs'],
  ['session', 'Sessões'],
  ['onda', 'Ondas'],
];

export default function ForjaChangelog({ changelog = [] }: Props) {
  const [filter, setFilter] = useState<Filter>('all');

  const filtered = useMemo(
    () => (filter === 'all' ? changelog : changelog.filter((e) => e.kind === filter)),
    [changelog, filter],
  );

  return (
    <div className="fj-changelog" data-testid="forja-changelog">
      <div className="fj-clog-tabs" role="tablist" aria-label="Filtro do changelog" data-testid="forja-changelog-filtros">
        {TABS.map(([k, l]) => (
          <button
            key={k}
            type="button"
            role="tab"
            aria-selected={filter === k}
            className={'fj-clog-tab' + (filter === k ? ' active' : '')}
            onClick={() => setFilter(k)}
          >
            {l}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        // O `ChangelogFeed` não desenha empty-state (o mock nunca fica vazio), mas
        // em produção filtrar por PRs/Ondas SEMPRE dá zero — sem isto a tela fica
        // muda e parece quebrada. Uso o idioma de vazio do PRÓPRIO protótipo
        // (`<div className="fj-empty"><p>…</p></div>`, forja-page.jsx:588 e :1194):
        // zero CSS novo, vocabulário do design.
        <div className="fj-empty" data-testid="forja-changelog-vazio">
          <p>Nenhum evento real pra esse filtro ainda.</p>
        </div>
      ) : (
        <ul className="fj-feed">
          {filtered.map((e, i) => (
            <li key={`${e.kind}-${e.id}-${i}`} className="fj-feed-item">
              <span
                className="fj-feed-dot"
                style={{ background: DOT[e.kind] }}
                aria-hidden="true"
                data-testid="forja-changelog-dot"
              />
              <div className="fj-feed-body">
                <div className="fj-feed-top">
                  <span className="fj-feed-ref">{e.id}</span>
                  {e.flags.map((f) => (
                    <span key={f} className={'fj-flag fj-flag-' + f}>
                      {f}
                    </span>
                  ))}
                  {/* dd/mm é o que o protótipo mostra; o ISO fica no title pra
                      desambiguar o ano sem mexer no que é renderizado. */}
                  <span className="fj-feed-when" title={e.date || undefined}>
                    {e.date_label}
                  </span>
                </div>
                {/* Resumo vazio = sessão sem summary e sem 1º prompt. Some o
                    parágrafo; não entra rótulo sintético no lugar. */}
                {e.title !== '' && <p className="fj-feed-resumo">{e.title}</p>}
                <div className="fj-feed-meta">
                  <ForjaRoleBadge role={e.actor} />
                  {e.modules.map((m) => (
                    <span key={m} className="fj-mod sm">
                      {m}
                    </span>
                  ))}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
