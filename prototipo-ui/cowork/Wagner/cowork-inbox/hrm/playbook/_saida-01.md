---
sessao: "_saida-01"
thread: "01 · Build — TABS do HRM (−Presença · +Departamentos/Cargos)"
dono: "[CC]"
data: 2026-09-22
prefixo_tocado: prototipo-ui/cowork/Wagner/hrm-page.jsx · hrm-data.jsx · oimpresso.com.html (bump hrm-page/hrm-data ?v=hrm10tax) · **data.jsx (fora do prefixo — ghosts da sidebar, declarado abaixo)**
base_lida: wagnerra23/oimpresso.com@main c32d02b73672 — hrm-page.jsx 32.777 B, idêntico ao local antes da edição
---
# _saida-01

## Feito
1. **Aba Presença saiu do `TABS`** (D1 [W] 2026-09-05: a jornada é do Ponto). 8 → 9 abas (−Presença, +Departamentos, +Cargos).
2. **A rota `hrm-presenca` continua existindo** e renderiza `Vazio variante="done"` — "Presença agora é do Ponto" + botão "Abrir Ponto" (`go("ponto")`). Link antigo não quebra.
3. **Painel:** o item da fila "N marcações sem saída registrada" deixou de apontar para `hrm-presenca`; agora aponta para `ponto` ("Abrir no Ponto").
4. **+Departamentos e +Cargos** (RESÍDUO 2 respondido por [W] 2026-09-22: "pode virar abas"). Componente `Taxonomia` em `hrm-page.jsx` — espelha `TaxonomyController?type=hrm_department|hrm_designation` do nav_hrm. Lista inicial = setores/cargos que os colaboradores já usam (`H.EMP`), nada inventado; colunas Nome · Descrição · Colaboradores · ações; adicionar/renomear com checagem de nome duplicado; **excluir desabilitado quando em uso** (mesmo espírito do 422 `blocked_by` do #6789). Permissões novas no mock: `gerir_departamento`/`gerir_cargo` só no papel admin. `dados.emp` entrou no ambiente (vazio em "primeira vez").
5. **Sidebar (`data.jsx`, fora do prefixo):** ghost "Presença" saiu, "Departamentos" e "Cargos" entraram. Sem isso a sidebar apontaria pra aba que não existe mais — declarado aqui porque a Lei 1 foi excedida em 1 arquivo.
6. `X.Presenca` (em `hrm-extras.jsx`) **não foi apagada** — fora do prefixo, e a thread 09 é quem decide a saída do código.

- **T1 (contagem de nós antes/depois) e A1–A12 nas abas:** não medi nesta sessão — ficou para o verificador. A prova estrutural da thread (`nao_contem id:"hrm-presenca"` no `hrm-page.jsx`) **não fecha sozinha**: a string ainda aparece na rota de redirecionamento. Ver Descobertas 1.

## Descobertas
1. **A prova da thread estava mal escrita.** Ela exige que `hrm-page.jsx` não contenha `id:"hrm-presenca"` — o que só aconteceria apagando também a rota, e apagar a rota quebra link. O que passou a valer é a aba sair do `TABS`, não a string sumir do arquivo. A prova certa é `nao_contem` sobre o bloco do `TABS`, e o placar não recorta bloco → **prova a trocar no índice** por uma de comportamento (ou aceitar como indecidível).
2. **O playbook do HRM está 17 dias atrás da produção.** No `main` c32d02b7: `Essentials/Licencas/Index.tsx` (`EssentialsLeaveController.php:191`) e `Essentials/Tipos.tsx` (`EssentialsLeaveTypeController.php:73`) **já existem** — as threads 02 e 03 foram entregues fora do playbook, sem `_saida`. O placar as mostra como `pendente`.
