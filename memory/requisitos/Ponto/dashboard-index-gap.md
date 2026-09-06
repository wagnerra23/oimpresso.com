---
id: requisitos-ponto-dashboard-index-gap
tela: Ponto/Dashboard/Index (/ponto)
prototipo: prototipo-ui/cowork/ponto-page.jsx
tela_viva: resources/js/Pages/Ponto/Dashboard/Index.tsx
gerado_em: 2026-09-06
comparacao: memory/requisitos/Ponto/Dashboard-visual-comparison.md
---

# GAP-SPEC — Ponto/Dashboard/Index

> **Fase 1 = PARIDADE, não wishlist.** O cabeçalho do `ponto-page.jsx:1-4` declara o arquivo como
> *"Import das telas Blade do main (Modules/Ponto/Resources/views)"* — é **porte reverso**, então
> o protótipo é retrato do vivo, e "só no protótipo" não implica "falta no vivo".
> Régua de triagem herdada do playbook do módulo (`cowork-inbox/hrm/playbook/08-feriados-puxar.md`
> §3): *o que o protótipo tem que a Page não tem só vira pedido se for **comportamento**, nunca
> layout*. Contrato de copy: [`prototipo-ui/contrato/ponto-painel.contract.json`](../../../prototipo-ui/contrato/ponto-painel.contract.json).
> Medição de forma (escala, header, sub-nav) **já tem dono**: o
> [`Dashboard-visual-comparison.md`](Dashboard-visual-comparison.md) de 2026-08-28 — este gap não
> a refaz, aponta.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota "O que trava o fechamento" | **Paridade, com âncora estável.** `Dashboard/Index.tsx:131` (`NotaFechamento`) renderiza em `:138` com `data-contract="painel-nota-fechamento"`; o comentário `:120` cita a região do protótipo (`ponto-page.jsx:37`, `Nota contrato="painel-nota-fechamento"`). A copy `DIVERGENCIA` do contrato está em `:155` e o `<Deferred>` em `:231` cobre o estado de carga (`NotaSkeleton`, `:179`). | Nada — paridade. |
| KPIs do painel (6) | **Paridade literal.** `Dashboard/Index.tsx:240` (`KpiGrid cols={6} data-contract="painel-kpis"`) traz os 6 rótulos do contrato na mesma ordem: Colaboradores ativos `:242` · Presentes agora `:251` · Atrasos hoje `:264` · Faltas hoje `:273` · HE do mês `:282` · Aprovações pendentes `:291`. O sub-rótulo legal `limite {N}h/dia (Art. 59)` está em `:284`, interpolado de `config_clt` — igual ao `ponto-page.jsx:50`. | Nada — paridade. |
| Fila de aprovações | **Paridade.** `Dashboard/Index.tsx:333` (`Card data-contract="painel-fila-aprovacoes"`), título em `:337`; o protótipo é `ponto-page.jsx:57-77`. O comentário `:329` registra que a fila vem **antes** da atividade e na coluna larga — a ordem do contrato. | Nada — paridade. |
| Atividade recente | **Paridade.** `Dashboard/Index.tsx:365` (`div data-contract="painel-atividade"`) delega ao `ActivityFeed` com `title="Atividade recente"` (`:369`); protótipo em `ponto-page.jsx:79-88`. | Nada — paridade. |
| Rodapé legal | **Paridade.** `Dashboard/Index.tsx:382` registra em comentário que é o `<Legal />` do protótipo (`ponto-ui.jsx`), acionado no `ponto-page.jsx:89`. O `Dashboard-visual-comparison.md` §6 confirmou por extração de texto que o rodapé *"Registros protegidos pela Portaria MTP 671/2021"* **existe** em produção — e registra que a conclusão contrária, tirada de screenshot, era falsa. | Nada — paridade. |
| Gráfico "Últimos 7 dias" e painel "O que precisa da sua atenção" | **Vivo à frente.** Os dois blocos existem em produção e **não têm par** no `ponto-page.jsx` (`Painel`, `:27-90`, tem 4 blocos: nota · KPIs · fila · atividade). Registrado no `Dashboard-visual-comparison.md` §6 como *"só em produção, sem par na âncora"*. | Nada — vivo à frente. Registrado para não virar "bug" na próxima leitura. |
| Estados vazios | **Vivo à frente.** O protótipo só desenha o estado populado; produção implementou vazios acionáveis (`Dashboard-visual-comparison.md` §7). | Nada — vivo à frente. |
| Escala tipográfica (~28% maior em prod) | **Diverge, medido, e a decisão já está aberta.** `Dashboard-visual-comparison.md` §3: título de seção 12,5px na âncora × 16px em produção, na mesma largura (2560px), com controle de condição declarado. É o achado principal daquele documento e ele mesmo classifica como decisão de produto — *"encolher produção para 12,5px é decisão de produto… medir não é mandar mudar"*. | **Decidir.** Já aberta em `Dashboard-visual-comparison.md` §"O que NÃO decidir a partir deste documento" item 1. Este gap **não reabre**: aponta. Enquanto não houver decisão de [W], o próprio doc proíbe mexer na tela. |
| Header — título, subtítulo e ações | **Diverge.** `Dashboard-visual-comparison.md` §4: a âncora carrega `tenant · competência · contagem` no subtítulo e três ações (Fechamento · Importar AFD · Nova intercorrência); produção carrega `data · hora de refresh` e uma ação (Bater ponto). Duas das três ações da âncora dependem de telas que **não existem** (`Fechamento`) ou de fluxo próprio. | **Decidir.** Contrato de informação, não formatação — e a ação `Fechamento` pertence à frente 5 do `COLAR-NO-CODE-ponto-ondas.md`, **travada** nas decisões [W] 1–4 (estado da competência · permissão · exceções assinadas · reabertura). Sem elas, construir o header é inventar lei. |
| Sub-navegação (13 abas × 5 + overflow) | **Divergência declarada — a produção é o dono.** `PontoSubNav` deriva `primary`/`ghosts` do `shell.menu`; o `COLAR-NO-CODE-ponto-ondas.md` §0 lei 5 é literal: *"Autoridade de navegação = `shell.menu` → `PontoSubNav` → `PageHeaderTabs` (produção), **não** a minha TabBar"*. As 3 abas ausentes (Fechamento · Conformidade · REP-P) são capacidade não-construída, não divergência visual. | Nada — divergência declarada, dono é a produção. As 3 telas ausentes vivem nas frentes 5–7, travadas em [W]. |
