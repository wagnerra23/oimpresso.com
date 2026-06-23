# 2026-06-11 — Ondas de qualidade Q1–Q5 (ponte pro Code)

## Pedido
[W]: (1) método pra controlar o caos de arquivos de IA; (2) erros comuns de dev com IA — o que já resolvemos, o que falta; (3) "gere as ondas que o code precisa criar".

## O que foi feito
1. Respostas grounded (LICOES_CC + PROCESSO_MEMORIA_CC lidos no turno): taxonomia de 8 classes de erro (E1 alucinar fato · E2 vitória sem prova · E3 proliferação · E4 reinventar · E5 escopo estourado · E6 memória stale · E7 teste que não testa · E8 humano como detector). Resolvidas com defesa forte: E1/E2/E3/E6/E8-design. Abertas no repo: E7 (gates G-3/G-7/ratchet) + dicionários + visual stub.
2. **Releitura @main no turno (Portão 1) corrigiu 2 fatos stale:**
   - `e2e-gate.yml@main` ✓lido: ainda `workflow_dispatch` não-required, MAS harness JÁ estabilizado (MySQL+schema-squash+visreg-login+FsmSeeder); o próprio yml documenta o critério de flip (2 verdes → pull_request+required).
   - `scripts/casos-test-results.json@main` ✓lido: **NÃO está mais vazio** — 5 UCs pass (06-10). G-7 tem 1ª prova (meu "G-7 sem prova" de 06-09 estava stale).
3. **Ponte zero-toque:** `prototipo-ui-patch/PROMPT_PARA_CODE_ONDAS-QUALIDADE-GOVERNANCA.md` — 5 ondas ordenadas:
   - **Q1** flip G-3 E2E → required (2 verdes → pull_request) · **Q2** G-7 honesto + ratchet cobertura (≥10 UCs, P0 primeiro, fio venda→estoque→faturamento→caixa) · **Q3** dicionários de domínio ×5 (estoque/faturamento ANTES das telas novas) · **Q4** gate visual de pixel real (US-GOV-013) · **Q5** meta-gates (registry de gates, frescor, lição-sem-asserção repo-side).
   - Regras transversais: advisory→2 verdes→required; check provado 2 lados; estender-não-recriar; sem cunhar ADR; required flip = 1 clique [W].

## Decisões
- Nenhuma Tier 0 nova; tudo proposta §10.4 ([CL] valida contra main).

## Erros + correção
- Nenhum erro novo nesta sessão. 2 fatos stale meus (G-7 vazio · "harness em reconstrução") corrigidos por releitura @main antes de afirmar — Portão 1 funcionou.

## Residual
- Q1–Q5 dependem de [W] colar o prompt 1× no Code.
- Lado [CC] (não vai pro Code): benchmark §11 com hiato; manifesto-de-leituras (Portão 1 mecanizado) não construído.

## Refs
- `prototipo-ui-patch/PROMPT_PARA_CODE_ONDAS-QUALIDADE-GOVERNANCA.md` · `e2e-gate.yml@main` · `casos-test-results.json@main` · CODE_NOTES@main (parcial).

## Próximo passo
[W] cola o prompt das ondas no Code. Code roda Q1→Q5 sem novo pedido, reportando por onda em CODE_NOTES.
