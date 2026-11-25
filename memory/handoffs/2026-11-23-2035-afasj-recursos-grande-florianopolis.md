---
date: "2026-11-23"
time: "2035 BRT"
slug: "afasj-recursos-grande-florianopolis"
tldr: "Recursos da AFASJ (São José/SC) e da Grande Florianópolis pesquisados e mergeados (#11387), com kit da reunião de 24/11. O caminho mais concreto é o edital de penas pecuniárias da VEP de São José, 1–30/04/2027 (exige mais de 1 ano na Comarca, aceita saúde)."
decided_by: [W]
cycle: null
prs: [11387]
us: []
next_steps:
  - "Reunião AFASJ 24/11: levar os 4 planos de afasj-reuniao-2026-11-24-planos.md"
  - "Até 31/03/2027: certidões, estatuto registrado, conta e projeto com 3 orçamentos (edital VEP São José)"
  - "Reunião com o vereador Jandir da Rosa (decreto da CIPFIBRO, utilidade pública municipal)"
related_adrs: ["0130-handoff-append-only-mcp-first", "0451-associacoes-rede-uniafibro-area-de-membros"]
---

# Handoff 2026-11-23 20:35 BRT — AFASJ: recursos na Grande Florianópolis

## TL;DR

Pesquisa web, zero código. Mergeado no PR #11387: `memory/clientes/uniafibro/afasj-saojose-plano-captacao.md`
(recursos por esfera, com 🟢/🟡/🔴) e `afasj-reuniao-2026-11-24-planos.md` (4 planos para a reunião de 24/11).
Também há um doc no Claude Docs para imprimir: "Reunião AFASJ — planos para captação".

## Cronologia desta sessão

| Quando (BRT) | Evento |
|---|---|
| ~19:50 | Pedido "recursos para associação de São José"; identificada a AFASJ (rede Uniafibro) |
| ~20:00 | 1ª pesquisa; PR #11387 aberto |
| ~20:05 | [W] pede Grande Florianópolis e libera a rede: ambiente pessoal `oimpresso-pesquisa-sc` com 20 hosts públicos |
| ~20:10 | 3 pesquisas paralelas (São José, Florianópolis, demais municípios + regional) |
| ~20:15 | Edital da VEP de São José lido na íntegra (TJSC tem anti-robô; aberto via Playwright) |
| ~20:30 | Revisão + kit da reunião; PR mergeado por auto-merge |

## Estado atual dos artefatos

| Arquivo | Status | Notas |
|---|---|---|
| memory/clientes/uniafibro/afasj-saojose-plano-captacao.md | ✅ main | Correção: utilidade pública estadual não exige a municipal (Lei 18.269/2021) |
| memory/clientes/uniafibro/afasj-reuniao-2026-11-24-planos.md | ✅ main | 4 planos, perguntas, decisões |

## Bloqueios / pendências

- [ ] Presidência e contatos da AFASJ não encontrados — perguntar na reunião — owner: W
- [ ] saojose.sc.gov.br fora do ar (erro 522): Fundo do Idoso, conselho e fundo PcD seguem 🟡/🔴 — owner: W
- [ ] Decreto da CIPFIBRO (Lei 6.241/2023) e órgão emissor não encontrados — owner: W
- [ ] @fibromialgicossjsc: mesmo grupo da AFASJ? — owner: W
- [ ] Check `crons de governança vivos?` vermelho em todo PR por `devcontainer-firewall.yml` (run agendada de 23/11 19:29Z falhou) — fora do escopo desta sessão

## Próximos passos (ordem)

1. Reunião de 24/11 com o kit.
2. Checklist de documentos até 31/03/2027 (edital VEP São José, 1–30/04/2027).
3. PJSC Mais Social, 1–30/09/2027.

## Estado MCP no momento do fechamento

- MCP `oimpresso` indisponível nesta sessão (proxy recusou o túnel: 403). `cycles-active`, `my-work`,
  `sessions-recent` e `decisions-search` não puderam ser consultados — fallback: índice `memory/08-handoff.md`.
- Último handoff relacionado: 2026-11-17 09:15 (calendário AREFI).
