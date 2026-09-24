---
thread: "07 · Configurações — PUXAR"
dono: "[CC]"
estado: feito (medição) · 1 decisão para [W]
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: nenhum arquivo de build · 00-INDICE.md (decisão RESIDUO-6 acrescentada)
---
# _saida-07 · Configurações — protótipo × produção

Lido no turno: `resources/js/Pages/Essentials/Settings/Index.tsx` (8.866 B). Protótipo: `hrm-extras.jsx` `Config()` + `hrm-data.jsx` `CFG`.

## Premissa corrigida
A thread dizia **12 campos** no protótipo. Medido hoje: **10** — as mesmas 10 chaves da Page viva, 1:1. A 11ª ("permitir registrar a própria presença") **já não é campo**: é uma nota que aponta a permissão `allow_users_for_attendance_from_web`. Classe (i) já estava resolvida no build.

## As 10 chaves
| chave | protótipo (rótulo) | produção (rótulo) | classe | ação |
|---|---|---|---|---|
| `leave_ref_no_prefix` | Prefixo do número de referência | Prefixo de afastamentos | — | nada |
| `leave_instructions` | Instruções ao colaborador | Instruções para afastamentos (card) | — | nada |
| `payroll_ref_no_prefix` | Prefixo da folha | Prefixo de folha | — | nada |
| `essentials_todos_prefix` | Prefixo das tarefas | Prefixo de tarefas | — | nada |
| `grace_before_checkin` | Antes da entrada | Antes de entrar | **(ii) presença** | decisão [W] |
| `grace_after_checkin` | Depois da entrada | Depois de entrar | **(ii)** | decisão [W] |
| `grace_before_checkout` | Antes da saída | Antes de sair | **(ii)** | decisão [W] |
| `grace_after_checkout` | Depois da saída | Depois de sair | **(ii)** | decisão [W] |
| `is_location_required` | Exigir localização na marcação | Exigir localidade no ponto | **(ii)** — e **divergência de significado** ↓ | decisão [W] |
| `calculate_sales_target_commission_without_tax` | Apurar comissão de meta sem imposto | Meta de vendas sem impostos | — | nada |

Classe (iii) (chave real que a Page não expõe): **0**. Nenhum gap para o [CL].

## Divergência de significado (não é só rótulo)
`is_location_required` — protótipo: *"Sem permissão de local no navegador, a marcação é recusada"* (GPS). Produção: *"Colaborador deve selecionar **loja** ao bater ponto"* (business_location). São regras diferentes. Não mudei o protótipo: sem ler o `EssentialsAttendanceController`, não sei qual das duas o backend aplica. Fica anexada à RESIDUO-6.

## Decisão pedida — `RESIDUO-6` (acrescentada ao json do índice)
As 5 chaves de presença continuam em `/hrm/settings` ou migram para o Ponto (dono da jornada desde **D1**, 2026-09-05)? Sem resposta, nada se move — nem aqui, nem no `main`.
> Colisão de nome evitada: a thread chamava isto de "RESÍDUO 5", mas `RESIDUO-5` no índice já é a pergunta de Metas (trava a 04).

## Protótipo × produção que fica como está (layout/copy)
- Nota "Esta tela já é Inertia no main", botão **Descartar alterações** e aviso "alterações não salvas" — só protótipo, afordância de UX; não vira pedido.
- Produção agrupa em 4 cards (Prefixos · Afastamentos · Tolerâncias · Comportamentos); protótipo em 4 (Licenças · Folha e tarefas · Tolerância · Regras). Mesmo conteúdo.
