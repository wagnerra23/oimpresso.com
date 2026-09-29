---
id: requisitos-ponto-dashboard-visual-comparison
tela: Ponto/Dashboard/Index
url: /ponto
status: pending_approval
approver: _pendente_
prototype_source: "prototipo-ui/cowork/Wagner/ponto-page.jsx (âncora computada por ancora.mjs)"
implementation: resources/js/Pages/Ponto/Dashboard/Index.tsx
adr: 0107
---

# Visual Comparison — Ponto/Dashboard (Painel)

> **O primeiro do módulo.** Até 2026-08-28 o Ponto tinha **21 telas e 0 documentos de comparação**,
> enquanto o repo tinha 82 espalhados por 20 módulos (Sells 13 · Crm 12 · Financeiro 8 · Produto 7).
> O gate F1.5 nunca rodou aqui — é por isso que a divergência abaixo atravessou o módulo inteiro
> sem ninguém ver. O hook `block-mwart-violation` cobra **RUNBOOK**, e RUNBOOK existe (11 deles):
> o enforcement checava a metade que documenta, não a metade que **compara**.

## Âncora

Computada, não escolhida no olho:

```
node scripts/design/ancora.mjs Ponto/Dashboard/Index
  → [related_prototype (charter)] prototipo-ui/cowork/Wagner/ponto-page.jsx
    ✓ frescor: verificado contra o Cowork vivo em 2026-08-27T21:56:54.006Z
```

Portão `cowork-mirror-freshness --preview-ds`: **rc=0** (PREVIEW COMPLETO).
Selftest do `protocolo.config.mjs`: **OK**. Selftest do `design-diff.mjs`: **OK**.

## Método

Sonda **idêntica** injetada nos dois lados via `javascript_tool`, lendo `getComputedStyle` —
não screenshot, não leitura de fonte. Âncora servida em `http://localhost:5607/oimpresso.com.html`
(menu RH → Ponto); produção em `https://oimpresso.com/ponto`.

**Controle de condição:** a 1ª rodada comparou 1314px × 2560px. Antes de concluir, a âncora foi
re-medida a **2560px** e os tamanhos **não mudaram** — a escala é viewport-independente, então a
comparação é válida. `htmlFontSize: 16px` e `zoom: 1` nos dois lados.

## Veredito por dimensão

### 1. Cor / tokens de fundação — ✅ IDÊNTICO

| Aspecto | Âncora | Produção | OK? |
|---|---|---|---|
| `body` background | `oklch(0.26 0.006 240)` | `oklch(0.26 0.006 240)` | ✅ |

Os tokens de cor **chegam** em produção. A divergência desta tela não é de paleta.

### 2. Família tipográfica — ✅ IDÊNTICO

| Aspecto | Âncora | Produção | OK? |
|---|---|---|---|
| Fonte dos elementos | `IBM Plex Sans` | `IBM Plex Sans` | ✅ |
| Faces carregadas | — | 34 (Sans + Mono) | ✅ |

> ⚠️ **Erro de método registrado:** a 1ª medição leu `font-family` do `<body>` e devolveu o stack
> genérico em produção — quase virou um achado "prod não carrega IBM Plex", que é **falso**. O
> `<body>` declara o fallback; os elementos usam Plex. Medir a propriedade errada e chamar de
> verificado é §5 2026-07-16.

### 3. Escala tipográfica — ❌ DIVERGE, e é sistemático

Medido na mesma largura (2560px):

| Elemento | Âncora | Produção | Δ |
|---|---|---|---|
| Título de seção (`Fila de aprovações` / `Últimos 7 dias`) | **12,5px** / 600 | **16px** / 600 | **+28%** |
| Item de sub-nav | ~11–12px | **14px** | +17–27% |
| Rótulo auxiliar (`(2 pendentes)`) | 11px / 400 | — | — |

**Este é o achado principal.** Não é cor nem fonte: é **densidade**. A âncora é um cockpit compacto;
produção renderiza ~25–30% maior. É o que se percebe como *"nem se parece com o protótipo"* sem
conseguir apontar o quê.

### 4. Estrutura do header — ❌ DIVERGE (em 2026-08-28) → ✅ paridade desde 2026-09-29 (ver §Estado em 2026-09-29)

| Aspecto | Âncora | Produção |
|---|---|---|
| Título | `Ponto · Ponto eletrônico · Portaria MTP 671/2021` | `Dashboard · Ponto eletrônico` |
| Subtítulo | `ROTA LIVRE · matriz · Agosto/2026 · 7 colaboradores no ponto` | `28 de agosto de 2026 · atualizado 15:37` |
| Ações | **Fechamento** · **Importar AFD** · **Nova intercorrência** | **Bater ponto** |

O subtítulo da âncora carrega **tenant + competência + contagem**; produção carrega **data + hora de
refresh**. São contratos de informação diferentes, não formatação.

### 5. Sub-navegação — ❌ DIVERGE (3 abas ausentes, em 2026-08-28) → ✅ paridade desde 2026-09-29 (ver §Estado em 2026-09-29)

| Âncora (13) | Produção |
|---|---|
| Painel · Espelho de Ponto · Aprovações · Intercorrências · Banco de Horas · **Fechamento** · **Conformidade** · Escalas · Colaboradores · **REP-P (celular)** · Importações · Relatórios · Configurações | Dashboard · Espelho · Aprovações · Intercorrências · Banco de Horas · (overflow) |

- **Ausentes em produção:** `Fechamento`, `Conformidade`, `REP-P (celular)` — as três que o handoff
  do [CC] já apontava como não-construídas.
- **Nomenclatura diverge:** `Painel`→`Dashboard`, `Espelho de Ponto`→`Espelho`.

### 6. Blocos de conteúdo — ⚠️ MISTO

✅ **Fiéis** (mesma presença, mesma ordem, mesmo texto):
banner *"O que trava o fechamento de agosto"* · os **6 KPIs na mesma ordem com rótulos e
sub-rótulos idênticos** (inclusive `limite 2h/dia (Art. 59)`) · `Fila de aprovações` +
`Ver fila completa` · `Atividade recente` · rodapé `Registros protegidos pela Portaria MTP 671/2021`.

❌ **Só em produção, sem par na âncora:**
- gráfico `Últimos 7 dias — Minutos trabalhados + horas extras por dia`
- painel `O que precisa da sua atenção`

> ⚠️ **2º erro de método registrado:** por screenshot eu tinha concluído que produção **não** tinha
> `Atividade recente` nem o rodapé legal. A extração de texto provou que tem os dois. Screenshot é
> ilustração, não prova.

### 7. Estados vazios — ✅ BEM RESOLVIDOS (fora da âncora, e corretos)

A âncora só desenha o estado **populado**. Produção implementou estados vazios que a âncora não
cobre, e eles são bons e acionáveis:

- Espelho: *"Nenhum colaborador com controle de ponto ativo."*
- Colaboradores: *"Cadastre colaboradores no HRM (UltimatePOS) — eles aparecerão aqui automaticamente."*

Não é gap: é produção à frente da âncora.

### 8. Tolerância de atraso — ⚠️ dado, não estrutura

Âncora: `além da tolerância de 5 min` · Produção: `além da tolerância de 10 min`.
É configuração por tenant, não divergência de design.

## Resumo

| Dimensão | Veredito |
|---|---|
| 1 · Cor / tokens | ✅ idêntico |
| 2 · Família tipográfica | ✅ idêntico |
| 3 · **Escala tipográfica** | ❌ **diverge ~28%, sistemático** em 2026-08-28 · resolvida em parte desde o #6431 |
| 4 · Header (título/subtítulo/ações) | ❌ diverge em 2026-08-28 · ✅ paridade desde 2026-09-29 |
| 5 · Sub-nav | ❌ 3 abas ausentes + nomenclatura em 2026-08-28 · ✅ paridade desde 2026-09-29 |
| 6 · Blocos de conteúdo | ⚠️ 5 fiéis · 2 extras em prod |
| 7 · Estados vazios | ✅ prod à frente |
| 8 · Tolerância | ⚠️ dado |

## O que NÃO decidir a partir deste documento

Este doc **mede**; ele não decide. Três coisas dependem do [W]:

1. **A escala.** Encolher produção para 12,5px é decisão de produto — pode ser que a âncora esteja
   densa demais para uso real de 8h/dia. Medir não é mandar mudar.
2. **Os 2 painéis extras** (`Últimos 7 dias`, `O que precisa da sua atenção`): produção evoluiu
   além da âncora. Ou a âncora incorpora, ou eles saem. Não assumir que "extra = errado".
3. **As 3 abas ausentes** (situação de 2026-08-28; construídas depois — ver §Estado em 2026-09-29) eram capacidade não-construída, não divergência visual — vivem no backlog
   do módulo, não neste doc.

⚠️ Sem decisão do [W] em (1) e (2), **não mexer na tela**: um anti-padrão inventado aqui vira lei
para a próxima sessão.

## Estado em 2026-09-29 (re-medido; as seções acima são o retrato de 2026-08-28)

- **§3 Escala — resolvida em parte.** No mesmo dia do retrato, o #6431 ([W]: *"o correto sempre vai ser o
  protótipo"*) passou os 2 títulos de seção para o token `--fs-3` (12,5px): `Dashboard/Index.tsx:300` e
  `:319`. Seguem 12px (`text-xs`) os sub-rótulos (ex. `:301`, `:325`) contra 11px na âncora — 11px não
  tem token, e token novo é decisão [W]. O item (1) de "O que NÃO decidir" vale só para esse resto.
- **§4 Header — paridade.** O W9 ([W] 2026-09-28, ADR 0418, #8118) trocou o header pelo `PontoAreaHeader`
  (`Dashboard/Index.tsx:209`), cópia do `MP.Header` do protótipo (`ponto-page.jsx:523-529`): título,
  papel com a Portaria, linha de contexto, selo "Atualizado" e a ação "Nova intercorrência"
  (`_shared/PontoAreaHeader.tsx:91-133`). O "local" da linha de contexto não entra — recusa declarada em
  `PontoAreaHeader.tsx:27-28`.
- **§5 Sub-nav — paridade.** As 13 abas seguem ordem e rótulo do protótipo (`ponto-page.jsx:10-24`),
  listadas em `Modules/Ponto/Http/Controllers/DataController.php:177-192`, com Fechamento, Conformidade
  e REP-P (celular), cujas telas existem. Aba com `perm` some para quem não pode abrir (`:193-196`).
- Fonte dos números: [dashboard-index-gap.md](dashboard-index-gap.md), re-medido na mesma data.

## Cobertura

Este documento cobre **1 de 21** telas do Ponto. As outras 20 seguem sem comparação.
