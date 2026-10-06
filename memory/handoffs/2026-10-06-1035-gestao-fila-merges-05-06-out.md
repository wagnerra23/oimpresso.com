---
date: "2026-10-06"
time: "10:35 BRT"
slug: gestao-fila-merges-05-06-out
tldr: "Gestão da fila de merges 05→06/10: ~30 PRs mergeados (ERP + app #68), 3 da Fabricação com ok [W] e conferidos em prod, #8669 Minha assinatura quebrou em prod (500) e foi consertado no #8700, #8595 fechado (ADR 0409), incidente do GitHub Actions atravessado, 47 playbooks novos importados (#8746). Lições para o próximo gerente: o app desktop liga auto-merge sozinho; lane advisory não roda no head; PR de bot não dispara CI."
prs: [8645, 8643, 8644, 8642, 8651, 8659, 8672, 8671, 8688, 8690, 8691, 8669, 8700, 8679, 8694, 8693, 8701, 8698, 8707, 8715, 8689, 8720, 8729, 8744, 8745, 8741, 8742, 8743, 8746]
---

# Handoff — Gestão da fila de merges (05→06/10)

Sessão "Gerenciar merges da fila de PRs (continuação)". Autorização [W]: "gerencie o merge de todos", com as exceções da regra mestre.

## O que foi feito

- **Merges diretos meus (ERP):** #8645 #8643 #8644 #8642 #8651 #8659 #8672 #8671 #8688 #8690 #8691 #8679 #8694 #8693 #8701 #8698 #8707 #8715 #8689 #8720 #8729 #8744 #8745 #8746, mais os de valor com ok [W] dado a esta sessão: #8669, #8741, #8742, #8743. **App:** #68 no `428aca6` (ok [W] depois da tabela; o `7f8f799` anterior foi barrado porque [W] mudou a decisão para "configuração da empresa, padrão desligado").
- **#8669 (Minha assinatura) deu 500 em prod.** Causa medida por dois caminhos (log de prod + CT 100): o Laravel 13 injeta o default `null` em `ModuleUtil $moduleUtil = null`. Conserto no #8700, e `/subscription` foi conferida em prod. O teste do #8669 nunca rodou no head; a lane advisory `verticais` não roda em `synchronize`.
- **Fabricação #8741/#8742/#8743** em prod. Painel da ordem conferido no biz=1 contra o detalhe antigo: valores batem (198/198, 0/0). Duas divergências antigas (lista "2 ingredientes" × 1; quantidade 1,00 × 2,00 com 1,00 de desperdício) foram para a sessão-chip `task_eb29279b`.
- **#8595 fechado** (ADR 0409: regravar baseline visual está aposentado), com comentário de reabertura.
- **Incidente GitHub Actions** (19:11 UTC 05/10, runners sem atribuição): 648 runs na fila, 2 rodando. Cancelei 59 runs obsoletos (SHA substituído / branch sem PR), avisei as sessões para segurar push, e liberei quando normalizou.
- **Playbooks novos:** 47 arquivos só no Cowork. Exportei o zip pelo Chrome do [W] (Share → Project HTML → Project archive; o download ficou no `.tmp`, copiado e testado). A sessão de import gerou o #8746 (mergeado) e abriu 9 threads. Respondi no chat do Cowork as 4 decisões que [W] aceitou (Essenciais D1, Sistema D1/D2, Telas soltas D1).
- Sessões concluídas arquivadas (≈20).

## Lições para o próximo gerente (medidas, não opinião)

1. **O app desktop liga auto-merge sozinho** nos PRs das sessões com "Auto-fix pull requests" ligado (timeline: `auto_squash_enabled` segundos após o `gh pr create`; vale para o #8700 desta sessão). Ele não conhece as exceções da regra mestre: o #8655 (valor) entrou sem ok [W]. **Ao abrir PR de valor/estoque, desligue o auto-merge** (`gh pr merge N --disable-auto`) e confira a timeline.
2. **Required verde não prova que o teste do PR rodou.** Antes de mergear PR com teste novo, rode `node scripts/governance/test-lane-coverage.mjs --pr N`; se a lane não rodou no head, `gh workflow run <lane>.yml --ref <branch>` e espere.
3. **PR aberto por bot (`app/github-actions`) não dispara CI.** Fechar e reabrir dispara (#8689).
4. **PR empilhado:** quando a base entra por squash, o empilhado fica DIRTY. Junte `origin/main` ao branch (sem rebase), regenere derivados (`module-surface.mjs <Mod> --write`) e confira o `casos-coverage-guard` (o merge muda a data do `.tsx` e acusa G-6 stale).
5. **Draft não é recusa**, mas tirar do draft re-roda os required (ready_for_review).
6. **Vigia:** 1 `gh pr list` a cada 5 min; script com teto de 2 h (o harness mata). Checagem de required pela união `classic_protection ∪ rulesets` (48).
7. **Prod:** o login do oimpresso.com no Chrome expira; peça ao [W] para logar, nunca digite senha.

## Estado no fechamento

- Abertos: #8750 (Oficina, lembrete de revisão por km — área do app → [af6112]), #8749 (Produto 04 Etiquetas, mostra preço → **exceção de valor**, levar tabela ao [W]), #8747 (handoff do app). App #56 parado (CI do app sem cobrança).
- Pendências [W]: cobrança do GitHub Actions; `.env` C1 de Vendas (6 linhas `MWART_VENDAS_*`); decidir desligar o auto-merge do app desktop; app nas lojas (conta Google Play, demo, revisão); jurídico /privacidade; aviso à ROTA LIVRE antes do C4.
- Threads em execução: Essenciais 01/02, Estoque 01, Crm 09, Produto 04, Officeimpresso 09 (cutover → merge [W]), Placar A-LOTE+Superadmin A2, Tema escuro 01 (check advisory; promoção é [W]), Telas soltas A1.
