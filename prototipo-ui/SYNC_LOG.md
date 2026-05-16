# SYNC_LOG.md — timeline append-only do loop

> Cada linha = 1 evento. **Imutável após escrito.**
> Formato: `YYYY-MM-DD HH:MM [SIGLA] <evento>`

---

```
2026-05-09 14:00 [CL] criou prototipo-ui/ com 13 arquivos + ADR 0114 + skill V4
2026-05-09 14:00 [CL] leu CLAUDE_CODE_BRIEFING.md, auto-check OK (3/3)
2026-05-09 14:00 [W]  abriu COWORK_NOTES.md com 3 perguntas + 5 extras pra [CD]
```

---

## Eventos a registrar

| Evento | Sigla | Linha esperada |
|---|---|---|
| Wagner adiciona pedido | [W] | `[W] add request: <tela> P<N> → COWORK_NOTES.md` |
| Cowork exporta protótipo | [CC] | `[CC] export prototipos/<tela>/ (zip de N arquivos)` |
| Claude Design roda critique | [CD] | `[CD] critique <tela> score=NN benchmark=<ref>` |
| Wagner aprova screenshot | [W2] | `[W2] approved screenshot <tela>` |
| Claude Code abre PR de F3 | [CL] | `[CL] PR #NNN draft <tela> (mwart-from-cowork)` |
| Claude Accessibility roda a11y | [CA] | `[CA] a11y <tela> WCAG-AA pass=YES/NO critical=N` |
| Wagner mergeia PR | [W2] | `[W2] merged PR #NNN <tela>` |
| Override registrado | [W] | `[W] /design-override <tela> reason="..."` |
| Loop quebrado (>7d em fase) | [CL] | `[CL] ALERT design_loop_stuck <tela> stuck_in=F3 days=N` |
2026-05-09 16:30 [CL] gerou RUNBOOK-manifestacao.md (cockpit-runbook skill, 12 seções)
2026-05-09 16:35 [CL] gerou manifestacao-visual-comparison.md (mwart-comparative V4, 15 dimensões)
2026-05-09 16:40 [W]  approved manifestacao-visual-comparison (greenfield via canon Cockpit, sem screenshot externo)
2026-05-09 16:50 [CL] criou Pages/NfeBrasil/Manifestacao/Index.tsx + 3 LinkedApps + ManifestacaoController US-NFE-052
2026-05-09 19:00 [CL] cowork-inbox pipeline E2E live — drop em cowork-inbox/ → header parsed → arquivo movido pra path whitelist → PR auto-mergeado em ~24s (PRs #321 → #325)
2026-05-09 19:08 [CL] dropped prototipos/producao-oficina/F1.html via inbox (PR #326 → #327, run 25609417046, 24s, kanban 5 colunas DNA Cockpit V2 conservador)
2026-05-09 19:30 [W2] approved producao-oficina F1 — pronto pra F3 quando Wagner pedir
2026-05-09 21:15 [CL] dropped prototipos/financeiro-{unificado,fluxo,conciliacao,dre,plano-contas}/page.tsx — 5 batch F1 pinos (WAITING_FOR_BACKEND)
2026-05-09 21:15 [CL] análise pré-merge rejeitou batch original do PROMPT_PARA_CLAUDE_CODE — controllers Cowork eram NO-OP sem tenant scope (Tier 0 violation, ADR 0093) + UnificadoController regrediria fixes #355/#358 em prod
2026-05-09 21:15 [CL] sequência sugerida pra F3: fluxo (sem tabela nova) → plano-contas (fundação) → dre (consome plano) — conciliacao escopo separado (exige bank_statement_lines + ADR arq/0006)
2026-05-09 23:55 [CL] dropped prototipos/produto-unificado/ — 4 pinos F1 visuais (.jsx + .html) extraídos do PR #352 bloqueado (anti-padrões T-AP-9, M-AP-1, M-AP-3, M-AP-4 catalogados em LICOES_F3_FINANCEIRO_REJEITADO.md)
2026-05-09 23:55 [CL] PR #352 mantido aberto com comentário de bloqueio — código de produção (controller + routes + Page.tsx) bloqueado, só material visual aproveitado nesse PR menor
2026-05-11 21:30 [W]  declarou "Oimpresso ERP Conunicação Visual. novo.zip" como canon visual único — autorizou substituição completa dos prototipos/financeiro-*/
2026-05-11 21:30 [CL] extraiu ZIP (177 arquivos, 2.9 MB) pra prototipo-ui/_zip-novo-2026-05-11/ (área isolada)
2026-05-11 21:30 [CL] substituiu 5 page.tsx em prototipos/financeiro-{unificado,conciliacao,dre,fluxo,plano-contas}/ com Index.tsx do prototipo-ui-patch/ do ZIP (canon visual novo)
2026-05-11 21:30 [CL] adicionou 5 arquivos visuais em financeiro-unificado/ (visual-source.html + 4 cowork-*.jsx standalone) como referência completa
2026-05-11 21:30 [CL] ALERTA — Design System do ZIP (uploads/Design System/resources/js/) é fotografia ANTIGA: AppShellV2 −5.4KB, Sidebar −16.3KB, AppShell removido em prod, StatusBadge −1.8KB. NÃO copiado pra resources/js/ — fica em _zip-novo-2026-05-11/ como referência. Sobrescrever destruiria features mergeadas em prod.
2026-05-11 21:30 [CL] prototipo-ui-patch/Modules/Financeiro/Http/Controllers/*.php (5 controllers) mantidos APENAS em _zip-novo-2026-05-11/ — não copiados pra Modules/Financeiro/ (Tier 0 IRREVOGÁVEL, ADR 0093, mesmo bloqueio de 2026-05-09)
2026-05-11 21:35 [W]  decidiu: sidebar = o que está em prod (resources/js/Components/cockpit/Sidebar.tsx 30KB) é canon, Design System do ZIP descartado
2026-05-11 21:35 [W]  pediu remoção de material antigo pra evitar confusão
2026-05-11 21:35 [CL] removeu _zip-novo-2026-05-11/ inteiro (177 arquivos) — todo material relevante pro Financeiro já copiado pra prototipos/financeiro-*/ (canon visual novo consolidado)
2026-05-11 21:45 [W]  apontou que ZIP tinha material de TODOS os módulos (não só Financeiro) — pediu cópia completa mantendo identidade visual consistente
2026-05-11 21:45 [CL] re-extraiu ZIP e copiou HTMLs/JSX por módulo: criou 5 dirs novos (compras, os, clientes, orcamento, chat) + populou produto, produto-cockpit, produto-unificado, sells-create, vendas-cockpit, inventario-migracao, producao-oficina com visual-source.html + cowork-*.jsx
2026-05-11 21:45 [CL] decisão arquitetural: identidade visual canônica = shared components em prod (KpiCard, PageHeader, StatusBadge, AppShellV2). HTMLs/JSX do ZIP = mockups por tela (referência F1). Refator em prod aplica shared components garantindo consistência automática.
2026-05-11 21:45 [CL] removeu _zip-novo-2026-05-11/ definitivamente — todo material consolidado em prototipos/
2026-05-14 06:30 [W]  extraiu Oimpresso-handoff.zip (export Claude Design) e pediu auditoria do template chat da Jana
2026-05-14 06:30 [CL] confirmou bundle completo (40 imports presentes, 8 CSS + 32 JS/JSX, sem deps externas faltando)
2026-05-14 06:35 [CL] revisão crítica chat.jsx — descobriu que template é WhatsApp-style multi-purpose, NÃO chat IA da Jana. Modules/Whatsapp/ (omnichannel ADR 0096+0135) é o canal correto pra atendimento humano
2026-05-14 06:40 [CL] nota inicial 42/100 (considerando ambíguo entre IA e atendimento)
2026-05-14 06:45 [W]  esclareceu: chat = só Jana, atendimento = Modules/Whatsapp omnichannel
2026-05-14 06:50 [CL] reavaliou: nota revisada 24/100 vs Glean Chat / ChatGPT Enterprise / Notion AI / Microsoft Copilot M365 (2026) — 0/6 P0 charter fechados, vocabulário humano vazado, 4 kinds IA ausentes
2026-05-14 06:55 [CL] escreveu COWORK_NOTES.amendment-jana-chat-block-renderer.md — 19 divergências catalogadas (5 anti-patterns charter + 7 vocabulário humano + 7 features IA ausentes) + correção formal item-por-item + critério F1.5 ≥80
2026-05-14 06:55 [CL] appendou resumo executivo do amendment em COWORK_NOTES.md (principal) + atualizou HANDOFF.md sinalizando F0.5 amendment P0 bloqueia F3 Jana
2026-05-14 06:55 [CL] aguarda decisão Wagner: opção A (atacar Jana V2 primeiro — [CC] gera V2 com 4 kinds tipados + streaming + citations) vs opção B (atacar Financeiro/Fluxo primeiro)
2026-05-15 21:10 [CC] export Cowork novo via WebFetch design URL (`Oimpresso ERP - Chat.html` aberto no handoff) — tarball 6.9 MB · 605 arquivos
2026-05-15 21:10 [CL] sync snapshot-only pra `_cowork-export-2026-05-15/` — 161 arquivos · 3.3 MB · excluiu uploads/backups/scraps (trap stale conhecido SYNC_LOG 2026-05-11)
2026-05-15 21:10 [CL] highlight no _SNAPSHOT.md: `chat-jana.jsx` (491 lin) + `chat-jana.css` (645 lin) presentes — provável V2 atendendo amendment-block-renderer; aguarda Wagner confirmar comparação contra 19 divergências P0
2026-05-15 21:10 [CL] AVISO no _SNAPSHOT.md: `prototipo-ui-patch/{Modules,Pages,resources,routes,app}` mantido isolado — NÃO aplicar direto (Tier 0 ADR 0093, mesmo bloqueio de 2026-05-09 PR #352 e 2026-05-11 zip canon)
