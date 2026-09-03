// Saúde — view `saude` do protótipo (`prototipo-ui/cowork/forja-page.jsx`, SaudeView).
//
// ADR 0388 "réplica primeiro": o markup e as classes são os do protótipo (`fj-saude`,
// `fj-metric`, `fj-spark`, `fj-wip`, `fj-flux-*`, `fj-age`, `fj-gate-health`) — o CSS já
// está no chão desde a Onda 1 (`resources/css/cowork-forja-bundle.css`). Conformidade de
// DS (oklch inline no sparkline, glifo `→`) NÃO é veto aqui: vira item da lista
// `memory/requisitos/Forja/INCONSISTENCIAS-replica.md`.
//
// O que MUDA em relação ao protótipo, e por quê (nenhum deles é layout):
//   · dado é REAL (`ForjaSaudeService`), não `window.FORJA` mockado;
//   · `ver →` NAVEGA (Inertia <Link>) em vez de filtrar na própria SPA — em produção o
//     destino do drill é outra tela (/forja/trabalho, /team-mcp/scorecard);
//   · o card "Checks verdes" vem SEM sparkline: nenhuma tabela guarda o histórico dele,
//     e linha inventada seria dado fantasma (o serviço manda `serie: null`);
//   · a seção "Automação" (3 toggles) NÃO é replicada: não existe motor de regras em
//     produção, e toggle que não liga nada é controle falso. Declarado no charter/casos.

import { Link } from '@inertiajs/react';

export interface SaudeMetrica {
  label: string;
  valor: string;
  lim: string;
  nota: string;
  /** ok | warn | bad — sufixo da classe `fj-metric-*` (borda superior). */
  estado: string;
  hue: number;
  /** Série 0..1 já normalizada. `null` = sem histórico persistido ⇒ sem sparkline. */
  serie: number[] | null;
  drill: string | null;
}

export interface SaudeFase {
  id: string;
  label: string;
  n: number;
  hue: number;
}

export interface SaudeCheck {
  id: string;
  fase: string;
  /** green | red — sufixo da classe `fj-gate-*` (cor do ponto). */
  estado: string;
  rotulo: string;
}

export interface SaudeData {
  metricas: SaudeMetrica[];
  wip: SaudeFase[];
  fluxo: { entregas: number; aging: { fresco: number; atencao: number; parado: number } };
  checks: SaudeCheck[];
  janelaDias: number;
}

/**
 * Sparkline do protótipo, 1:1: viewBox 60×18, polyline sem fill, stroke por hue.
 * Série de 1 ponto (ou vazia) não desenha — evita divisão por zero virar `NaN` no path.
 */
function Spark({ data, hue }: { data: number[]; hue: number }) {
  if (data.length < 2) return null;

  const pts = data
    .map((d, i) => `${i * (60 / (data.length - 1))},${17 - Math.max(0, Math.min(1, d)) * 15}`)
    .join(' ');

  return (
    <svg className="fj-spark" viewBox="0 0 60 18" preserveAspectRatio="none" aria-hidden="true">
      <polyline points={pts} fill="none" stroke={`oklch(0.55 0.13 ${hue})`} strokeWidth="1.8" strokeLinejoin="round" />
    </svg>
  );
}

export default function ForjaSaude({ saude }: { saude?: SaudeData }) {
  if (!saude) return null;

  const { metricas, wip, fluxo, checks, janelaDias } = saude;
  // Pico do WIP dá a escala das barras. `1` como piso evita 0/0 quando não há card.
  const wipMax = Math.max(1, ...wip.map((w) => w.n));

  return (
    <div className="fj-saude" data-testid="forja-saude">
      <div className="fj-mcp-intro">
        Semáforo do loop, alimentado pelo que já existe (audit log do MCP · eventos de task · checks do
        scorecard · changelog). <b>Cada métrica linka a uma ação</b> — nada decorativo. Janela: {janelaDias} dias.
      </div>

      <div className="fj-saude-grid">
        {metricas.map((m) => (
          <div key={m.label} className={'fj-metric fj-metric-' + m.estado}>
            <div className="fj-metric-top">
              <span className="fj-metric-lbl">{m.label}</span>
              {m.lim && <span className="fj-metric-lim">{m.lim}</span>}
            </div>
            <div className="fj-metric-mid">
              <span className="fj-metric-val">{m.valor}</span>
              {m.serie && <Spark data={m.serie} hue={m.hue} />}
            </div>
            <div className="fj-metric-foot">
              <span>{m.nota}</span>
              {m.drill && (
                <Link href={m.drill} className="fj-metric-drill">
                  ver →
                </Link>
              )}
            </div>
          </div>
        ))}
      </div>

      <section className="fj-mcp-card" style={{ marginTop: 16 }}>
        <h3>Fluxo · WIP por fase</h3>
        <div className="fj-wip">
          {wip.map((w) => (
            <div key={w.id} className="fj-wip-col" title={w.label}>
              <span className="fj-wip-n">{w.n}</span>
              <div
                className="fj-wip-bar"
                style={{ height: 6 + (w.n / wipMax) * 56 + 'px', background: `oklch(0.58 0.13 ${w.hue})` }}
              />
              <span className="fj-wip-lbl">{w.id}</span>
            </div>
          ))}
        </div>
        <div className="fj-flux-row">
          <div className="fj-flux-stat">
            <b>{fluxo.entregas}</b>
            <span>entregas (changelog)</span>
          </div>
          <div className="fj-flux-aging">
            <span className="fj-age fj-age-ok">{fluxo.aging.fresco} fresco</span>
            <span className="fj-age fj-age-warn">{fluxo.aging.atencao} atenção</span>
            <span className="fj-age fj-age-bad">{fluxo.aging.parado} parado</span>
          </div>
        </div>
        <p className="fj-dr-desc" style={{ marginTop: 8 }}>
          WIP por fase e aging saem de `mcp_tasks` (project FORJA); entregas, do changelog. Lead/cycle time
          dependem de timestamp de transição por fase, que ainda não é persistido.
        </p>
      </section>

      <section className="fj-mcp-card" style={{ marginTop: 16 }}>
        <h3>Checks do MCP</h3>
        <ul className="fj-gate-health">
          {checks.map((c) => (
            <li key={c.id}>
              <span className={'fj-gate fj-gate-' + c.estado}>
                <span className="fj-gate-dot" />
                {c.id}
              </span>
              <span className="fj-gate-fase">{c.fase}</span>
              <span className="fj-gate-state">{c.rotulo}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
