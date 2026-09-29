---
id: requisitos-ponto-dashboard-index-gap
tela: Ponto/Dashboard/Index (/ponto)
prototipo: prototipo-ui/cowork/Wagner/ponto-page.jsx
tela_viva: resources/js/Pages/Ponto/Dashboard/Index.tsx
gerado_em: 2026-09-29
comparacao: memory/requisitos/Ponto/Dashboard-visual-comparison.md
---

# GAP-SPEC — Ponto/Dashboard/Index

> **Fase 1 = PARIDADE, não wishlist.** O cabeçalho do `ponto-page.jsx:1-4` declara o arquivo como
> *"Import das telas Blade do main (Modules/Ponto/Resources/views)"* — é **porte reverso**, então
> o protótipo é retrato do vivo, e "só no protótipo" não implica "falta no vivo".
> Régua de triagem herdada do playbook do módulo (`cowork-inbox/hrm/playbook/08-feriados-puxar.md`
> §3): *o que o protótipo tem que a Page não tem só vira pedido se for **comportamento**, nunca
> layout*. Contrato de copy: [`governance/design/contracts/ponto-painel.contract.json`](../../../governance/design/contracts/ponto-painel.contract.json).
> Medição de forma (escala, header, sub-nav) **já tem dono**: o
> [`Dashboard-visual-comparison.md`](Dashboard-visual-comparison.md) de 2026-08-28 — este gap não
> a refaz, aponta.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota "O que trava o fechamento" | **Paridade, com âncora estável.** `Dashboard/Index.tsx:130` (`NotaFechamento`) renderiza em `:136-137` com `data-contract="painel-nota-fechamento"`; o comentário `:119-121` cita a região do protótipo por **âncora de símbolo** — `§Nota contrato="painel-nota-fechamento"`, com a instrução literal *"nunca linha — re-localize com grep"* (o bloco está hoje em `ponto-page.jsx:38-44`). O título sai em `:154` e a copy `DIVERGENCIA` do contrato em `:163` e `:169` e o `<Deferred>` em `:214` cobre o estado de carga (`NotaSkeleton`, `:178`). | Nada — paridade. |
| KPIs do painel (6) | **Paridade literal.** `Dashboard/Index.tsx:223` (`KpiGrid cols={6} data-contract="painel-kpis"`) traz os 6 rótulos do contrato na mesma ordem: Colaboradores ativos `:225` · Presentes agora `:234` · Atrasos hoje `:247` · Faltas hoje `:256` · HE do mês `:265` · Aprovações pendentes `:274`. O sub-rótulo legal `limite {N}h/dia (Art. 59)` está em `:267`, interpolado de `config_clt` — igual ao `ponto-page.jsx:51`. | Nada — paridade. |
| Fila de aprovações | **Paridade.** `Dashboard/Index.tsx:316` (`Card data-contract="painel-fila-aprovacoes"`), título em `:320`; o protótipo é `ponto-page.jsx:56-73`. O comentário `:312-315` registra que a fila vem **antes** da atividade e na coluna larga — a ordem do contrato. | Nada — paridade. |
| Atividade recente | **Paridade.** `Dashboard/Index.tsx:348` (`div data-contract="painel-atividade"`) delega ao `ActivityFeed` com `title="Atividade recente"` (`:352`); protótipo em `ponto-page.jsx:75-85`. | Nada — paridade. |
| Rodapé legal | **Paridade.** `Dashboard/Index.tsx:365` registra em comentário que é o `<Legal />` do protótipo (`ponto-ui.jsx`), acionado no `ponto-page.jsx:87`. O `Dashboard-visual-comparison.md` §6 confirmou por extração de texto que o rodapé *"Registros protegidos pela Portaria MTP 671/2021"* **existe** em produção — e registra que a conclusão contrária, tirada de screenshot, era falsa. | Nada — paridade. |
| Gráfico "Últimos 7 dias" e painel "O que precisa da sua atenção" | **Existem só em produção, sem par na âncora.** O Card *"Últimos 7 dias"* está em `Dashboard/Index.tsx:298-310` e o `<AlertInbox>` em `:359-361`; o `Painel` do protótipo (`ponto-page.jsx:27-90`) tem 4 blocos — nota · KPIs · fila · atividade — e nenhum dos dois. | **Decidir.** ⚠️ Não é "vivo à frente": o [`Dashboard-visual-comparison.md`](Dashboard-visual-comparison.md) §"O que NÃO decidir a partir deste documento" lista estes 2 painéis como o **item 2** das *"três coisas [que] dependem do [W]"* — *"produção evoluiu além da âncora. **Ou a âncora incorpora, ou eles saem.** Não assumir que 'extra = errado'"*. Decisão **pendente**, não fechada. |
| Estados vazios | **Vivo à frente.** O protótipo só desenha o estado populado; produção implementou vazios acionáveis (`Dashboard-visual-comparison.md` §7). 2026-09-29: os exemplos do §7 são de outras telas (Espelho, Colaboradores). No Painel, a fila vazia existe nos dois lados (`ponto-page.jsx:59` × `Dashboard/Index.tsx:337-340`); o que só o vivo tem é o fallback "nenhuma marcação hoje" do KPI Presentes agora (`Dashboard/Index.tsx:236-240`). | Nada — vivo à frente. |
| Escala tipográfica (~28% maior em prod) | **Resolvida em parte.** `Dashboard-visual-comparison.md` §3 mediu, em 2026-08-28, título de seção 12,5px na âncora × 16px em produção. No mesmo dia o #6431 ([W]: *"o correto sempre vai ser o protótipo"*) passou os 2 títulos de seção para o token `--fs-3` (12,5px): `Dashboard/Index.tsx:300` e `:319`. Ficou de fora, declarado no #6431: os sub-rótulos `text-xs` (12px; ex. `:301` e `:325`) contra 11px no protótipo — 11px não tem token. | **Decidir** só o resto: 11px não existe na escala de token (`fs-1` = 10,5 · `fs-2` = 11,5), e token novo é decisão [W]. Os títulos de seção já estão resolvidos (#6431). |
| Header — título, subtítulo e ações | **Paridade desde o #8118 (2026-09-29).** W9 ([W] 2026-09-28, ADR 0418) trocou o header da tela pelo `PontoAreaHeader` (`Dashboard/Index.tsx:209`), cópia do `MP.Header` do protótipo (`ponto-page.jsx:523-529`): título "Ponto", papel "Ponto eletrônico · Portaria MTP 671/2021", linha de contexto, selo "Atualizado HH:MM" e a ação "Nova intercorrência" (`PontoAreaHeader.tsx:91-133`). A linha de contexto não traz o "local" ("matriz" no protótipo) — recusa declarada em `PontoAreaHeader.tsx:27-28`. Registro de 2026-09-06: diverge (âncora com 3 ações e contexto; produção com data e "Bater ponto"). | Nada — paridade; o "local" é recusa declarada no vivo. |
| Sub-navegação (13 abas × 5 + overflow) | **Paridade desde o #8118 (2026-09-29).** W9 ([W] 2026-09-28, ADR 0418): as abas seguem ordem, rótulo e ícone das 13 do protótipo (`ponto-page.jsx:10-24`), listadas em `DataController.php:177-192` — incluindo Fechamento, Conformidade e REP-P (celular), cujas telas existem. A fonte continua sendo `shell.menu` → `PontoSubNav` (`PontoAreaHeader.tsx:129`); aba com `perm` some para quem não pode abrir (`DataController.php:193-196`). Registro de 2026-09-06: 5 abas + overflow, 3 ausentes. | Nada — paridade. |
