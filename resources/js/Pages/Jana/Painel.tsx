// @memcofre
//   tela: /jana/painel
//   stories: US-JANA-PAINEL-001 (Onda A1)
//   adrs: 0035 stack-ai, 0039 ui-cockpit-pattern, 0093 multi-tenant, 0114 cowork-loop
//   visual-canon: prototipo-ui/cowork-snapshot/chat-jana.jsx (491 ln IIFE window.JanaCockpit)
//   status: onda-a1-esqueleto (mock data; sub-components + CSS canon + queries reais nas Ondas A2-C)
//   module: Jana
//   cycle: CYCLE-06 goal #4 Jana V2 demo apresentável a 1 piloto

import React from 'react'
import { Head } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'

interface BriefData {
  greeting: string
  paragraphs: string[]
  chips: Array<{ tone: string; icon: string; label: string }>
}

interface KpiData {
  label: string
  value: string
  delta?: string
  deltaCls?: string
  icon: string
  sub?: string
  emphasize?: boolean
}

interface AnaliseData {
  id: string
  title: string
  sub: string
  pill?: { tone: string; label: string }
  icon: string
  kind: 'buckets' | 'sparkline' | 'bars' | 'list' | 'donut' | 'text'
  big?: { value: string; color?: string }
}

interface AcaoData {
  id: string
  icon: string
  tone: string
  title: string
  sub: string
  cta: { label: string; tone: string }
}

interface PainelPayload {
  person: { name: string; role: string; avatar: string }
  updatedAt: string
  today: string
  brief: BriefData
  kpis: KpiData[]
  analises: AnaliseData[]
  acoes: AcaoData[]
}

interface Props {
  business: { id: number; name: string; version: string }
  painel: PainelPayload
}

/**
 * Painel · Cockpit do Analista IA Jana V2.
 *
 * Onda A1 — esqueleto Inertia/React renderizando mock data do PainelController.
 * Sub-components KPI/Análise/Brief/Ação ainda inline (extração nas Ondas A2-A3).
 * CSS canon (classes .jc-*) virá na Onda A4 — visualmente esta tela ainda é HTML cru.
 * Queries SQL reais substituem mock na Onda B.
 * BriefDiarioAgent (LLM-generated narrative) integra na Onda C.
 */
export default function Painel({ business, painel }: Props) {
  return (
    <AppShellV2 title="Jana · Painel">
      <Head title="Jana · Painel" />

      <div className="jc-page" data-screen-label="Jana — Dashboard">
        <header className="jc-header">
          <div className="jc-header-l">
            <div className="jc-avatar">{painel.person.avatar}</div>
            <div className="jc-id">
              <h1>
                {painel.person.name} <span className="dot">·</span> {painel.person.role}
              </h1>
              <p>
                <span className="jc-tenant">{business.name.toUpperCase()}</span>
                <span className="jc-sep">·</span>
                biz={business.id}
                <span className="jc-sep">·</span>
                {business.version}
              </p>
            </div>
          </div>
          <div className="jc-header-r">
            <span className="jc-updated">
              <span className="d" />
              Atualizado {painel.updatedAt}
            </span>
            <button className="jc-btn ghost">⚙ Configurar</button>
            <button className="jc-btn dark">⬇ Exportar</button>
          </div>
        </header>

        <section className="jc-brief">
          <div className="jc-brief-h">
            <span className="jc-brief-h-l">
              📅 <b>Brief diário</b> · {painel.today}
            </span>
            <span className="jc-pill ia">IA</span>
          </div>
          <p className="jc-brief-greet">
            <strong>{painel.brief.greeting}</strong>
          </p>
          {painel.brief.paragraphs.map((p, i) => (
            <p key={i}>{p}</p>
          ))}
          <div className="jc-brief-chips">
            {painel.brief.chips.map((c, i) => (
              <button key={i} className={'jc-chip ' + c.tone}>
                <span className="ic">{c.icon}</span> {c.label}
              </button>
            ))}
          </div>
        </section>

        <div className="jc-kpis">
          {painel.kpis.map((k, i) => (
            <div key={i} className={'jc-kpi' + (k.emphasize ? ' emph' : '')}>
              <div className="jc-kpi-h">
                <span>{k.label.toUpperCase()}</span>
                <span className="jc-kpi-ic">{k.icon}</span>
              </div>
              <b className={'jc-kpi-v ' + (k.deltaCls === 'red big' ? 'red' : '')}>{k.value}</b>
              {k.delta && <small className={'jc-kpi-d ' + (k.deltaCls || '')}>{k.delta}</small>}
              {k.sub && <small className="jc-kpi-d">{k.sub}</small>}
            </div>
          ))}
        </div>

        <h2 className="jc-h2">
          <span className="ic">📊</span> ANÁLISES PRINCIPAIS
        </h2>
        <div className="jc-grid">
          {painel.analises.map((a) => (
            <div key={a.id} className={'jc-an ' + a.kind}>
              <div className="jc-an-h">
                <div className="jc-an-h-l">
                  <span className="jc-an-ic" data-kind={a.id}>
                    {a.icon}
                  </span>
                  <div>
                    <b>{a.title}</b>
                    <small>{a.sub}</small>
                  </div>
                </div>
                {a.pill && <span className={'jc-pill ' + a.pill.tone}>{a.pill.label}</span>}
              </div>
              {a.big && <div className={'jc-an-big ' + (a.big.color || '')}>{a.big.value}</div>}
              <div className="jc-an-placeholder">
                <em>[ {a.kind} ] — sub-component virá na Onda A2</em>
              </div>
            </div>
          ))}
        </div>

        <h2 className="jc-h2">
          <span className="ic">💡</span> AÇÕES QUE {painel.person.name.toUpperCase()} SUGERE
        </h2>
        <div className="jc-acoes">
          {painel.acoes.map((a) => (
            <div key={a.id} className={'jc-acao tone-' + a.tone}>
              <span className="jc-acao-ic">{a.icon}</span>
              <div className="jc-acao-text">
                <b>{a.title}</b>
                <small>{a.sub}</small>
              </div>
              <button className={'jc-cta ' + a.cta.tone}>{a.cta.label}</button>
            </div>
          ))}
        </div>
      </div>
    </AppShellV2>
  )
}
