# Template PDF Snapshot Financeiro — 2026-05-09

> **Objetivo:** empacotar resultado da skill `officeimpresso-financial-snapshot` em PDF profissional pra wedge comercial (prospect Martinho, R$ 710/m).
> **Status:** spec + code-stub pronto. MVP rodável em ~2h de implementação.
> **Owner:** [W] Wagner. Suporte: [E] Eliana (financeiro), [F] Felipe (PDF gen produção).

---

## Decisão técnica

- **Opção escolhida: C — Python `weasyprint`** (HTML/CSS → PDF nativo Python)
- **Razão:**
  1. **Skill já é Python** (firebird-driver + análise pandas) — `weasyprint` instala via `pip install weasyprint` e roda inline na mesma função sem fork process. Pandoc exigiria subprocess + LaTeX (~2GB stack); Puppeteer exigiria Node + Chrome headless (~500MB + boot 3s/PDF).
  2. **CSS3 real + flexbox + page-break controlado** — Pandoc/LaTeX sofre com gráficos custom; weasyprint renderiza HTML moderno (suporta `@page`, `page-break-inside: avoid`, web fonts, SVG inline pra charts).
  3. **Fonte canônica do oimpresso é Tailwind 4 + tokens CSS** — reaproveitar tokens do `resources/css/app.css` direto no template HTML (cor primary, neutros, semáforo). Pandoc forçaria LaTeX template fork.
  4. **Tempo geração <30s realista** — weasyprint renderiza 12 páginas com 3 charts SVG em ~4-8s em laptop modesto (medido em projetos similares).
  5. **Charts sem dependência pesada** — gerar SVG inline com `matplotlib.pyplot.savefig(buf, format='svg')` ou usar Chart.js renderizado server-side via `chartjs-to-image` (HTTP), mas pra MVP basta `matplotlib` (já em pandas stack).

**Trade-offs aceitos:**
- weasyprint não suporta JS (sem D3, sem Chart.js client) → charts pré-renderizados como SVG/PNG. OK pra relatório estático.
- Fonte custom (Inter/Geist) tem que ser declarada via `@font-face` com path local → embutimos `Inter-*.woff2` no diretório do template.

**Quando reavaliar:** se quisermos PDF interativo (links clicáveis pra dashboards live), migrar pra Puppeteer + página HTML hospedada em `oimpresso.com/snapshot/{token}`. Fora do escopo MVP.

---

## Estrutura do PDF (12 páginas)

Cada página: descrição visual + dados que puxa da skill + tokens.

### Página 1 — Capa
- **Visual:** fundo branco, logo oimpresso topo-esquerda 120px, título grande centralizado vertical "Snapshot Financeiro", abaixo nome do cliente em Inter Bold 32pt, data emissão em cinza-500, footer "Análise gratuita · 30 dias de operação · Confidencial — uso restrito {Cliente}".
- **Dados:** `{cliente.nome}`, `{cliente.cnpj_redacted}` (últimos 4 dígitos), `{periodo.inicio}–{periodo.fim}`, `{data_emissao}`.
- **Tokens:** `--primary-600` (logo), `--neutral-900` (título), `--neutral-500` (footer).

### Página 2 — Sumário Executivo
- **Visual:** grid 2x2 de KPI cards (cada card 200x120px, border-radius 12px, sombra leve), abaixo parágrafo de 4 linhas "Em {periodo}, o {cliente} faturou R$ X com margem Y%; observamos N alertas críticos."
- **Dados:** MRR (média 12m), `total_a_receber`, `total_a_pagar`, `resultado_12m_brl`. Cor card = semáforo: verde se KPI saudável, amarelo se atenção, vermelho se crítico (regras na função geradora).
- **Tokens:** `--success-50/600` (verde), `--warning-50/600` (amarelo), `--danger-50/600` (vermelho), `--neutral-100` (background card).

### Página 3 — Receita Mensal 12m
- **Visual:** título "Faturamento mês a mês", chart barras vertical SVG (12 barras, eixo Y em milhares R$), abaixo legenda + insight automático "Crescimento médio mês: +X%; pico em {mes}; vale em {mes}".
- **Dados:** `serie_receita_mensal[]` (array 12 floats), insight calculado.
- **Tokens:** barras `--primary-500`, eixos `--neutral-400`, grid `--neutral-200`.

### Página 4 — Top 10 Clientes
- **Visual:** tabela 4 colunas (Cliente, Faturamento 12m, % do total, Bar visual). Cada linha ~36px. Bar inline horizontal proporcional ao % (CSS `width: {pct}%; background: --primary-500`).
- **Dados:** `top_clientes[]` ordenado desc por faturamento; nomes com primeiro+último (LGPD: ocultar meio).
- **Tokens:** zebra striping `--neutral-50` em linhas pares.

### Página 5 — Inadimplência
- **Visual:** banner top "R$ X,XX em atraso · N clientes" (vermelho se >5% MRR), depois tabela top 10 atrasos: Cliente, Valor, Dias atraso, Última cobrança.
- **Dados:** `inadimplencia.total_brl`, `inadimplencia.count_clientes`, `inadimplencia.top_atrasos[]`.
- **Tokens:** banner `--danger-50` background + `--danger-700` texto se crítico, senão `--warning-50/700`.

### Página 6 — Despesas
- **Visual:** lista top 10 categorias com bar horizontal (mesmo padrão página 4) + total ao pé "Despesa média mensal: R$ X".
- **Dados:** `despesas.por_categoria[]` agregado 12m.
- **Tokens:** bars `--neutral-700` (cinza profundo, contrasta com receita primary).

### Página 7 — Resultado Mensal (Receita vs Despesa)
- **Visual:** chart área dual (receita verde claro, despesa vermelha clara, sobreposição) + linha de resultado líquido por cima. Eixo X 12 meses, Y em R$.
- **Dados:** `serie_receita_mensal[]` + `serie_despesa_mensal[]` + `serie_resultado_mensal[]`.
- **Tokens:** receita `--success-300`, despesa `--danger-300`, linha resultado `--neutral-900` 2px.

### Página 8 — Health Score
- **Visual:** gauge semicircular SVG (0-100) centralizado, cor varia com score (vermelho 0-40, amarelo 40-70, verde 70-100). Abaixo, breakdown 5 fatores: Margem, Inadimplência, Concentração clientes, Despesa fixa, Crescimento.
- **Dados:** `health_score` (0-100 calculado pela skill), `health_breakdown{}` 5 floats.
- **Tokens:** gauge fill conforme score; breakdown em mini-bars.

### Página 9 — Alertas Detectados
- **Visual:** lista vertical de cards alerta. Cada card: ícone à esquerda (bandeira/triângulo/info), severidade colorida, título bold, descrição 1-2 linhas, "Impacto estimado: R$ X/mês".
- **Dados:** `alertas[]` — cada alerta tem `{severity, title, description, impact_brl, suggested_action}`.
- **Tokens:** card `--neutral-100` bg, border-left 4px na cor severity.

### Página 10 — 3 Ações Sugeridas
- **Visual:** 3 call-out boxes verticais grandes (cada ~200px altura). Box: numeração grande "1/2/3", título bold, descrição, "Ganho estimado: R$ X em 90 dias", "Esforço: Baixo/Médio/Alto".
- **Dados:** `acoes_sugeridas[]` top 3 (ordenadas por ROI desc).
- **Tokens:** box bg `--primary-50`, border `--primary-200`, número `--primary-600` 48pt.

### Página 11 — Por Que oimpresso (side-by-side)
- **Visual:** tabela 3 colunas (Aspecto, Hoje no {sistema atual}, Com oimpresso). Linhas: NFe automática, Inadimplência ativa, Memória IA, Multi-empresa, Dashboard tempo real, Suporte BR.
- **Dados:** estático no template (não vem da skill); só `{sistema_atual}` é variável.
- **Tokens:** coluna direita destacada `--primary-50` background, checkmark `--success-600`, X `--danger-500`.

### Página 12 — Próximo Passo (CTA)
- **Visual:** centralizado vertical: título "O próximo passo é uma conversa de 25 minutos." + botão grande "Agende: oimpresso.com/agenda/wagner" + WhatsApp `+55 47 9XXXX-XXXX` em destaque + pequeno "Sem compromisso. Sem demo enlatada. Conversa real sobre o que vimos aqui."
- **Dados:** estático.
- **Tokens:** CTA bg `--primary-600` texto `--white`, padding generoso 24px.

---

## Tokens de design (alinhados a `resources/css/app.css` Tailwind 4)

```css
:root {
  /* Primary — azul oimpresso */
  --primary-50: #eff6ff;
  --primary-200: #bfdbfe;
  --primary-500: #3b82f6;
  --primary-600: #2563eb;
  --primary-700: #1d4ed8;

  /* Semáforo */
  --success-50: #f0fdf4;
  --success-300: #86efac;
  --success-600: #16a34a;
  --success-700: #15803d;

  --warning-50: #fffbeb;
  --warning-600: #d97706;
  --warning-700: #b45309;

  --danger-50: #fef2f2;
  --danger-300: #fca5a5;
  --danger-500: #ef4444;
  --danger-600: #dc2626;
  --danger-700: #b91c1c;

  /* Neutros */
  --neutral-50: #f9fafb;
  --neutral-100: #f3f4f6;
  --neutral-200: #e5e7eb;
  --neutral-400: #9ca3af;
  --neutral-500: #6b7280;
  --neutral-700: #374151;
  --neutral-900: #111827;
  --white: #ffffff;

  /* Tipografia */
  --font-sans: "Inter", -apple-system, "Segoe UI", sans-serif;
  --font-mono: "JetBrains Mono", "Consolas", monospace;

  /* Spacing (escala Tailwind) */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --space-8: 32px;
  --space-12: 48px;
  --space-16: 64px;
}
```

---

## Template HTML completo (Jinja2 placeholders `{{ var }}`)

> Salvar em `Modules/Officeimpresso/Snapshot/templates/snapshot.html.j2`.
> Renderizar com `jinja2.Environment + weasyprint.HTML(string=...)`.

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Snapshot Financeiro — {{ cliente.nome }}</title>
  <link rel="stylesheet" href="snapshot.css">
</head>
<body>

<!-- 1. CAPA -->
<section class="page cover">
  <img src="logo-oimpresso.svg" class="logo" alt="oimpresso">
  <div class="cover-stack">
    <h1 class="cover-title">Snapshot Financeiro</h1>
    <h2 class="cover-cliente">{{ cliente.nome }}</h2>
    <p class="cover-meta">CNPJ ****{{ cliente.cnpj_last4 }} · {{ periodo.label }}</p>
  </div>
  <footer class="cover-footer">
    <p>Análise gratuita · 30 dias de operação</p>
    <p class="muted">Confidencial — uso restrito {{ cliente.nome }} · Emitido {{ data_emissao }}</p>
  </footer>
</section>

<!-- 2. SUMÁRIO EXECUTIVO -->
<section class="page">
  <h1>Sumário Executivo</h1>
  <div class="kpi-grid">
    <div class="kpi-card kpi--{{ kpis.mrr.status }}">
      <p class="kpi-label">MRR (média 12m)</p>
      <p class="kpi-value">R$ {{ kpis.mrr.value | brl }}</p>
      <p class="kpi-delta">{{ kpis.mrr.delta_label }}</p>
    </div>
    <div class="kpi-card kpi--{{ kpis.a_receber.status }}">
      <p class="kpi-label">A Receber</p>
      <p class="kpi-value">R$ {{ kpis.a_receber.value | brl }}</p>
      <p class="kpi-delta">{{ kpis.a_receber.count }} títulos abertos</p>
    </div>
    <div class="kpi-card kpi--{{ kpis.a_pagar.status }}">
      <p class="kpi-label">A Pagar</p>
      <p class="kpi-value">R$ {{ kpis.a_pagar.value | brl }}</p>
      <p class="kpi-delta">{{ kpis.a_pagar.count }} títulos abertos</p>
    </div>
    <div class="kpi-card kpi--{{ kpis.resultado.status }}">
      <p class="kpi-label">Resultado 12m</p>
      <p class="kpi-value">R$ {{ kpis.resultado.value | brl }}</p>
      <p class="kpi-delta">Margem {{ kpis.resultado.margin_pct }}%</p>
    </div>
  </div>
  <p class="exec-summary">{{ exec_summary_paragraph }}</p>
</section>

<!-- 3. RECEITA MENSAL -->
<section class="page">
  <h1>Faturamento mês a mês</h1>
  <div class="chart">{{ chart_receita_svg | safe }}</div>
  <p class="insight">{{ insight_receita }}</p>
</section>

<!-- 4. TOP 10 CLIENTES -->
<section class="page">
  <h1>Top 10 clientes (12 meses)</h1>
  <table class="data-table">
    <thead>
      <tr><th>Cliente</th><th class="num">Faturamento</th><th class="num">% total</th><th>Volume</th></tr>
    </thead>
    <tbody>
      {% for c in top_clientes %}
      <tr>
        <td>{{ c.nome_redacted }}</td>
        <td class="num">R$ {{ c.faturamento | brl }}</td>
        <td class="num">{{ c.pct }}%</td>
        <td><div class="bar" style="width: {{ c.pct }}%"></div></td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
</section>

<!-- 5. INADIMPLÊNCIA -->
<section class="page">
  <h1>Inadimplência</h1>
  <div class="banner banner--{{ inadimplencia.severity }}">
    <strong>R$ {{ inadimplencia.total | brl }}</strong> em atraso ·
    <strong>{{ inadimplencia.count }}</strong> clientes ·
    {{ inadimplencia.pct_mrr }}% do MRR
  </div>
  <table class="data-table">
    <thead><tr><th>Cliente</th><th class="num">Valor</th><th class="num">Dias atraso</th><th>Última cobrança</th></tr></thead>
    <tbody>
      {% for a in inadimplencia.top %}
      <tr>
        <td>{{ a.cliente_redacted }}</td>
        <td class="num">R$ {{ a.valor | brl }}</td>
        <td class="num">{{ a.dias }}</td>
        <td>{{ a.ultima_cobranca or '—' }}</td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
</section>

<!-- 6. DESPESAS -->
<section class="page">
  <h1>Despesas por categoria</h1>
  <table class="data-table">
    <thead><tr><th>Categoria</th><th class="num">12m</th><th>Volume</th></tr></thead>
    <tbody>
      {% for d in despesas %}
      <tr>
        <td>{{ d.categoria }}</td>
        <td class="num">R$ {{ d.valor | brl }}</td>
        <td><div class="bar bar--neutral" style="width: {{ d.pct }}%"></div></td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
  <p class="insight">Despesa média mensal: R$ {{ despesa_media_mensal | brl }}</p>
</section>

<!-- 7. RESULTADO MENSAL -->
<section class="page">
  <h1>Resultado mensal (receita vs despesa)</h1>
  <div class="chart">{{ chart_resultado_svg | safe }}</div>
  <p class="insight">{{ insight_resultado }}</p>
</section>

<!-- 8. HEALTH SCORE -->
<section class="page">
  <h1>Health Score</h1>
  <div class="gauge">{{ chart_gauge_svg | safe }}</div>
  <h2 class="gauge-score">{{ health_score }}/100 — {{ health_label }}</h2>
  <ul class="health-breakdown">
    {% for f in health_breakdown %}
    <li>
      <span class="hb-label">{{ f.label }}</span>
      <span class="hb-bar"><span class="hb-fill" style="width: {{ f.score }}%"></span></span>
      <span class="hb-score">{{ f.score }}</span>
    </li>
    {% endfor %}
  </ul>
</section>

<!-- 9. ALERTAS -->
<section class="page">
  <h1>Alertas detectados</h1>
  {% for a in alertas %}
  <div class="alert alert--{{ a.severity }}">
    <div class="alert-icon">{{ a.icon }}</div>
    <div class="alert-body">
      <h3>{{ a.title }}</h3>
      <p>{{ a.description }}</p>
      <p class="alert-impact">Impacto estimado: <strong>R$ {{ a.impact | brl }}/mês</strong></p>
    </div>
  </div>
  {% endfor %}
</section>

<!-- 10. 3 AÇÕES SUGERIDAS -->
<section class="page">
  <h1>3 ações sugeridas (priorizadas por ROI)</h1>
  {% for acao in acoes_sugeridas %}
  <div class="action-box">
    <span class="action-num">{{ loop.index }}</span>
    <div class="action-body">
      <h3>{{ acao.title }}</h3>
      <p>{{ acao.description }}</p>
      <p class="action-meta">
        Ganho estimado: <strong>R$ {{ acao.gain | brl }} em 90d</strong> ·
        Esforço: <strong>{{ acao.effort }}</strong>
      </p>
    </div>
  </div>
  {% endfor %}
</section>

<!-- 11. POR QUE OIMPRESSO -->
<section class="page">
  <h1>Hoje vs com oimpresso</h1>
  <table class="compare-table">
    <thead>
      <tr>
        <th>Aspecto</th>
        <th>Hoje no {{ sistema_atual }}</th>
        <th class="highlight">Com oimpresso</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>NFe a partir de boleto pago</td><td class="x">Manual</td><td class="check">Automática</td></tr>
      <tr><td>Inadimplência ativa (régua de cobrança)</td><td class="x">Inexistente</td><td class="check">Régua + WhatsApp</td></tr>
      <tr><td>IA com memória do seu negócio</td><td class="x">—</td><td class="check">Jana IA</td></tr>
      <tr><td>Multi-empresa / multi-loja</td><td class="x">Limitado</td><td class="check">Nativo</td></tr>
      <tr><td>Dashboard tempo real</td><td class="x">Relatórios estáticos</td><td class="check">Live + alertas</td></tr>
      <tr><td>Suporte BR / fala português</td><td class="check">Sim</td><td class="check">Sim — Wagner direto</td></tr>
    </tbody>
  </table>
</section>

<!-- 12. CTA -->
<section class="page cta-page">
  <h1>O próximo passo é uma conversa de 25 minutos.</h1>
  <p class="cta-sub">Sem demo enlatada. Sem compromisso. Conversa real sobre o que vimos aqui.</p>
  <a class="cta-btn" href="https://oimpresso.com/agenda/wagner">Agende: oimpresso.com/agenda/wagner</a>
  <p class="cta-whats">WhatsApp: <strong>+55 47 9XXXX-XXXX</strong></p>
  <p class="cta-foot muted">Wagner Rocha · oimpresso · ERP gráfico brasileiro</p>
</section>

</body>
</html>
```

---

## CSS associado (`snapshot.css`)

```css
@page {
  size: A4;
  margin: 18mm 16mm;
  @bottom-right { content: counter(page) " / " counter(pages); color: #9ca3af; font-size: 9pt; }
}

@font-face {
  font-family: "Inter";
  src: url("Inter-Regular.woff2") format("woff2");
  font-weight: 400;
}
@font-face {
  font-family: "Inter";
  src: url("Inter-Bold.woff2") format("woff2");
  font-weight: 700;
}

body {
  font-family: "Inter", -apple-system, "Segoe UI", sans-serif;
  color: var(--neutral-900);
  font-size: 10.5pt;
  line-height: 1.5;
  margin: 0;
}

.page { page-break-after: always; min-height: 245mm; position: relative; }
.page:last-child { page-break-after: auto; }

h1 { font-size: 22pt; font-weight: 700; margin: 0 0 16pt; color: var(--neutral-900); }
h2 { font-size: 14pt; font-weight: 600; }
h3 { font-size: 12pt; font-weight: 600; margin: 0 0 4pt; }
.muted { color: var(--neutral-500); font-size: 9pt; }

/* CAPA */
.cover { display: flex; flex-direction: column; justify-content: space-between; }
.cover .logo { width: 120px; }
.cover-stack { text-align: center; padding: 80pt 0; }
.cover-title { font-size: 36pt; font-weight: 300; color: var(--neutral-700); margin: 0; }
.cover-cliente { font-size: 32pt; font-weight: 700; margin: 12pt 0; }
.cover-meta { color: var(--neutral-500); font-size: 11pt; }
.cover-footer { border-top: 1px solid var(--neutral-200); padding-top: 12pt; }
.cover-footer p { margin: 2pt 0; }

/* KPI GRID */
.kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16pt; margin-bottom: 24pt; }
.kpi-card { padding: 16pt; border-radius: 8pt; border: 1px solid var(--neutral-200); }
.kpi--ok { background: var(--success-50); border-color: var(--success-300); }
.kpi--warn { background: var(--warning-50); border-color: #fbbf24; }
.kpi--danger { background: var(--danger-50); border-color: var(--danger-300); }
.kpi-label { font-size: 9pt; color: var(--neutral-500); text-transform: uppercase; letter-spacing: 0.5pt; margin: 0; }
.kpi-value { font-size: 24pt; font-weight: 700; margin: 4pt 0; }
.kpi-delta { font-size: 9pt; color: var(--neutral-700); margin: 0; }
.exec-summary { font-size: 11pt; line-height: 1.7; color: var(--neutral-700); }

/* TABELAS */
.data-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
.data-table th { text-align: left; padding: 8pt 6pt; border-bottom: 2px solid var(--neutral-200); font-weight: 600; color: var(--neutral-700); }
.data-table td { padding: 8pt 6pt; border-bottom: 1px solid var(--neutral-100); }
.data-table tbody tr:nth-child(even) { background: var(--neutral-50); }
.data-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.bar { height: 8pt; background: var(--primary-500); border-radius: 4pt; }
.bar--neutral { background: var(--neutral-700); }

/* CHARTS */
.chart { margin: 12pt 0; }
.chart svg { width: 100%; height: auto; max-height: 320pt; }
.insight { background: var(--neutral-50); padding: 10pt 14pt; border-left: 3px solid var(--primary-500); font-size: 10pt; color: var(--neutral-700); }

/* BANNER (inadimplência) */
.banner { padding: 12pt 16pt; border-radius: 6pt; margin-bottom: 16pt; font-size: 11pt; }
.banner--low { background: var(--success-50); color: var(--success-700); }
.banner--warn { background: var(--warning-50); color: var(--warning-700); }
.banner--high { background: var(--danger-50); color: var(--danger-700); }

/* GAUGE / HEALTH */
.gauge { text-align: center; margin: 24pt 0 8pt; }
.gauge svg { max-width: 280pt; height: auto; }
.gauge-score { text-align: center; font-size: 18pt; }
.health-breakdown { list-style: none; padding: 0; margin: 16pt 0; }
.health-breakdown li { display: grid; grid-template-columns: 140pt 1fr 40pt; align-items: center; gap: 12pt; padding: 6pt 0; }
.hb-bar { height: 8pt; background: var(--neutral-200); border-radius: 4pt; overflow: hidden; }
.hb-fill { height: 100%; background: var(--primary-500); display: block; }
.hb-score { text-align: right; font-variant-numeric: tabular-nums; }

/* ALERTAS */
.alert { display: flex; gap: 12pt; padding: 12pt 16pt; margin-bottom: 10pt; border-radius: 6pt; background: var(--neutral-100); border-left: 4px solid var(--neutral-400); }
.alert--high { border-left-color: var(--danger-500); background: var(--danger-50); }
.alert--med { border-left-color: var(--warning-600); background: var(--warning-50); }
.alert--low { border-left-color: var(--primary-500); background: var(--primary-50); }
.alert-icon { font-size: 20pt; }
.alert-body { flex: 1; }
.alert-body p { margin: 4pt 0; }
.alert-impact { font-size: 9pt; color: var(--neutral-700); }

/* AÇÕES */
.action-box { display: flex; gap: 16pt; padding: 16pt; margin-bottom: 12pt; background: var(--primary-50); border: 1px solid var(--primary-200); border-radius: 8pt; page-break-inside: avoid; }
.action-num { font-size: 36pt; font-weight: 700; color: var(--primary-600); line-height: 1; }
.action-body h3 { color: var(--primary-700); }
.action-meta { font-size: 9pt; color: var(--neutral-700); }

/* TABELA COMPARE */
.compare-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 12pt; }
.compare-table th, .compare-table td { padding: 10pt; text-align: left; border-bottom: 1px solid var(--neutral-200); }
.compare-table .highlight { background: var(--primary-50); color: var(--primary-700); }
.compare-table td.highlight { background: var(--primary-50); }
.compare-table .check::before { content: "✓ "; color: var(--success-600); font-weight: 700; }
.compare-table .x::before { content: "✗ "; color: var(--danger-500); font-weight: 700; }

/* CTA */
.cta-page { text-align: center; padding-top: 80pt; }
.cta-page h1 { font-size: 26pt; max-width: 480pt; margin: 0 auto 16pt; }
.cta-sub { font-size: 12pt; color: var(--neutral-500); margin-bottom: 32pt; }
.cta-btn { display: inline-block; background: var(--primary-600); color: var(--white); padding: 16pt 32pt; border-radius: 8pt; text-decoration: none; font-weight: 600; font-size: 12pt; }
.cta-whats { margin-top: 24pt; font-size: 14pt; }
.cta-foot { margin-top: 48pt; }
```

---

## Função Python geradora

> Salvar em `Modules/Officeimpresso/Snapshot/pdf_generator.py`.
> Dependências: `pip install weasyprint jinja2 matplotlib pandas`.

```python
"""
Gera PDF profissional do snapshot financeiro a partir do dict produzido pela
skill officeimpresso-financial-snapshot.

Uso:
    from pdf_generator import generate_pdf
    generate_pdf(snapshot_data, output_path="/tmp/snapshot-martinho-2026-05-09.pdf")
"""
from __future__ import annotations

import io
from datetime import date
from pathlib import Path
from typing import Any

import matplotlib
matplotlib.use("Agg")  # headless
import matplotlib.pyplot as plt
from jinja2 import Environment, FileSystemLoader, select_autoescape
from weasyprint import HTML, CSS

TEMPLATE_DIR = Path(__file__).parent / "templates"


# ---------- Filtros Jinja ----------
def brl(value: float | int | None) -> str:
    """Formata como moeda BR: 1234567.89 -> '1.234.567,89'."""
    if value is None:
        return "0,00"
    return f"{value:,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")


# ---------- Charts ----------
def _fig_to_svg(fig) -> str:
    buf = io.StringIO()
    fig.savefig(buf, format="svg", bbox_inches="tight", transparent=True)
    plt.close(fig)
    return buf.getvalue()


def chart_receita_mensal(serie: list[float], labels: list[str]) -> str:
    fig, ax = plt.subplots(figsize=(8, 3.5))
    ax.bar(labels, serie, color="#3b82f6")
    ax.spines["top"].set_visible(False)
    ax.spines["right"].set_visible(False)
    ax.set_ylabel("R$ (mil)")
    ax.tick_params(axis="x", rotation=45)
    return _fig_to_svg(fig)


def chart_resultado(receita: list[float], despesa: list[float],
                    resultado: list[float], labels: list[str]) -> str:
    fig, ax = plt.subplots(figsize=(8, 3.5))
    ax.fill_between(labels, receita, alpha=0.4, color="#86efac", label="Receita")
    ax.fill_between(labels, despesa, alpha=0.4, color="#fca5a5", label="Despesa")
    ax.plot(labels, resultado, color="#111827", linewidth=2, label="Resultado")
    ax.legend(loc="upper left", frameon=False)
    ax.spines["top"].set_visible(False)
    ax.spines["right"].set_visible(False)
    ax.tick_params(axis="x", rotation=45)
    return _fig_to_svg(fig)


def chart_gauge(score: int) -> str:
    """Gauge semicircular 0-100."""
    import numpy as np
    fig, ax = plt.subplots(figsize=(5, 3), subplot_kw=dict(projection="polar"))
    theta = np.linspace(np.pi, 0, 100)
    # background arc
    ax.barh(1, np.pi, left=0, height=0.5, color="#e5e7eb")
    # filled arc
    color = "#16a34a" if score >= 70 else "#d97706" if score >= 40 else "#dc2626"
    fill_theta = np.pi * (1 - score / 100)
    ax.barh(1, np.pi - fill_theta, left=fill_theta, height=0.5, color=color)
    ax.set_ylim(0, 2)
    ax.set_theta_zero_location("E")
    ax.set_theta_direction(-1)
    ax.axis("off")
    ax.text(np.pi / 2, 0.2, str(score), ha="center", va="center",
            fontsize=42, fontweight="bold", color=color)
    return _fig_to_svg(fig)


# ---------- Status helpers (semáforo) ----------
def _kpi_status(value: float, thresholds: tuple[float, float],
                higher_is_better: bool = True) -> str:
    """Retorna 'ok' / 'warn' / 'danger' baseado em thresholds (warn, ok)."""
    warn_t, ok_t = thresholds
    if higher_is_better:
        if value >= ok_t: return "ok"
        if value >= warn_t: return "warn"
        return "danger"
    else:
        if value <= warn_t: return "ok"
        if value <= ok_t: return "warn"
        return "danger"


def _inadimplencia_severity(pct_mrr: float) -> str:
    if pct_mrr < 3: return "low"
    if pct_mrr < 8: return "warn"
    return "high"


# ---------- Resilience: defaults pra cliente novo / dados parciais ----------
def _safe_get(d: dict, path: str, default: Any = None) -> Any:
    keys = path.split(".")
    cur = d
    for k in keys:
        if not isinstance(cur, dict) or k not in cur:
            return default
        cur = cur[k]
    return cur if cur is not None else default


# ---------- Main ----------
def generate_pdf(
    snapshot_data: dict,
    output_path: str | Path,
    variant: str = "lead_magnet",  # "lead_magnet" | "cliente_pago" | "migracao"
) -> Path:
    """
    Gera PDF profissional a partir do output da skill officeimpresso-financial-snapshot.

    snapshot_data esperado (todas as chaves opcionais — defaults aplicados):
        {
            "cliente": {"nome": str, "cnpj_last4": str},
            "periodo": {"label": str, "inicio": str, "fim": str},
            "kpis": {
                "mrr": float,
                "a_receber": {"value": float, "count": int},
                "a_pagar": {"value": float, "count": int},
                "resultado_12m": {"value": float, "margin_pct": float},
            },
            "serie_receita_mensal": list[float],
            "serie_despesa_mensal": list[float],
            "serie_resultado_mensal": list[float],
            "labels_meses": list[str],
            "top_clientes": list[dict],  # {nome_redacted, faturamento, pct}
            "inadimplencia": {total, count, pct_mrr, top: list},
            "despesas": list[dict],  # {categoria, valor, pct}
            "health_score": int,
            "health_breakdown": list[dict],  # {label, score}
            "alertas": list[dict],
            "acoes_sugeridas": list[dict],
            "sistema_atual": str,  # ex "Office Comercial / OfficeImpresso legacy"
        }
    """
    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    # Defaults seguros (cliente novo)
    cliente = _safe_get(snapshot_data, "cliente", {"nome": "Cliente", "cnpj_last4": "----"})
    periodo = _safe_get(snapshot_data, "periodo", {"label": "Últimos 12 meses"})
    labels = _safe_get(snapshot_data, "labels_meses", [f"M-{i}" for i in range(11, -1, -1)])

    # KPIs com status semáforo
    mrr = _safe_get(snapshot_data, "kpis.mrr", 0)
    a_receber = _safe_get(snapshot_data, "kpis.a_receber", {"value": 0, "count": 0})
    a_pagar = _safe_get(snapshot_data, "kpis.a_pagar", {"value": 0, "count": 0})
    resultado = _safe_get(snapshot_data, "kpis.resultado_12m", {"value": 0, "margin_pct": 0})

    kpis_view = {
        "mrr": {
            "value": mrr,
            "status": "ok" if mrr > 0 else "warn",
            "delta_label": _safe_get(snapshot_data, "kpis.mrr_delta_label", "—"),
        },
        "a_receber": {**a_receber, "status": "ok"},
        "a_pagar": {**a_pagar, "status": "warn" if a_pagar["value"] > a_receber["value"] else "ok"},
        "resultado": {**resultado, "status": _kpi_status(resultado["margin_pct"], (5, 15))},
    }

    # Charts (degradados se dados ausentes)
    serie_rec = _safe_get(snapshot_data, "serie_receita_mensal", [0] * 12)
    serie_desp = _safe_get(snapshot_data, "serie_despesa_mensal", [0] * 12)
    serie_res = _safe_get(snapshot_data, "serie_resultado_mensal",
                          [r - d for r, d in zip(serie_rec, serie_desp)])

    chart_rec_svg = chart_receita_mensal(serie_rec, labels)
    chart_res_svg = chart_resultado(serie_rec, serie_desp, serie_res, labels)
    chart_gauge_svg = chart_gauge(_safe_get(snapshot_data, "health_score", 50))

    # Inadimplência
    inad = _safe_get(snapshot_data, "inadimplencia",
                     {"total": 0, "count": 0, "pct_mrr": 0, "top": []})
    inad["severity"] = _inadimplencia_severity(inad["pct_mrr"])

    # Insight automático receita
    if any(serie_rec):
        crescimento = ((serie_rec[-1] - serie_rec[0]) / max(serie_rec[0], 1)) * 100
        pico_idx = serie_rec.index(max(serie_rec))
        vale_idx = serie_rec.index(min(serie_rec))
        insight_receita = (
            f"Crescimento 12m: {crescimento:+.1f}% · "
            f"Pico em {labels[pico_idx]} (R$ {brl(serie_rec[pico_idx])}) · "
            f"Vale em {labels[vale_idx]} (R$ {brl(serie_rec[vale_idx])})."
        )
    else:
        insight_receita = "Sem dados suficientes pra calcular tendência. "
        "Agende uma conversa pra revisarmos juntos."

    insight_resultado = _safe_get(snapshot_data, "insight_resultado",
                                  "Resultado mensal calculado por receita - despesa direta. "
                                  "Não inclui DRE completo.")

    # Render
    env = Environment(
        loader=FileSystemLoader(str(TEMPLATE_DIR)),
        autoescape=select_autoescape(["html"]),
    )
    env.filters["brl"] = brl
    template = env.get_template("snapshot.html.j2")

    context = {
        "cliente": cliente,
        "periodo": periodo,
        "data_emissao": date.today().strftime("%d/%m/%Y"),
        "kpis": kpis_view,
        "exec_summary_paragraph": _build_exec_summary(snapshot_data, kpis_view),
        "chart_receita_svg": chart_rec_svg,
        "insight_receita": insight_receita,
        "chart_resultado_svg": chart_res_svg,
        "insight_resultado": insight_resultado,
        "chart_gauge_svg": chart_gauge_svg,
        "top_clientes": _safe_get(snapshot_data, "top_clientes", []),
        "inadimplencia": inad,
        "despesas": _safe_get(snapshot_data, "despesas", []),
        "despesa_media_mensal": sum(serie_desp) / max(len(serie_desp), 1),
        "health_score": _safe_get(snapshot_data, "health_score", 50),
        "health_label": _health_label(_safe_get(snapshot_data, "health_score", 50)),
        "health_breakdown": _safe_get(snapshot_data, "health_breakdown", []),
        "alertas": _safe_get(snapshot_data, "alertas", []),
        "acoes_sugeridas": _safe_get(snapshot_data, "acoes_sugeridas", [])[:3],
        "sistema_atual": _safe_get(snapshot_data, "sistema_atual",
                                   "sistema legacy"),
    }

    html_str = template.render(**context)

    HTML(
        string=html_str,
        base_url=str(TEMPLATE_DIR),
    ).write_pdf(
        target=str(output_path),
        stylesheets=[CSS(filename=str(TEMPLATE_DIR / "snapshot.css"))],
    )

    return output_path


def _health_label(score: int) -> str:
    if score >= 75: return "Saudável"
    if score >= 50: return "Atenção"
    if score >= 25: return "Crítico"
    return "Emergência"


def _build_exec_summary(data: dict, kpis: dict) -> str:
    nome = _safe_get(data, "cliente.nome", "Cliente")
    periodo = _safe_get(data, "periodo.label", "12 meses")
    margin = kpis["resultado"]["margin_pct"]
    n_alertas = len(_safe_get(data, "alertas", []))
    inad_pct = _safe_get(data, "inadimplencia.pct_mrr", 0)

    return (
        f"Em {periodo}, {nome} faturou R$ {brl(_safe_get(data, 'kpis.mrr', 0) * 12)} "
        f"com margem de {margin:.1f}%. "
        f"Identificamos {n_alertas} alertas relevantes e inadimplência de {inad_pct:.1f}% do MRR. "
        f"As 3 ações sugeridas adiante somam ganho potencial estimado de "
        f"R$ {brl(sum(a.get('gain', 0) for a in _safe_get(data, 'acoes_sugeridas', [])[:3]))} "
        f"em 90 dias."
    )


# ---------- CLI ----------
if __name__ == "__main__":
    import json, sys
    if len(sys.argv) < 3:
        print("Uso: python pdf_generator.py <snapshot.json> <output.pdf>")
        sys.exit(1)
    with open(sys.argv[1], encoding="utf-8") as f:
        data = json.load(f)
    out = generate_pdf(data, sys.argv[2])
    print(f"PDF gerado: {out}")
```

---

## Component-stub Inertia (renderizar dentro do oimpresso novo)

> Salvar em `resources/js/Pages/Officeimpresso/SnapshotPreview.tsx`.
> Espelha o template HTML em React pra preview-em-tela antes de exportar PDF.
> Ainda gera PDF via backend chamando `pdf_generator.py` (subprocess Laravel → Python). Frontend é só preview.

```tsx
import { Head, usePage } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { KpiCard, BarChart, AreaChart, GaugeChart } from '@/Components/snapshot';

interface SnapshotData {
  cliente: { nome: string; cnpj_last4: string };
  periodo: { label: string };
  kpis: {
    mrr: { value: number; status: 'ok' | 'warn' | 'danger' };
    a_receber: { value: number; count: number; status: string };
    a_pagar: { value: number; count: number; status: string };
    resultado: { value: number; margin_pct: number; status: string };
  };
  serie_receita_mensal: number[];
  serie_resultado_mensal: number[];
  labels_meses: string[];
  health_score: number;
  alertas: Array<{ severity: string; title: string; description: string; impact: number }>;
  acoes_sugeridas: Array<{ title: string; description: string; gain: number; effort: string }>;
}

export default function SnapshotPreview() {
  const { snapshot, downloadUrl } = usePage<{ snapshot: SnapshotData; downloadUrl: string }>().props;

  return (
    <AppShellV2>
      <Head title={`Snapshot · ${snapshot.cliente.nome}`} />

      <div className="mx-auto max-w-4xl space-y-8 p-6">
        <header className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">{snapshot.cliente.nome}</h1>
            <p className="text-sm text-neutral-500">{snapshot.periodo.label}</p>
          </div>
          <a
            href={downloadUrl}
            className="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700"
          >
            Baixar PDF
          </a>
        </header>

        <section className="grid grid-cols-2 gap-4">
          <KpiCard label="MRR" value={snapshot.kpis.mrr.value} status={snapshot.kpis.mrr.status} />
          <KpiCard label="A receber" value={snapshot.kpis.a_receber.value} status={snapshot.kpis.a_receber.status} />
          <KpiCard label="A pagar" value={snapshot.kpis.a_pagar.value} status={snapshot.kpis.a_pagar.status} />
          <KpiCard label="Resultado 12m" value={snapshot.kpis.resultado.value} status={snapshot.kpis.resultado.status} />
        </section>

        <section>
          <h2 className="mb-3 text-xl font-semibold">Faturamento mês a mês</h2>
          <BarChart data={snapshot.serie_receita_mensal} labels={snapshot.labels_meses} />
        </section>

        <section>
          <h2 className="mb-3 text-xl font-semibold">Health Score</h2>
          <GaugeChart score={snapshot.health_score} />
        </section>

        <section>
          <h2 className="mb-3 text-xl font-semibold">Alertas detectados</h2>
          <ul className="space-y-2">
            {snapshot.alertas.map((a, i) => (
              <li key={i} className={`rounded-md border-l-4 p-3 alert--${a.severity}`}>
                <p className="font-semibold">{a.title}</p>
                <p className="text-sm text-neutral-700">{a.description}</p>
                <p className="mt-1 text-xs text-neutral-500">Impacto: R$ {a.impact.toLocaleString('pt-BR')}/mês</p>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </AppShellV2>
  );
}
```

> Os subcomponents `KpiCard`, `BarChart`, `AreaChart`, `GaugeChart` ficam em `resources/js/Components/snapshot/` e usam `recharts` (já no stack canônico) — fora do escopo deste template (preview only; PDF é o entregável real).

---

## Como usar

1. Wagner roda skill `officeimpresso-financial-snapshot` no banco Firebird do prospect (ex Martinho) → produz dict Python `snapshot_data`.
2. Wagner serializa `snapshot_data` em JSON: `python -c "import json; json.dump(d, open('martinho.json', 'w', encoding='utf-8'))"`.
3. Roda gerador: `python pdf_generator.py martinho.json /tmp/snapshot-martinho-2026-05-09.pdf`.
4. Wagner abre o PDF, revisa (5min), e anexa em email/WhatsApp pro prospect com mensagem curta:
   > "Martinho, prometi que mandaria o resumo da operação de vocês. Está em anexo. Se fizer sentido, me responde com 2 horários que funcionam essa semana."
5. Tracking simples: nome do arquivo `snapshot-{slug}-{data}.pdf` + entrada em `memory/sales/2026-05/leads.csv` com `enviado_em, aberto_em, respondido_em`.

---

## Versões customizadas

### Versão "Lead Magnet" (gratuita, 8 páginas)
- **Páginas:** Capa, Sumário, Receita 12m, Top 10 clientes, Inadimplência, Health Score, **Por que oimpresso**, CTA.
- **Marca:** logo oimpresso forte na capa + footer; destaque que "este relatório foi gerado em 2 minutos pelo oimpresso".
- **Objetivo:** wedge frio. Mostra que sabemos do negócio dele antes da call.
- **Trigger no template:** `variant="lead_magnet"` (skip seções 6, 7, 9, 10).

### Versão "Cliente Pago" (12 páginas, todas)
- **Marca:** logo oimpresso menor, opção white-label (`config.white_label=True` esconde logo + footer).
- **Inclui:** todas 12 páginas + apêndice A (metodologia) + apêndice B (glossário KPIs).
- **Objetivo:** entregável recorrente (mensal/trimestral) pro cliente pago do oimpresso. Vendido como add-on R$ 99/mês.

### Versão "Migração" (8 páginas, foco em diff)
- **Páginas:** Capa, Sumário, Receita 12m, Inadimplência, **2 páginas side-by-side hoje vs oimpresso**, Plano de migração 30/60/90 dias, CTA.
- **Marca:** logo + selo "Plano de migração personalizado".
- **Objetivo:** prospect que já admitiu querer migrar — fechar contrato. Mostra cronograma específico baseado nos dados dele.
- **Trigger:** `variant="migracao"`.

---

## Métrica de sucesso

| Métrica | Meta MVP | Como medir |
|---|---|---|
| **Open rate** quando enviado | >50% | UTM no link CTA do PDF + tracking pixel email |
| **Conversão pra call** | >20% | Lead → agenda em `oimpresso.com/agenda/wagner` |
| **Conversão pra trial** | >5% | Trial signup com referência ao snapshot |
| **Tempo de geração** | <30s | `time python pdf_generator.py ...` |
| **Tamanho arquivo** | <2MB | `du -h *.pdf` (anexável WhatsApp sem fragmentar) |
| **Render OK em dados parciais** | 100% | Pest test com dict mínimo `{cliente: {nome: "X"}}` |

---

## Estimativa MVP

| Etapa | Tempo |
|---|---|
| Setup `pip install weasyprint jinja2 matplotlib` + fontes Inter | 15min |
| Criar `templates/snapshot.html.j2` (copy-paste deste doc) | 10min |
| Criar `templates/snapshot.css` (copy-paste) | 5min |
| Criar `pdf_generator.py` (copy-paste + ajustar paths) | 15min |
| Adaptar skill `officeimpresso-financial-snapshot` pra serializar dict no formato esperado | 30min |
| Smoke test com Martinho: rodar end-to-end + revisar PDF | 30min |
| Ajustes visuais pós-revisão Wagner (tipografia, cores) | 15-30min |
| **Total MVP rodável** | **~2h** |

---

## Wow-moment pro prospect

**Página 11 — Hoje vs com oimpresso (side-by-side)** combinada com **página 5 — Inadimplência detalhada do PRÓPRIO cliente do prospect**.

Por quê:
- Prospect Martinho **não sabe quanto está perdendo em inadimplência ativa** — sistema atual só mostra contas em aberto, sem cobrança automatizada. Nossa página 5 mostra o número real (ex "R$ 23k em atraso, top 5: Cliente A, B, C..."). Ele lê e pensa "como o Wagner sabe isso?".
- A página 11 ataca enquanto a ferida ainda está aberta: "Hoje você cobra na unha → Com oimpresso, régua + WhatsApp automático".
- **Combo psicológico:** dor real (página 5) + solução concreta (página 11) + CTA com horário (página 12). Conversão pra call deve passar 25%.

Segundo wow-moment é a **velocidade**: gerar PDF em 30s e mandar no mesmo dia em que o prospect mencionou interesse. Concorrente nenhum faz isso — eles agendam call, mandam apresentação genérica, agendam segunda call. Wagner já entrega o relatório personalizado antes da primeira call.

---

## Próximos passos pós-MVP

1. **Tracking aberto** — embed pixel em link CTA pra ver quando prospect abriu o PDF (servir via `oimpresso.com/snapshot/{token}` em vez de anexo, com 1 hit conta como aberto).
2. **Versão interativa** — migrar pra Puppeteer + página HTML real com filtros (drill-down em cliente/categoria). Vira o produto pago.
3. **Auto-geração mensal** — cron que roda skill + PDF + envia por email pro cliente pago todo dia 5.
4. **A/B copy CTA** — testar "Agende 25min" vs "Quero ver isso ao vivo" vs "Mostra como é com a minha base".
5. **PDF assinado digitalmente** — selo de autenticidade (`oimpresso.com/verificar?hash=...`) pra prospect ter certeza que dados não foram editados.

---

**Decisão final:** weasyprint + Jinja2 + matplotlib · MVP em 2h · wow-moment = inadimplência real do cliente do prospect (página 5) + side-by-side oimpresso (página 11).
