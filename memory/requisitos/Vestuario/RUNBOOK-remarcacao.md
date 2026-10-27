---
last_validated: "2026-10-27"
slug: vestuario-runbook-remarcacao
title: "RUNBOOK — Remarcação por coleção (US-VEST-032)"
type: runbook
authority: canonical
lifecycle: ativo
owner: W
last_updated: "2026-10-27"
pii: false
related_us: [US-VEST-032]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0121-oimpresso-modular-especializado-por-vertical
---

# RUNBOOK — Remarcação por coleção (US-VEST-032)

> Processo MWART F1 PLAN ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Tela nova (não há Blade a migrar). Cliente piloto: ROTA LIVRE biz=4.

## Contexto

Na troca de estação a loja remarca dezenas de peças de uma vez. Hoje é peça a peça no cadastro.
Decisão [W] 2026-10-27 (SPEC US-VEST-032): filtro → percentual ou valor → prévia → gravar → reverter,
e as etiquetas novas saem para impressão.

## Fonte de design

Protótipo `prototipo-ui/cowork/Wagner/vestuario-remarcacao-page.jsx` (ADR 0438), registrado em
`window.PROTO_HTML["vest-remarcacao"]` e aberto dentro do shell do protótipo (rota `vest-remarcacao`,
ghost "Remarcação por coleção" em Vestuário, PR #10055). Para mudar a tela, edite esse `.jsx`.

## Rotas

| Método | Rota | O quê |
|---|---|---|
| GET | `/vestuario/remarcacao` | tela |
| POST | `/vestuario/remarcacao/previa` | antes → depois, não grava |
| POST | `/vestuario/remarcacao` | grava (exige a assinatura da prévia) |
| POST | `/vestuario/remarcacao/{lote}/reverter` | devolve o preço de antes |
| GET | `/vestuario/remarcacao/{lote}/etiquetas` | itens para `POST /vestuario/etiquetas/lote/pdf` |

## Ligar para uma empresa

Desligada por padrão (404). Liga pelo setting do Vestuário da empresa:

```bash
php artisan vestuario:settings set --business=<id> --key=remarcacao.enabled --value=true --type=bool
```

Permissão: `product.update`.

## Multi-tenant Tier 0

Serviço `Remarcacao` filtra produtos, lotes e reversão pelo `business_id` da sessão; teste
`RemarcacaoContratoTest` (tenant 98 × 99) prova. Nunca smoke na biz=4.

## Regra mestre de valor

Prévia antes → depois obrigatória; gravar só com a prévia em dia; o antes fica guardado por variação;
reverter não pisa em preço mexido depois. Tabela de dupla confirmação no PR da fatia de serviço (#9968).

## Smoke (F4)

Ligar o setting no biz=1, abrir `/vestuario/remarcacao`, filtrar por um nome de teste, prévia, gravar,
imprimir etiquetas, reverter; screenshot. Desligar depois.

## Aviso cliente (F5)

Ligar na ROTA LIVRE só com aviso à Larissa; é tela nova, não substitui nada.

## Referências

- SPEC: [US-VEST-032](SPEC.md) · Contrato: [`_telas/remarcacao.casos.md`](_telas/remarcacao.casos.md)
