---
sessao: "01"
titulo: Build — TABS do HRM (−Presença · +Departamentos/Cargos)
dono: "[CC]"
base: 159e572dd448
prefixo: hrm-page.jsx · hrm-data.jsx · oimpresso.com.html (só bump ?v=) — deste projeto Cowork; espelho em prototipo-ui/cowork/
nao_toca: hrm-extras.jsx (Presenca fica no arquivo até a 09 fechar) · hrm-forms.jsx · DS · app.jsx (rota hrm-presenca continua existindo e redireciona)
depende: RESÍDUO 2 (Departamentos/Cargos) — a parte −Presença não depende de nada (D1 respondida)
---
# 01 · Build: TABS do HRM

## Por quê
[W] D1 (2026-09-05): a presença web **cede lugar ao Ponto**. O protótipo ainda mostra a aba Presença como se fosse do HRM — exportar isso é exportar decisão revogada. E o `nav_hrm.blade.php` de produção tem **Departamentos** e **Cargos** (`TaxonomyController?type=hrm_department|hrm_designation`) que o protótipo não tem.

## Alvo (o que muda, medido antes/depois)
- `TABS` em `hrm-page.jsx`: hoje 8 (`hrm · hrm-licencas · hrm-presenca · hrm-turnos · hrm-folha · hrm-feriados · hrm-metas · hrm-config`).
- Depois: `hrm-presenca` **sai** das abas; a rota continua e renderiza um `EmptyState variant="done"` com "Presença agora é do Ponto" + ação "Abrir Ponto" (`window.__go('ponto')`). Não apagar `Presenca` de `hrm-extras.jsx` nesta thread.
- `hrm-departamentos` e `hrm-cargos` **entram** (só após RESÍDUO 2): lista PT-01 simples (nome · descrição · colaboradores · ações), dado em `hrm-data.jsx` (`H.DEPTOS`, `H.CARGOS`), permissão `A.pode("crud_department")`/`("crud_designation")`.
- Painel: os cards que leem `s.dados.pre` (presença) passam a apontar pro Ponto (texto + `__go('ponto')`), sem número inventado.

## Não inventar
- Átomos do módulo (`hrm-ui.jsx`: `Tabela` com `scope="col"`, `Busca` com `aria-hidden` no svg) · `TabBar` do DS (já dá `aria-selected` 8/8).
- Copy PT-BR sentence case: "Departamentos", "Cargos", "Presença agora é do Ponto".
- Zero cor crua; sem `.html` novo; variação = Tweak.

## Passo a passo
1. `gh pr list --state open` × `hrm-page.jsx` (colisão de 04/09). Medir T1 no build servido (dark, `__oiLazyDone`, 2 leituras iguais de `querySelectorAll('*').length` — o módulo tem skeleton, 1ª leitura mente ~23%).
2. Editar `TABS`; adicionar dados; adicionar 2 views; trocar cards de presença do Painel.
3. Rodar A1–A12 nas 2 abas novas (A3 svg · A5 aria-selected · th scope).
4. Bump `?v=` no host; T1 de novo; contagem de abas = 7 (sem RESÍDUO 2) ou 9 (com).
5. `_saida-01.md`.

## PARAR SE
- RESÍDUO 2 não respondido → executar só a parte −Presença (7 abas) e registrar.
- Algum card do Painel precisar de número do Ponto → `—` + linha no `_saida` (dado do Ponto não é deste módulo).

## Prova (PLACAR confere)
- `hrm-page.jsx` no espelho `prototipo-ui/cowork/` sem `hrm-presenca` em `TABS` · `NAV.ds-tabbar` com 7 ou 9 `BUTTON` `aria-selected`
- `_saida-01.md` nesta pasta
- Não verificável daqui: o pacote (`gerar-payload-partes.mjs`) — aviso o comando, não afirmo que regenerei
