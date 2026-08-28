---
date: '2026-08-28'
time: 09:45 BRT
slug: onda1-ponto-fechada-biometria-removida
tldr: "Onda 1 do Ponto fechada em 4 PRs + ADR 0383. VRT 0→2, zero cor crua nas Pages, biometria removida do REP-P (exigia selfie e nunca teve rota; o hash era persistido no dispositivo_id). 5 decisões do HANDOFF-ponto seguem travando 9 PRs."
type: handoff
status: aceito
authority: reference
module: pontowr2
---

# Onda 1 do Ponto fechada — e a biometria que exigia foto sem nunca ter rota

## Estado no fechamento

⚠️ **Sem snapshot MCP.** As tools MCP (`cycles-active`, `my-work`, `sessions-recent`,
`decisions-search`) **não estavam disponíveis** nesta sessão — só o `brief-fetch` via hook
de SessionStart. O checklist MCP-first da [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)
**não foi cumprido**, e registro isso em vez de fingir que foi. O estado abaixo é medido por
`git`/`gh`, que estavam disponíveis.

- `main` em `a50da3c38f` no momento da última verificação
- 4 PRs desta sessão mergeados: #6365, #6362, #6369, #6393
- ADR **0383** criada e ratificada por [W] no fio (*"aceito"*)
- índice regenerado: **388 ADRs, 349 ativos, 13 colisões, 0 alertas**

## O que mudou no módulo (medido no main, não afirmado)

| Métrica | Antes | Depois |
|---|---|---|
| VRT (baseline de pixel) | 0/20 | **2/20** |
| E2E | 0/20 | **2/20** |
| `blue-*`/`violet-*` nas Pages | 5 sites | **0** |
| biometria em código executável | selfie obrigatória | **0** (+ GUARD Pest) |

## Por que a Onda 1 custou 4 PRs e não 1

O plano previa "trocar cor em 2 arquivos". O gate visual é **fail-closed**: Page sem contrato
em `visreg-screens.json` é barrada. Com VRT 0/20, a cascata foi: fixture de seed inexistente
(`VisregPontoSeeder` — o `visual-regression.yml` NÃO usa a action `pest-mysql-setup`) →
workflow required editado → 2 contratos → 2 baselines → 1 RUNBOOK.

**[W] e o Cowork estavam certos** ao pôr o A0 na frente. Tentei dobrá-lo no A1 duas vezes.

## Armadilhas descobertas (valem para qualquer módulo)

1. **`screen` do manifesto visreg dropa `/Index`.** `screen` = `component` sem `/Index`
   (`Compras` ← `Compras/Index`). Tela `Show` acerta por acidente de forma e esconde a regra.
2. **Canário anti-verde-vazio**: PR que só toca `.md`/`.json`/`.snap` marca
   `visual_required=true` mas resolve `screens=[]` → Pest sem teste → sem contagem → barra.
   Dar a tela ao PR conserta e melhora o desenho.
3. **Corrida com o `main`**: ~1 commit/20min contra gate de 10-15min, e o `Preflight` exige
   ancestralidade **no instante do run**. Derrubou 3 PRs. Converge no merge, não na insistência.
4. **`_visreg` roda com seed próprio** — `db:seed` + 7 `Visreg*`, nenhum tocava Ponto.

## A biometria

[W] respondeu à pergunta errada com a resposta certa: eu ia perguntar a *copy* da tela de
selfie; ele disse *"LGPD não pode ter isso no sistema interno de ponto"*.

Medido: nunca teve rota (nada coletou em prod), **mas** o `hash('sha256', $selfieB64)`
compunha o `dispositivo_id`, **persistido** na marcação append-only. E o canon (`SCOPE.md`,
`catalog.json`, `AUDIT-SENIOR`) afirmava que o endpoint funcionava.

Corrigi a **fonte** e regenerei o derivado; **preservei** o `AUDIT-SENIOR` (registro datado).
Guard Pest provado por nome no CI. Citação legal corrigida: **Art. 5º, II + Art. 11**, não
Art. 9º.

## Próximo passo — 5 decisões travam 9 PRs

Não dependem de CI. São a maior alavanca parada:

1. Competência: tabela `ponto_competencias` nova ou derivado das apurações?
2. Permissão: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`?
3. Exceções assinadas: onde persistem? bloqueiam o AFD?
4. Reabrir competência fechada: com auditoria ou definitivo?
5. REP-P sem GPS: permitir marcar com justificativa?

*(A 6 — copy da selfie — morreu com a ADR 0383. A 7 é fora de escopo.)*

⚠️ **A Onda 4 (REP-P) do plano precisa ser reescrita** antes de virar PR: PR-D1/D2 previam selfie.

## Dívida nomeada, não escondida

- **18 UC órfãos**: NÃO é rename de `it()` — é conversão PHPUnit→Pest em 3 arquivos /
  22 métodos / ~1400 linhas de teste Tier 0. O `casos:report` desaconselha o lote.
  Recomendação: oportunístico, só `EspelhoContratoTest` (8 UC).
- `_components/` do Ponto ainda usa `emerald-*` — semântica de estado (HE alta/média),
  decisão de design, não troca mecânica.
- `Colaboradores/Index`, `Escalas/Index`, `Welcome` barrados pelo guard MWART (rc=2) —
  cada um precisa de RUNBOOK próprio.

## Meus erros, para o próximo não repetir

Quatro, todos **"ler a fonte certa para a pergunta errada"**, todos pegos por máquina:
seed errado (404) · `id=1` derivado e não observado · `screen` com `/Index` (a convenção
estava no arquivo que eu já lera) · `reset --hard` encadeado a `checkout` que podia falhar
(caiu na branch errada; salvou o push anterior, não a cautela).
