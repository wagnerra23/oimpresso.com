---
id: requisitos-ponto-espelho-index-gap
tela: Ponto/Espelho/Index (/ponto/espelho)
prototipo: prototipo-ui/cowork/ponto-page.jsx
tela_viva: resources/js/Pages/Ponto/Espelho/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Ponto/Espelho/Index

> **Fase 1 = PARIDADE.** `ponto-page.jsx:1-4` declara o porte reverso das telas Blade do main.
> Região do protótipo: `EspelhoLista` (`ponto-page.jsx:93-158`), importada de
> `espelho/index.blade.php`. Régua de triagem (playbook `08-feriados-puxar.md` §3): só vira pedido
> o que for **comportamento** (ordenar, filtrar), nunca layout.
> ⚠️ O contrato [`ponto-espelho.contract.json`](../../../prototipo-ui/contrato/ponto-espelho.contract.json)
> lista esta tela em `alvo[]`, mas **as suas 5 seções são todas do `Show`**
> (`espelho-dados-colaborador` · `espelho-totais` · `espelho-modo-visao` · `espelho-apuracao-diaria`
> · `espelho-folha-impressao`). **Nenhuma seção do contrato descreve a lista** — logo a copy da
> lista **não é contrato travado**, e as divergências abaixo não quebram gate.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Filtro de mês de referência | **Paridade de capacidade, forma diferente.** Vivo: `Espelho/Index.tsx:80-88` (`<Input type="month">`), com o `<label htmlFor="mes">` em `:79` e partial reload `only: ['mes']` (`:50`). Protótipo: `<select>` de competências (`ponto-page.jsx:111-115`). O vivo aceita qualquer mês; o protótipo, os 2 que o mock tem. | Nada — vivo à frente (o `_pendente_w` do contrato pergunta *"quantos meses ficam navegáveis"*; o `type=month` não fecha essa porta, o `select` fecharia). |
| Busca por nome/matrícula | **Presente e DESABILITADA.** `Espelho/Index.tsx:72-76`: `<Input>` com `placeholder="Buscar por matrícula, nome ou CPF (em breve)"` (`:73`) e `disabled` (`:74`). O protótipo tem busca funcional em `ponto-page.jsx:121-122` (filtra `nome + matricula`, `:103`). O campo existe no vivo só como promessa. | **Decidir.** É **comportamento**, logo vira pedido pela régua do playbook. Duas saídas honestas: implementar o filtro (o `EspelhoController@index` já pagina — `:132-155` consome `links`/`current_page`) ou **remover o input** até implementar — um controle desabilitado com "(em breve)" é afordância falsa. Não entra de passagem: muda o `EspelhoController`, fora do escopo de 1 arquivo. |
| Filtro por escala | **Ausente.** Protótipo: `<select>` "Escala" em `ponto-page.jsx:116-120`, filtrando por `escala_atual_id` (`:102`). Varredura contada no vivo: `grep -n "escala"` em `Espelho/Index.tsx` = **0 ocorrências** (a interface `Colaborador`, `:20-26`, tem só id/matricula/cpf/nome/email). | **Decidir.** Comportamento (filtrar) → vira pedido. Exige o dado no payload: `ponto_escalas` está na lista de tabelas canônicas do `COLAR-NO-CODE-ponto-ondas.md` §5, então não é dado inventado — mas é mudança de controller, não de tela. |
| Filtro "Só com divergência" + contador da competência | **Ausente.** Protótipo: checkbox em `ponto-page.jsx:123` e o contador *"N dias em divergência na competência"* em `:126`, ambos derivados de `totaisEspelho().divergencias`. No vivo, `divergenc` = **0 ocorrências** em `Espelho/Index.tsx`. | **Decidir.** É o atalho operacional do mês (quem tem pendência), e o `Painel` já publica o mesmo número — mas quem o **calcula** é o `DashboardController.php:105` (`ApuracaoDia::where(...)->whereMonth(...)`); a tela apenas recebe (`Dashboard/Index.tsx:234`, `divergencias={kpis?.divergencias_mes ?? 0}`, prop declarada em `:36`). Custo real: agregar por colaborador no `index`, que hoje é `Inertia::defer` de uma paginação simples. Decisão de [W] porque muda a query da lista. |
| Colunas da tabela | **Diverge: 5 no vivo × 9 no protótipo.** Vivo `Espelho/Index.tsx:106-110`: Matrícula · Colaborador · CPF · E-mail · Espelho. Protótipo `ponto-page.jsx:129`: Matrícula · Colaborador · Escala · Trabalhado · HE · Saldo BH · Controla ponto · Divergências · Ação. Ausentes no vivo: **Escala · Trabalhado · HE · Saldo BH · Controla ponto · Divergências**; só no vivo: **CPF · E-mail**. | **Decidir.** As 6 ausentes são **dado agregado por competência**, não layout — é a mesma query do item anterior. O CPF do vivo é exigência legal do documento (o `Show` o imprime, `Espelho/Show.tsx:188`), então **não** sai. Decisão de [W]: a lista é índice de navegação (hoje) ou painel de competência (protótipo)? |
| Estado vazio | **Vivo à frente.** `Espelho/Index.tsx:97-100` (a frase em `:99`): *"Nenhum colaborador com controle de ponto ativo."* — citado no `Dashboard-visual-comparison.md` §7 como estado que a âncora não cobre e produção resolveu bem. Protótipo: *"Nenhum colaborador com esse filtro nesta competência."* (`ponto-page.jsx:130`), que é a frase do caso **filtrado**. | Nada — vivo à frente. As duas frases descrevem casos diferentes; a do protótipo só faz sentido quando os filtros acima existirem. |
| Paginação | **Paridade.** Vivo: `Espelho/Index.tsx:132-155`, com partial reload `only: ['colaboradores','mes']` (`:148`) e rótulo *"Página X de Y · N colaborador(es)"*. Protótipo: `Pager` em `ponto-page.jsx:153`, 15 por página (`:107`). | Nada — paridade. |
