---
date: "2026-07-16"
topic: "Adversário do ponto de entrada de tela — Wagner informa US ou UC? Nenhum: canal real é chat + 4 slots que vinculam"
authors: [W, C]
outcomes:
  - "Veredito verificado: US e UC são artefatos de SAÍDA do agente, não canal de pedido do dono"
  - "Canal de entrada real dominante = chat (evidência convergente handoffs/sessions/ADR 0070)"
  - "Lápide §5 nova em proibicoes.md (não re-propor US/UC como canal de pedido)"
  - "Subseção nova em how-trabalhar.md (4 slots onde a palavra do dono vira máquina)"
  - "Task spawned: fix ucHeadRe trunca UC com dígito no prefixo (task_681bc138)"
  - "PR de higiene: labels advisory stale em anchor-drift/anchor-lint/gate-selftest/intent.json"
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0070-jira-style-task-management-current-md-removed
---

# Adversário do ponto de entrada — "onde eu informo: US ou UC?" (2026-07-16)

## Contexto

Wagner perguntou como descrever/pedir uma tela pro design ("seria algum contrato visual? ancorado em alguma coisa?"). O agente mapeou o trio-de-tela + contrato visual e recomendou **US no SPEC com `_pendente_`** como ponto de entrada. Wagner mandou o adversário: *"confira o processo vivo, onde eu deveria informar apenas os US? ou UC?"* — e depois *"confira"* de novo (ultracode).

## Método

1. **Rodada 1:** 2 céticos adversariais independentes (um pra refutar "US é o ponto de entrada", outro pra testar a contra-proposta "UC é o lugar certo"). Ambos REFUTARAM as duas opções.
2. **Rodada 2 (confira):** workflow com **7 verificadores de contexto-zero** (~970k tokens), um por cluster de claim, cada um instruído a refutar re-medindo do zero (anchor-lint re-rodado, git recontado, branch protection viva via `gh api`). Placar: 3 CONFIRMADAS, 3 CORRIGIDAS, 0 refutadas na conclusão.

## Veredito (verificado)

**Nem US, nem UC são canal de pedido do dono.** São artefatos que o agente escreve — majoritariamente DEPOIS do código. O canal de entrada real é o **chat**; a palavra do dono vira máquina em 4 slots (tabela em [how-trabalhar.md §Pedido de tela](../how-trabalhar.md)).

### Por que US caiu

- SPEC é o elo **mais fraco** da precedência Tier 0 (`teste verde > casos.md > charter > SPEC`, [proibicoes.md:108](../proibicoes.md)) e **não está no trio required** — nenhum dos 27 required contexts exige US pra tela nova (varredura 1-a-1, [required-checks-baseline.json](../../governance/required-checks-baseline.json)).
- `Atendimento/Csat/Index` está em prod desde 2026-05-12 **sem US nenhuma** (charter só +61d, casos.md nunca; único hit "CSAT" nos 59 SPECs é sobre o `DispatchCsatJob`, não a tela).
- `_pendente_` **conta como coberto** ([anchor-lint.mjs:542](../../scripts/governance/anchor-lint.mjs): `covered = anchored_ok + pendente + parcial`) — escrever o pedido já cumpre a métrica. Números do run 2026-07-16: 967 US · 372 `_pendente_` · 327 anchored_ok · 152 sem campo · 84.2%.
- Nascimento na janela 2026-05-17→07-16: **352 US novas; 47 nasceram já ancoradas em código vs 26 `_pendente_`** (~1.8:1 doc-pós-fato; método estrito "linha `Implementado em` no diff de nascimento", estável variando a janela). **279 nasceram sem âncora nenhuma no diff.** US-INFRA-008 nasceu no MESMO commit do Controller (`24b3a2c024`); US-CRM-071 nasceu 6d depois do `.tsx`, já `done`.
- Autoria: 197 commits em SPEC.md em 60d, **182 com `Co-Authored-By: Claude`**, os 15 restantes todos com rastro de agente (branch `claude/*`, `[CC]`, footer no PR). **Zero US escritas por [W] sozinho.**

### Por que UC caiu (pior)

- G-2 do casos-gate ([casos-coverage-guard.mjs:168-175](../../scripts/casos-coverage-guard.mjs)) **pune** UC sem teste: heading `## UC-*` fora do baseline sem teste citando o id → `exit(1)` num check **required com `enforce_admins:true`**. Um UC escrito pra pedir tela inexistente nasce órfão e **bloqueia o merge de quem for atender**. Gate hostil ao autor não-programador por construção.
- Nuance importante: `casos.md` **pode** existir sem `.tsx` (G-1 itera `listPages()` = só `.tsx`; o órfão `Estoque/Movimentacao.casos.md` — casos de DOMÍNIO — é invisível ao G-1). O bloqueio não é o código, **é o teste**.
- Vocabulário é de implementação: 44% dos UC citam arquivo de teste; UC-F04 fala em `POST /financeiro/unificado/bulk` + "limite 500 por chamada". Censo: 37 casos.md · 143 UC · **11 ✅ (7.7%)** · 107 🧪 · 25 ⬜. Trilhas: [CC]=42 · [CL]=12 · [Codex]=1 · **[W]=0** (o `owner: wagner` 37/37 é presença exigida pelo G-5, não autoria).
- A válvula pré-código que existe: **`[BACKLOG]` sem id** (53 itens, 48 sem UC-id) — prosa visível sem gate, sem precedência. Ou seja: uma US com outro nome.

### Canal de entrada real = chat (investigador dedicado)

- Fluxo observado em handoffs/sessions: **[W] pede em linguagem natural → agente propõe batch → [W] aprova no chat → agente roda `tasks-create` + apenda US no SPEC + PR**. `tasks-create` é registro downstream, não entrada.
- Em ~30 ocorrências de `tasks-create` lidas em contexto: **zero** são pedido do dono via tool. Único rastro de [W] criando task direto no MCP em 2 meses: 1 row órfã (US-RB-052, 2026-05-16, "nunca no SPEC" — causou colisão de ID).
- Zero sessões no último mês começaram puxando task do backlog via `my-work`. Só ~10% dos commits com `Refs:` citam task MCP; a âncora dominante é ADR (~937).
- **Correção a claim anterior do agente:** o git **NÃO é cego ao MCP** — `tasks-create` persiste via SPEC.md→git push→webhook→DB (handoffs 2026-06-16/2026-06-20). Só row DB-only é invisível (1 caso em 2 meses).

## Correções da rodada "confira" (contra os céticos da rodada 1 e contra o próprio agente)

1. **O gate de entrada NÃO é advisory** — o cético 1 leu o label do output (`"Gate de entrada (advisory)"`) e concluiu errado. Realidade: `anchor entry/covers gate` é **required vivo desde 2026-06-24/30** (PR #3320 + promoção conjunta; P14 rename 2026-07-01 tirou "(advisory)" do NOME dos contexts mas os comentários/labels internos ficaram stale — origem do PR de higiene desta sessão). O que é advisory é a dívida legada grandfathered (264 sem aceite / 392 sem teste). Consequência: US nova `_pendente_` segue sem pressão, mas **marcar US como implementada sem aceite/teste em SPEC tocado quebra o CI** — o SPEC morde mentira nova como ledger, só não serve de canal de pedido.
2. **47:26, não 56:26** — o número do cético 1 não reproduziu por método estrito; a conclusão qualitativa (~1.8:1 pós-fato) mantém.
3. **48 de 53 `[BACKLOG]` sem id** (não 53) — 5 citam id.

## Achados colaterais

- **`ucHeadRe` trunca UC com dígito no prefixo** ([scripts/lib/uc-regex.mjs:23](../../scripts/lib/uc-regex.mjs), `[A-Z]{0,6}`): os 9 `UC-KBV2-0N` viram 1 token no dedupe → guard reporta 135 UC quando há 143, e o G-2 pode dar match falso de órfão. **Task spawned** (task_681bc138, sessão paralela rodando).
- **Labels/comentários stale que mentem enforcement** (origem do erro do cético 1): [anchor-drift.yml](../../.github/workflows/anchor-drift.yml) linhas 12/20-21/94/152 + fragmento morto de shim na 199-200; [anchor-lint.mjs](../../scripts/governance/anchor-lint.mjs) linhas 61/67/649/650/656/703; fixtures do [gate-selftest.mjs](../../scripts/governance/gate-selftest.mjs) 512/561 asserem o label errado; `nota` do [financeiro-unificado.intent.json](../../prototipo-ui/contrato/financeiro-unificado.intent.json) diz "promovido a required" (não foi — passo 4 pendente). → **PR de higiene desta sessão.**
- **`casos-coverage-baseline.json` `_meta.stats` stale** (diz 8 casos/51 UC; real 37/135-143). **NÃO tocado aqui de propósito**: a sessão paralela do ucHeadRe vai mexer no mesmo arquivo (refazer o baseline com o regex certo) — evitar colisão (regra sessões-paralelas).
- **Contrato visual continua com adoção 2/237 telas** e o gate `contrato-de-tela` segue fora dos required — o "passo 4" é admin-only ([W]), falso-positivo medido = 0. Decisão pendente do dono.

## Fontes

- Workflow `conferir-veredito-us-uc` (run `wf_b2407ce5-d43`, 7 agentes, ~970k tokens) + 2 céticos da rodada 1 (~309k).
- Comandos-chave reproduzíveis: `node scripts/governance/anchor-lint.mjs` · `node scripts/casos-coverage-guard.mjs --report` · `gh api repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks` · `git log --diff-filter=A -- <artefato>`.
