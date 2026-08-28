---
slug: 0383-ponto-interno-nao-coleta-biometria
number: 383
title: "O ponto interno não coleta biometria — sem selfie no REP-P"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
module: pontowr2
kind: decision
decided_by:
  - W
decided_at: '2026-08-27'
quarter: 2026-Q3
supersedes: []
related:
  - 0093-multi-tenant-isolation-tier-0
---

# O ponto interno não coleta biometria

## Contexto

O `MobileMarcacaoService` + `Api/MobileMarcacaoController` (W28-8, mai/2026) nasceram
"Tangerino-like": marcação mobile REP-P com **selfie obrigatória**
(`'selfie_base64' => 'required|string|min:100000'`), anti-cheat que recusava a marcação
sem foto, e um stub `verificarBiometria()` apontando AWS Rekognition / Face++ — com item
de roadmap ativo *"AWS Rekognition liveness (W29+) — 2d + R$ ~50/mês"*.

Três fatos medidos em 2026-08-27/28, ao planejar a Onda 4 (REP-P) do módulo:

1. **Nunca rodou.** Varredura contada no repo inteiro: **zero** referência ao controller
   em `Routes/` ou `routes/`. Nada o alcançava por HTTP, logo nenhuma selfie foi coletada
   em produção. Era código morto exigindo dado sensível.
2. **O hash era persistido.** A selfie não ficava só em log: `hash('sha256', $selfieB64)`
   compunha o `dispositivo_id` (`mobile:{uuid}:{sha256[0:16]}`), gravado na marcação —
   que é append-only por força da Portaria MTP 671/2021.
3. **O canon afirmava que funcionava.** `SCOPE.md` e o `catalog.json` derivado dele
   declaravam o endpoint como existente e autenticado por Sanctum; o
   `AUDIT-SENIOR-2026-05-25` dizia *"MobileMarcacaoController::registrar … funciona"*, em
   contraste explícito com os outros stubs 501. A próxima sessão leria isso e construiria
   o REP-P assumindo captura pronta e aprovada.

## Decisão

**O sistema interno de ponto não coleta, não trafega e não deriva dado biométrico.**

[W] 2026-08-27, textual: *"LGPD não pode ter isso no sistema interno de ponto"*, seguido
de *"não existe"* sobre a rota.

Dado biométrico é **dado pessoal sensível** (LGPD **Art. 5º, II**) e seu tratamento exige
hipótese própria do **Art. 11** — que não se sustenta aqui, porque o anti-fraude do REP-P
**não depende dele**. O que fica, e é suficiente:

- GPS accuracy ≤ 500m (recusa sinal ruim / spoof)
- clock-skew do device ≤ 30s
- geofence por business — **sinaliza** para revisão humana, não bloqueia
- NSR sequencial + hash encadeado + append-only, pelo `MarcacaoService` canônico
  (Portaria MTP 671/2021 Art. 85)

⚠️ **Correção de citação que circulava no projeto:** o plano de ondas e documentos derivados
citavam **LGPD Art. 9º** para a selfie. Está errado — o Art. 9º é o direito do titular à
informação sobre o tratamento. A base correta é **Art. 5º, II** (definição de sensível) +
**Art. 11** (hipóteses de tratamento).

## Consequências

- `SELFIE_MIN_BYTES`, `verificarBiometria()` e o anti-cheat da selfie **deletados**;
  `dispositivo_id` passa a ser `mobile:{device_uuid}`.
- O item de roadmap do AWS Rekognition **morre com esta ADR** — não é "adiado".
- **Onda 4 (REP-P) do plano do Ponto precisa ser reescrita**: PR-D1/PR-D2 previam selfie, e
  a decisão pendente *"copy da selfie (LGPD)"* deixa de existir como pergunta.
- `SCOPE.md` corrigido na fonte + `catalog.json` regenerado pelo produtor
  (`catalog-graph.mjs --write`), nunca editado à mão.
- `AUDIT-SENIOR-2026-05-25.md` **preservado intacto**: é registro datado do que se
  acreditava naquela data. Fato datado se preserva; ponteiro vivo se corrige.

## Como se reconhece violação

Qualquer reintrodução de captura facial no fluxo do ponto — `selfie_base64`,
`SELFIE_MIN_BYTES`, `$selfieB64`, `verificarBiometria`, sufixo derivado no
`dispositivo_id`, ou integração de liveness/1:1 — **sem ADR nova que reabra esta**.

**É máquina, não memória:** o `Wave28MobileMarcacaoTest` carrega um GUARD que lê o fonte do
Service e do Controller e falha se qualquer um desses termos voltar. Medido no CI em
2026-08-28: `✓ it GUARD LGPD · o Service NAO conhece biometria` (0.17s, executado — não
skipped), lane `PHP / Pest (Ponto · MySQL)` com 267 passed.

O guard cobre o **ponto interno**. Ele não opina sobre biometria em outro contexto de
produto — se um dia isso se colocar, é ADR nova, com hipótese do Art. 11 declarada.

**Ratificação:** [W] em 2026-08-28, no fio de trabalho desta decisão: *"aceito"*.
Executada em [#6393](https://github.com/wagnerra23/oimpresso.com/pull/6393) (−111/+51).
