---
sessao: "decisoes-w"
titulo: "Officeimpresso — Logs é lista de máquinas e a flag liga; 09 fecha por prova mecânica"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 6511ed7c34
---
# _DECISOES-W-2026-10-14 — Officeimpresso (pedido ao Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` e da vista `oi-log` DO COWORK. O Code não edita o
> espelho (§5 2026-09-24). Vale quando o Cowork aplicar e o próximo retorno descer.
> Este arquivo não é recibo: o placar lê só `_saida-<id>.md`.

## O que o placar mostra hoje

`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/officeimpresso/playbook/00-INDICE.md`
em `origin/main` 6511ed7c34:

```
Officeimpresso: entregue 10 de 14 · próximo 0 · em curso 1 · pendente 0 · bloqueada 3
  03 [bloqueada] Dropar colunas senha/contra_senha
  A3 [bloqueada] Remedir Logs Index e Timeline, um design.json por tela
  07 [bloqueada] Logs: fechar o design-diff (depois de remedir)
  09 [em curso ] (indecidível) Ligar a tela nova de licenças (flag useV2OfficeimpressoLicencas)
```

## A decisão de [W] (2026-10-14, no chat)

Pergunta do `_saida-A3`: *"Logs/Index é lista de máquinas (como hoje) ou log de eventos (como o
protótipo)? E posso ligar `useV2OfficeimpressoLogs`?"*

- **Conteúdo: máquinas.** A tela de produção vence no DADO. A UI-0029 manda na forma, não no que a
  tela lista.
- **Flag: ligar para todas as empresas.** Entra no `fallbackDefaults` com `true` no PR #9412.
  Flag OFF no GrowthBook continua vencendo (rota de fuga, UC-LOGS-16).

## O que o Cowork precisa fazer

**1. Redesenhar a vista `oi-log` como lista de MÁQUINAS.** Hoje ela lista eventos do `licenca_log`
(Quando · Evento · Origem · Rota · Latência). A tela viva (`Logs/Index.tsx`, `MaquinasTable`) mostra
máquinas, com KPIs "Máquinas cadastradas", "Máquinas bloqueadas" etc. Enquanto a vista não mostrar
o mesmo dado, o design-diff mede identidade, não defeito (`_saida-A3` §4: `DIVERGE · 5 bugs`, nenhum
acionável). O payload da tela está em `LicencaLogController::buildMaquinasPayload` e
`buildKpisPayload`; o contrato de dado está em `Logs/Index.casos.md` (UC-LOGS-02 a 13).

**2. Decisão nova no índice:**

```json
{
  "id": "D7",
  "pergunta": "Logs/Index lista máquinas (produção) ou eventos (protótipo)? E pode ligar useV2OfficeimpressoLogs?",
  "respondida": true,
  "destrava": ["A3", "07"],
  "resposta": "máquinas (produção vence no dado); flag ligada para todas as empresas no fallbackDefaults (#9412)",
  "fonte": "_DECISOES-W-2026-10-14.md"
}
```

**3. Thread A3** — trocar o `bloqueio`. As causas do `_saida-A3` ficam assim:
- (1) flag desligada: resolvida pela D7 (#9412).
- (2) CSS vazio da Blade: deixa de valer, a rota serve a tela React.
- (3) staging sem máquinas: segue. A medida precisa de máquinas na base do staging.
- (4) telas diferentes: resolvida quando o item 1 acima descer.
- Timeline: a A4 já deu vista própria (`oi-log-timeline`).

Sugestão de `bloqueio` até o item 1 descer: `"espera o Cowork redesenhar a vista oi-log como lista
de máquinas (D7, _DECISOES-W-2026-10-14)"`.

**4. Thread 09** — está entregue, e a prova `execucao` não é medível pelo placar. Medido no `main`:
a flag `useV2OfficeimpressoLicencas` saiu do código em 2026-10-06 (#8783, cutover sem flag nem Blade)
e o `index.blade.php` da lista não existe mais. Proposta de provas mecânicas:

```json
"provas": [
  { "tipo": "ausente", "path": "${MOD}/Resources/views/licenca_computador/index.blade.php" },
  { "tipo": "nao_contem", "path": "app/Services/FeatureFlagService.php", "padrao": "'useV2OfficeimpressoLicencas' =>" }
]
```

Ajustar também o texto da D6 (*"hoje OFF"*), que envelheceu.

## Thread 03 — nada a pedir

O `bloqueio` já diz *"vetado: [W] D4 = não"*. O placar mostra "bloqueada" porque não tem estado
`descartada` (`scripts/qa/placar-indice.mjs`, `ESTADOS`), e conta bloqueada como fechada. Vale também
para home 01, hrm 05 e patrimonio 05. Se a métrica precisar tirar descartadas do denominador, o
conserto é na máquina, não no índice.
