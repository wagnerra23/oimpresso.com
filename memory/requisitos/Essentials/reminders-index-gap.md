---
id: requisitos-essentials-reminders-index-gap
tela: Essentials/Reminders/Index (/essentials/reminder)
prototipo: prototipo-ui/cowork/essenciais-page.jsx
tela_viva: resources/js/Pages/Essentials/Reminders/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Reminders/Index

> **Fase 1 = PARIDADE.** `essenciais-page.jsx:1-3` declara o porte reverso do blade
> (`reminder/{index,create,show}.blade.php`). Região: `Lembretes` (`:444-537`).
> Contrato: [`lembretes.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/lembretes.contract.json)
> (5 seções: `toolbar-mes` · `grade-mes` · `legenda` · `drawer` · `form`).
> Charter: [`Lembretes.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/Lembretes.charter.md) —
> objetivo declarado: *"um calendário que mostra junto o que a equipe marcou e o que os outros
> módulos cobram"*.
> ⚠️ Esta é a tela com a **maior divergência estrutural** das 11 medidas: o protótipo é calendário,
> o vivo é lista.

> ⚠️ **O contrato citado ainda NÃO é gate ativo.** Ele vive em
> `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`
> (medido com o critério do próprio gate — `git ls-files "prototipo-ui/contrato/*.contract.json"` sem o EXEMPLO, como `scripts/contrato-de-tela.mjs` faz: **28** contratos ativos, que incluem `essentials-tipos`, `essentials-licencas` e
> `essentials-metas` — nenhum dos 5 dos essenciais). Ele é **proposta de contrato**: descreve a
> copy literal pretendida e serve de âncora para esta comparação, mas **não trava merge hoje**.
> Por isso as divergências de copy abaixo saem como `Decidir.`, nunca como "quebra de gate".

| Parte | Estado no vivo | Ação |
|---|---|---|
| Forma da tela: grade do mês × lista | **Diverge por inteiro.** Protótipo: grade de calendário `ess-cal-g` com `role="grid"` e `aria-label` do mês (`:499`), células por dia e eventos dentro do dia (`:509`). Vivo: lista simples — `Reminders/Index.tsx:155` (`reminders.map(...)`) dentro de um `<Card>` (`:146-147`). Varredura contada no vivo (`rg -c`): `calend` = **0** · `cal-g` = **0** · `role="grid"` = **0**. (`grid` = 1 e `dia` = 4, mas nenhum é calendário: o `grid` é `grid-cols-1 md:grid-cols-3` do form em `:207`, e os 4 `dia` são `Dialog`/`dialogOpen`.) Não há estrutura de calendário. | **Decidir.** É a **forma** da tela, e o eixo FORMA tem cadeia própria — protótipo soberano sobre charter/ADR-UI ([UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md), citada em [proibicoes.md](../../proibicoes.md) §Precedência). ⚠️ E a lista do vivo **tem lastro escrito**: o `Reminders/Index.charter.md` traz o Non-Goal literal (`:40`) *"❌ NÃO renderiza calendário full-month grid (**decisão UX: listagem é mais prática diário**)"*, e o §Objetivo (`:22`) declara que a tela *"substitui o calendário FullCalendar legado por listagem ordenada cronologicamente — padrão consistente com outras telas migradas (Todo, Holidays)"*. Ou seja: são **duas decisões de forma opostas e ambas escritas** — o charter defende a lista, o protótipo desenha o calendário. Pelo eixo FORMA ([UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)) o protótipo é soberano sobre o charter, mas aqui o charter não é omissão: é decisão de UX registrada, com motivo. Decisão de [W] sobre qual vale; o perdedor se corrige no MESMO PR. |
| Navegação de mês | **Ausente.** Protótipo: `‹ mês anterior · {mês} de {ano} · ›` (`:487`), com o mês em estado (`:447`). No vivo não há navegação temporal — a lista é única. | **Decidir.** Depende inteiramente da linha acima: sem grade, não há mês para navegar. |
| Filtro por origem | **Ausente.** Protótipo: `fOrig` (`:449`) filtrando `l.origem` (`:459`). O charter explica o porquê: o calendário mostra junto o que **outros módulos cobram** (vencimento do Financeiro, fechamento do Ponto). Vivo: os termos `origem` e `origin` em `Reminders/Index.tsx` = **0 de cada**. | **Decidir.** É comportamento **e** modelo: exige que lembrete carregue procedência (`manual` × `financeiro` × `ponto`), o que o blade não tem. O charter lista *"canal de notificação"* entre os itens que aguardam [W]; a procedência é vizinha e não está decidida. |
| Legenda de origem | **Ausente.** Protótipo `:515-516`: legenda com as 3 origens do contrato (Lembrete · Financeiro · Ponto). | **Decidir.** Consequência direta do filtro acima — legenda sem origem no dado é decoração. |
| Dica de teclado + navegação por setas | **Ausente.** Protótipo: `:517` grafa a dica literal do contrato (*"Setas percorrem os dias · Enter abre o primeiro lembrete do dia"*) e `:467-480` implementa `ArrowLeft/Right/Up/Down` movendo o foco por `data-dia`. | **Decidir.** É acessibilidade de grid — só existe se a grade existir. Registrado aqui para não se perder quando a decisão da 1ª linha for tomada. |
| Criar / editar / excluir lembrete | **Paridade de capacidade.** Vivo: `<Dialog>` (`:77`, `:85`, `:97`) com toasts *"Lembrete criado/atualizado"* (`:107`) e *"Lembrete removido"* (`:119`); botões de editar (`:173`) e o `AlertDialog` de exclusão (`:14-21`). Protótipo: `FormLembrete` (`:538-564`), aberto pelo botão "Adicionar lembrete" (`:495`). | Nada — paridade. |
| Copy do botão de criação | **Diverge.** Contrato: `botao_novo` = "Adicionar lembrete" (protótipo `:495`). Vivo `Reminders/Index.tsx:142`: grafa **"Novo lembrete"** — medido, não suposto. | **Decidir.** Copy de contrato é soberania de [W]. |
| Drawer de detalhe com "Abrir no módulo" | **Ausente.** Protótipo `:521-526`: o drawer distingue lembrete **manual** (pode excluir) de **origem externa** (só leitura, com botão *"Abrir no módulo"* que salta para Financeiro ou Ponto). O contrato lista `origem-externa (só leitura)` como estado. | **Decidir.** Mesma raiz do filtro de origem: sem procedência no modelo, não há externo para abrir. |
