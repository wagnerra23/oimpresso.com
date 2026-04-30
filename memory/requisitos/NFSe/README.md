# NFSe — Onboarding Eliana

> **Você é**: Eliana[E] · Owner único deste módulo
> **Cliente alvo**: empresa **oimpresso** (sua empresa + Wagner) — **NÃO** ROTA LIVRE
> **Cidade**: Tubarão-SC

## Comece aqui

1. Lê [`SPEC.md`](SPEC.md) — visão + lista completa de tasks (US-NFSE-001..014)
2. Lê [`adr/arq/0001-cliente-oimpresso-modulo-standalone.md`](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) — por que NFSe é módulo standalone, não dentro de RecurringBilling
3. Sua **primeira task é `US-NFSE-001`** — pesquisa fiscal Tubarão (SN-NFSe Nacional vs ABRASF municipal). Tudo o resto depende disso.

## Regras invioláveis

- **ROTA LIVRE não usa NFSe** — se ver código que mistura ROTA LIVRE com NFSe, é bug. Reporta e corrige.
- **Não criar `Modules/RecurringBilling/`** pra isso. UltimatePOS já tem `recurring_invoice` nativo (em `app/Http/Controllers/SellPosController.php`).
- **Pode usar a recorrência nativa do UltimatePOS** como gatilho de emissão (US-NFSE-007).
- **Cert A1 vai pra `nfe_certificados`** (tabela neutra, compartilhada com futuro NfeBrasil).
- **Eliana commita no padrão `[E]` ou `[E+C]` se pareada com Claude**. Ex: `feat(nfse): adapter Focus NFe [E]`.
- **PII real (CNPJ tomador, valor, etc.)** nunca em commits/PRs — usar fakes em tests, dados reais só em DB.

## Capacidade

- 2-4h/dia
- Paralelo a outras tasks suas (não bloqueia Cycle 01 do Wagner)
- Estimativa: ~4-5 semanas calendário pra MVP completo (1 NFSe emitida real em produção)

## Quando travar

- Decisão fiscal (cert, regime, código LC 116) → **Wagner + contador**
- Decisão UI → **Wagner**
- Bug Inertia/AppShellV2 → consulta [`MANUAL_CLAUDE_CODE.md`](../../../MANUAL_CLAUDE_CODE.md) ou pareia com Claude

## Marcos visíveis

- [ ] US-NFSE-001 → 1 documento `PESQUISA_TUBARAO.md` com decisão SN-NFSe vs provider
- [ ] US-NFSE-003 → migrations rodando local (Eliana mostra `php artisan migrate` ok)
- [ ] US-NFSE-008+009 → tela `/nfse` no localhost com lista vazia + botão "Nova NFSe"
- [ ] US-NFSE-013 → 🎉 **1 NFSe REAL emitida em Tubarão** (PDF DANFSE imprimível, validada pela prefeitura)

## Chave do sucesso

Cada task entrega valor isolado:
- Sprint A entrega **conhecimento fiscal** (mesmo se nada de código for pro main)
- Sprint B entrega **backend testado** mockado
- Sprint C entrega **tela navegável** (mesmo sem emissão real)
- Sprint D entrega **NFSe real** funcionando em produção

Não tente pular Sprint A. Pesquisa fiscal mal-feita = 2 semanas de retrabalho.
