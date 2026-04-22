# ADR 0003 · IA opt-in, desligada por padrão

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude

## Contexto

DocVault classifica evidências (bug, rule, flow, quote, screenshot, decision) e pode sugerir módulo alvo + user story associada. Duas abordagens:

1. **IA sempre ligada**: toda evidência ingerida é classificada automaticamente por LLM.
2. **IA opt-in**: classificação manual por padrão; IA só quando explicitamente ligada.

Avaliações externas (ChatGPT) sugeriram sistema com "agentes autônomos" — `ClassifierAgent`, `StructurerAgent`, `ProjectManagerAgent` decidindo sozinhos.

## Decisão

**IA desligada por padrão** via flag `DOCVAULT_AI_ENABLED=false` no `.env`. Classificação inicial é **manual no Inbox**. Quando IA for ligada (futuro), ela **sugere** campos pré-preenchidos — ainda exige confirmação humana antes de aplicar (`status: triaged → applied`).

**Sem agentes autônomos** tomando decisões irreversíveis (ex.: regravar SPEC.md sem review).

## Consequências

**Positivas:**
- Custo zero de API enquanto não usar (ambiente local, dev, CI).
- Zero risco de alucinação vir pra documentação oficial.
- Validação humana cria dataset limpo pra fine-tuning futuro.

**Negativas:**
- Triagem manual é mais lenta no curto prazo.
- Requer disciplina de um humano olhar Inbox regularmente.

**Trade-off consciente**: velocidade automática vs. qualidade auditável. Documentação errada vira débito técnico amplificado — preferimos devagar e correto.

## Alternativas consideradas

- **Auto-classificar e marcar como `triaged`** (requer só revisão rápida): descartado — humano tende a aprovar sem ler.
- **Auto-aplicar com confidence > 0.9**: descartado — threshold é frágil, dá falso-positivo em casos sutis.
- **Agentes rodando em background**: descartado — sem observabilidade, quebra silencioso em prod.
