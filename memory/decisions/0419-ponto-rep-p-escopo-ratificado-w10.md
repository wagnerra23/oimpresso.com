---
slug: 0419-ponto-rep-p-escopo-ratificado-w10
number: 419
title: "Ponto — escopo do REP-P sem selfie ratificado (W10): 7 rotas reais, app do colaborador, fila do gestor e aba REP-P"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-29"
module: pontowr2
tags: [ponto, rep-p, mobile, portaria-671, lgpd, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0418-ponto-listas-servidor-forma-prototipo-e-13-abas
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0419 — Ponto: escopo do REP-P ratificado (W10)

## Contexto

A [ADR 0383](0383-ponto-interno-nao-coleta-biometria.md) (aceita em 2026-08-27) tirou selfie e
biometria do REP-P e disse textualmente que a "Onda 4 (REP-P) do plano do Ponto precisa ser
reescrita". A reescrita é a thread **06** do playbook do Ponto
(`prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/06-rep-p.md`), que ficou travada na
decisão **W10** ("ratificar o escopo reescrito", `00-INDICE.md` §6) — 6 PRs parados.

Estado medido no `main` em 2026-09-29: as 7 rotas do bloco 2 de `Modules/Ponto/Http/routes.php`
(`/ponto/api/*`) são closures `abort(501)`; `Api/MobileMarcacaoController` existe e nenhuma rota o
alcança; não há `Pages/Ponto/Mobile/`. Por isso a aba "REP-P (celular)" — a 13ª do protótipo —
ficou fora da [ADR 0418](0418-ponto-listas-servidor-forma-prototipo-e-13-abas.md): aba para rota
inexistente seria link morto.

## Decisão

[W] 2026-09-29, pergunta explícita com as opções: **ratificado o escopo da thread 06 como está.**

1. As 7 rotas `abort(501)` viram métodos reais do `MobileMarcacaoController` (reusando o
   `MobileMarcacaoService`, cujo anti-fraude já está pronto — expor, não reescrever).
2. App do colaborador em 3 telas — **Bater ponto · Meu espelho · Justificar** — e a **fila do
   gestor** (`ValidacaoMobile`), com a forma do protótipo `ponto-mobile.jsx`.
3. Sem selfie e sem biometria (ADR 0383). Anti-fraude que fica: GPS accuracy ≤ 500 m **recusa**
   sinal ruim (não existe "bater mesmo assim"), clock-skew ≤ 30 s, geofence **sinaliza** para
   revisão humana, NSR + hash encadeado + append-only pelo `MarcacaoService`.
4. A aba **"REP-P (celular)"** entra no header de módulo **junto com a tela**, nunca antes.

## Consequências

- A thread 06 sai da espera e segue na ordem dela (API → telas do colaborador → fila do gestor
  → contrato), 1 PR por passo, Tier 0 multi-tenant em toda query.
- A thread 07 (contratos do Ponto → required) depende do contrato `ponto-rep-p` que a 06 cria.
- O pedido de edição do `00-INDICE.md` do Cowork fica em
  `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/_DECISOES-W-2026-09-29.md`.

## Fonte

- [W] 2026-09-29, nesta sessão, ao pedir o PR da aba REP-P.
