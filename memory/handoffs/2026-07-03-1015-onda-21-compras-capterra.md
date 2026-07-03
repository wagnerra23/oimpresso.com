---
date: "2026-07-03"
time: "10:15 BRT"
slug: "onda-21-compras-capterra"
tldr: "Onda 2.1 (Compras, módulo mais fraco): FICHA de capacidade 34/100 + INVENTARIO + BRIEFING refresh. Wagner pegou 3 imprecisões (import DF-e existe no NfeBrasil, estoque/grade preservados do Blade). 5 chips spawnados; batch de tasks e sentinela viraram chips adversariais."
prs: [3708, 3709, 3711, 3713, 3714]
related_adrs:
  - "0089-capterra-driven-module-evolution"
  - "0101-tests-business-id-1-nunca-cliente"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
next_steps:
  - "Merge #3713 (correção C07/C10) + #3714 (BRIEFING refresh) — R10 Wagner"
  - "Chips rodando: 5 (test cálculo Tier 0, PiiRedactor, eager-load [ended], sentinela staleness, batch dedup)"
---

# Handoff — Onda 2.1 Compras (adversário Capterra de capacidade)

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO em COPI.
- **my-work:** 30 tasks (8 REVIEW, 8 BLOCKED, 14 TODO) — **nenhuma de Compras** (confirma que o batch do INVENTARIO ainda NÃO foi criado no MCP — correto, virou chip adversarial).
- **decisions 24h:** nenhuma ADR nova (brief).

## O que aconteceu
Primeira etapa do ciclo de **Compras** no programa de ondas (OK [W]). Rodei o adversário `capterra-senior` (via general-purpose — o subagent-type não estava registrado) sobre o módulo mais fraco (module-grade 59). Entregou a **FICHA de CAPACIDADE** que faltava (só existia a de design, 67).

**Nota capacidade: 34/100** — gap −38 vs topo BR (Omie/Hiper ~72). §8 adversarial forte: module-grade mede higiene não valor de compras; FSM é teatro (const no Drawer, não persistida); módulo não está em prod pra ninguém; hardening tests são source-grep tautológicos (Tier 0 valor/estoque).

**Wagner pegou 3 imprecisões minhas** (todas corrigidas apurando o código, não argumentando):
1. **C01** — "import XML DF-e não existe" era FALSO: o pull SEFAZ NSU (`DistribuicaoDfeService`) + manifestação (`ManifestacaoService`) EXISTEM e são testados no **Modules/NfeBrasil**; falta só a **ponte DF-e→compra** (`nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService`). C01 ❌1→🟡5, nota 30→33, G-01 esforço L→M.
2. **C07 estoque** — `/purchases/store` grava `variation_location_details` por variação/local (mesmo `ProductUtil` do Blade) + guard Tier 0 novo `assertPurchaseVariationsOwnership`. Subestimado 4→5.
3. **C10 grade tam×cor** — construída ponta-a-ponta (`GET /purchases/grade-matrix` → célula→`variation_id`→purchase_line→estoque), upgrade sobre o Blade linha-a-linha. 6→7. Nota → **34**.
   **Nada se perdeu vs Blade** — estoque idêntico, grade é ganho.

Wagner também levantou: "onde o conhecimento deve morar? tenho que ficar lembrando? hook?" → respondi (BRIEFING=features ativas / charter=por tela / FICHA=benchmark) e **atualizei o BRIEFING** (estava congelado em "scaffold 05-21" apesar do módulo estar grade 59 + cockpit live). Sobre o hook: recomendei **sentinela de staleness advisory** (NÃO gate-de-presença, que a governança já vetou — proibicoes §5 charter-sync-gate + L-24).

## Artefatos gerados
- `memory/requisitos/Compras/CAPTERRA-FICHA.md` (capacidade 34, 10 seções) — #3708 + correções #3711/#3713
- `memory/requisitos/Compras/CAPTERRA-INVENTARIO.md` (3 buckets ✅3/🟡8/❌8 + 16 tasks propostas) — #3709
- `memory/requisitos/Compras/BRIEFING.md` (refresh scaffold→cockpit-live, 3 réguas) — #3714
- `memory/sessions/2026-07-03-capterra-compras.md` — #3708

## Persistência (3 canais)
- **git:** #3708/#3709/#3711 MERGED · #3713/#3714 abertos (aguardam merge Wagner — R10).
- **MCP:** batch NÃO criado (virou chip adversarial `task_361f38f9`).
- **BRIEFING:** atualizado (#3714).

## Chips spawnados (5 — sessões independentes)
- `task_cf09af72` Teste E2E cálculo custo/estoque (Tier 0) — rodando
- `task_08607a7a` PiiRedactor no Drawer (LGPD) — rodando
- `task_9943da39` Eager-load anti-N+1 — **ended**
- `task_389c747c` Sentinela staleness BRIEFING (adversarial) — rodando
- `task_361f38f9` Batch dedup do INVENTARIO (adversarial) — rodando

## Próximos passos pra retomar
`gh pr view 3713 3714` → mergear se verdes. Ver resultado dos 5 chips (dedup do batch dirá quais das 16 tasks são NOVAS vs já-US/já-feitas).

## Lições catalogadas
- **Verificar reuso cross-módulo antes de declarar capacidade "ausente"** — a capacidade (import DF-e) vivia no módulo irmão NfeBrasil. Corrigir apurando código, não argumentando (Wagner é dono do domínio).
- **BRIEFING apodrece silenciosamente** apesar de regra+skill soft — precisa sentinela determinístico (advisory, staleness-detector, não presence-gate).
- Padrão da sessão: Wagner mergeia rápido → commit follow-up cai em branch órfã → recriar fresh off main (aconteceu 3×, todas resolvidas).

## Pointers detalhados
- Session log: `memory/sessions/2026-07-03-capterra-compras.md`
- Onda: `memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.1-adversario-capterra.md` (gabarito) + `template-onda-modulo.md`
- Gabarito Sells: `memory/requisitos/Sells/CAPTERRA-FICHA.md` (Onda 1.1, nota 60)
