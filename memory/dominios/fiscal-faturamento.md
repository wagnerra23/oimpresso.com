---
dominio: Fiscal & Faturamento — NF-e/NFC-e/NFS-e, documentos da transação, impostos
fonte_unica: este arquivo é a fonte canônica do vocabulário FISCAL (ADR 0264 G-4, Onda Q3)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e]
---

# Dicionário de domínio — Fiscal & Faturamento

> **Fonte única do vocabulário fiscal** (Onda Q3 — [W] vai construir faturamento AGORA; o
> dicionário existe ANTES das telas). Grounded nas migrations de `Modules/NfeBrasil` +
> `Modules/NFSe` + core.

## Princípio canônico — faturamento ≠ "nota"

**Faturar = emitir o documento fiscal + gerar o título.** "Nota" sozinha é linguagem de
balcão, não conceito do sistema: o que existe é **emissão** (`nfe_emissoes`/`nfse_emissoes`),
**documento da transação** (`transaction_documents`) e **título** (financeiro.md). Tela ou
agente que tratar "faturar" como só-imprimir-nota viola este dicionário.

## Conceitos-chave (PT-BR canônico)

- **Modelos**: `55` = NF-e · `65` = NFC-e · `67` = NFCom (em `nfe_emissoes.modelo`);
  NFS-e (serviço, prefeitura) vive em `nfse_emissoes`. MDF-e/CT-e só como `doc_type`
  de `transaction_documents` (wire futuro).
- **Ciclo da emissão NF-e**: `pendente → enviando → autorizada` (felicidade) ·
  `rejeitada/denegada/erro_envio` (falha) · `cancelada/inutilizada` (pós-autorização).
  "Cancelar" exige evento fiscal — nunca DELETE.
- **Manifestação do destinatário** (DF-e recebidos): `pendente → ciencia → confirmada`
  (ou `desconhecida/nao_realizada`).
- **Regime tributário** = `nfe_business_configs.regime ∈ {mei, simples, lucro_presumido,
  lucro_real}` — DAS ≈6% deriva do regime caixa (tela Impostos, Financeiro).
- **Split fiscal Vendas×Oficina** (oficina-auto.md): item `peca` → NF-e; `mao_obra`/
  `servico_terceiro` → NFS-e.
- **Sinônimos proibidos**: "nota de serviço" pra NFS-e em código/label (canônico: NFS-e);
  "cupom" pra NFC-e (canônico: NFC-e); "faturar" significando só imprimir.

## ⚠️ Conflito de vocabulário CATALOGADO — `nfse_emissoes.status`

A tabela foi criada pelo `Modules/NFSe` (2026_05_01, vocab `rascunho/processando/emitida/
cancelada/erro`) e **RE-criada** pelo `Modules/NfeBrasil` (2026_05_11, vocab `pending/sent/
authorized/rejected/cancelled`) — dois módulos, dois vocabulários, a MESMA tabela (ambos têm
model `NfseEmissao`). O estado cronológico atual (last-write-wins) é o do **NfeBrasil** —
declarado abaixo. Consolidar os módulos (deprecar um) é decisão [W] pendente; até lá este
dicionário trava o vocabulário VIVO e o guard acusa quem reintroduzir o antigo.

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "FiscalFaturamento",
  "migrations_paths": ["Modules/NfeBrasil/Database/Migrations", "Modules/NFSe/Database/Migrations", "database/migrations"],
  "tables_scope": ["nfe_business_configs", "nfe_dfe_eventos", "nfe_dfe_recebidos", "nfe_emissoes", "nfe_eventos", "nfe_inutilizacoes", "nfse_emissoes", "nfse_eventos_cancelamento", "nfse_provider_configs", "transaction_documents", "tax_rates", "invoice_schemes", "invoice_layouts"],
  "code_paths": ["Modules/NfeBrasil/Http", "Modules/NfeBrasil/Services", "Modules/NFSe/Http", "Modules/NFSe/Services"],
  "enums": {
    "nfe_emissoes.modelo": ["55", "65", "67"],
    "nfe_emissoes.status": ["pendente", "enviando", "autorizada", "rejeitada", "cancelada", "denegada", "inutilizada", "erro_envio"],
    "nfe_eventos.status": ["pendente", "enviado", "autorizado", "rejeitado"],
    "nfe_inutilizacoes.modelo": ["55", "65"],
    "nfe_inutilizacoes.status": ["pendente", "enviado", "autorizado", "rejeitado"],
    "nfe_dfe_eventos.status": ["pendente", "enviado", "autorizado", "rejeitado"],
    "nfe_dfe_recebidos.status_manifestacao": ["pendente", "ciencia", "confirmada", "desconhecida", "nao_realizada"],
    "nfe_business_configs.regime": ["mei", "simples", "lucro_presumido", "lucro_real"],
    "nfse_emissoes.status": ["pending", "sent", "authorized", "rejected", "cancelled"],
    "nfse_eventos_cancelamento.status": ["pendente", "enviado", "autorizado", "rejeitado"],
    "nfse_provider_configs.ambiente": ["homologacao", "producao"],
    "transaction_documents.doc_type": ["nfe55", "nfce65", "nfse56", "nfcom62", "mdfe58", "cte57", "boleto_asaas", "boleto_inter"],
    "transaction_documents.status": ["pending", "authorized", "rejected", "cancelled"],
    "tax_rates.calculation_type": ["fixed", "percentage"],
    "tax_rates.rounding_type": ["up", "down", "normal"],
    "invoice_schemes.scheme_type": ["blank", "year"],
    "invoice_layouts.design": ["classic", "elegant"]
  }
}
```

### Nota de governança

`NfeBrasil` e `NFSe` continuam listados no débito `module-no-dict` do baseline (o guard
chaveia por nome de pasta `Modules/`); este dicionário LÓGICO cobre os dois — o débito zera
quando a consolidação dos módulos for decidida.
