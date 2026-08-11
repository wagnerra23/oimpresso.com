---
id: requisitos-nfse-readme
---

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

---

## Do módulo (histórico movido de `Modules/NFSe/README.md`)

> Movido em 2026-08-10. Os dois arquivos tinham naturezas DIFERENTES — acima o doc de
> requisito, aqui a descrição técnica que vivia junto do código. Conteúdo preservado
> na íntegra; nenhum lado foi descartado.

# Módulo NFSe

Emissão de Nota Fiscal de Serviços Eletrônica via **Sistema Nacional NFSe** (LC 214/2025).

- Webservice federal direto (`sefin.nfse.gov.br`) — sem provider terceiro, custo zero
- Município: Tubarão-SC (IBGE `4218707`) — migrou pra SN-NFSe em 01/01/2026
- Auth: Certificado A1 (.pfx)
- Lib: [`nfse-nacional/nfse-php`](https://packagist.org/packages/nfse-nacional/nfse-php) v1.19+

## Configuração

Adicionar ao `.env`:

```env
# NFSe — Sistema Nacional NFSe (LC 214/2025)
NFSE_AMBIENTE=homologacao          # homologacao | producao
NFSE_CERT_PATH=storage/certs/oimpresso.pfx
NFSE_CERT_SENHA=sua_senha_aqui
NFSE_MUNICIPIO_IBGE=4218707        # Tubarão-SC
```

Certificado A1:
```bash
# Copiar .pfx pra pasta storage/certs/ (gitignored)
mkdir -p storage/certs
cp /caminho/para/oimpresso.pfx storage/certs/oimpresso.pfx
```

## Sprints

| Sprint | Tasks | Status |
|--------|-------|--------|
| A — Pesquisa + setup | US-001 ✅ · US-002 🔄 · US-003 | Em progresso |
| B — Backend | US-004 · US-005 · US-006 · US-007 | Pendente |
| C — UI React | US-008 · US-009 · US-010 | Pendente |
| D — Validação + prod | US-011 · US-012 · US-013 · US-014 | Pendente |

Ver SPEC completa: [`memory/requisitos/NFSe/SPEC.md`](SPEC.md)

## Pesquisa fiscal

Resultado US-001: [`memory/requisitos/NFSe/PESQUISA_TUBARAO.md`](PESQUISA_TUBARAO.md)
