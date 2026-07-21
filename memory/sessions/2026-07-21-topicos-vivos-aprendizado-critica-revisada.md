---
date: "2026-07-21"
hour: "10:47 BRT"
topic: "Tópicos vivos, aprendizado por crítica revisada e correção tela versus componente"
authors: [W, C]
outcomes:
  - "ADR 0345 aceitou BRIEFING como índice, tópico como unidade estável e crítica IA com síntese central mais aprovação humana."
  - "Schema/template de tópico e piloto Produto/calculo-total-fatura foram implementados em grace forward-only."
  - "SUPERFICIE deixou de contar componentes co-localizados como telas; Financeiro passou de 60 para 21 telas reais."
prs: [4617]
us: []
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# Session log 2026-07-21 — tópicos vivos e aprendizado revisável

## TL;DR

Wagner aprovou transformar em PR a taxonomia “um tema por arquivo, BRIEFING como resumo/índice” e pediu validar como o sistema aprende. A implementação separou treinamento do modelo de memória organizacional, criou o loop crítico IA → síntese central → aprovação humana → canon Git e corrigiu o catálogo que confundia componentes React com telas.

## Contexto

A auditoria anterior encontrou que `module-surface.mjs` classificava todo `.tsx` em `Pages/` como tela e que o scorecard de função tinha um gabarito circular: declarava `calculateInvoiceTotal()` defeituosa em C2 embora existisse golden protegendo o comportamento atual de `num_uf()`.

## Entregas

- `scripts/memory-schemas/topico.schema.json` + `TOPICO-TEMPLATE.md` — contrato forward-only.
- `memory/decisions/0345-*` — arquitetura aceita por [W].
- `memory/requisitos/Produto/topicos/calculo-total-fatura.md` — piloto com veredito composto e `incerto` onde faltou prova.
- `scripts/qa/page-path.mjs` — fonte compartilhada de tela executável versus auxiliar.
- scorecard `ProductUtil` do PR #4616 — `validation_status` rebaixado para `invalidado`: T2 circular, C2 corrigido pelo golden e C8 5→1 teste direto.
- sete `SUPERFICIE.md` regeneradas; Repair já estava sem drift.
- método/skill de função corrigidos para `incerto`, intenção externa e escopo por risco.
- template de BRIEFING reduzido a índice; nenhum BRIEFING legado foi migrado em massa.

## Validações

- `node --test scripts/governance/module-surface.test.mjs scripts/qa/page-path.test.mjs` — 12/12.
- `node scripts/qa/screen-coverage-map.mjs` — 235 telas, 235 charters; Financeiro 21/21.
- `node scripts/governance/module-surface.mjs --all --check` — oito módulos opt-in sem drift.
- AJV 2020 + `ajv-formats` + `gray-matter` — tópico piloto válido.
- `git diff --check` — sem erro.
- Primeira rodada do CI: `adr-index --check` e anti-ghost morderam; regenerado `_INDEX-GENERATED.md` e removida da fonte gerada a citação literal ao diretório modular inexistente de Sells. Reexecução local: ambos verdes.
- Pest/PHPStan não rodaram localmente; regra CT 100 preservada.

## Decisões cinzentas resolvidas

| Pergunta | Decisão | Justificativa |
|---|---|---|
| Toda IA pode criticar? | Sim, como proposta com evidência | Diversidade aumenta descoberta sem conceder autoridade canônica. |
| IA central decide sozinha? | Não; sintetiza e preserva divergências | Arquitetura/produto exige aprovação humana. |
| Isso treina o modelo? | Não altera pesos; treina o sistema organizacional | Git, testes, ADRs e tópicos sobrevivem entre modelos/sessões. |
| Toda função ganha parecer? | Não; somente risco relevante | Censo de triviais gera ruído e carimbo. |
| Migrar todos os BRIEFINGs agora? | Não; forward-only e oportunístico | Backfill em massa é abordagem já reprovada pelos gates diff-aware. |

## Aprendizados / pegadinhas

- Código é evidência de comportamento, não de intenção. Sem SPEC/charter/ADR/dono/golden/runtime suficiente, o veredito correto é `incerto`.
- “Defeito plantado” não pode ser declarado sobre código de produção usado para provar o próprio juiz. O bite-test precisa de fixture sintética e controle limpo.
- Concordância entre três juízes que compartilham rubrica/gabarito mede repetibilidade, não independência nem verdade.
- Um índice gerado só é útil se compartilha a mesma definição de tela com a ferramenta de cobertura.
- Texto que diz “este path não existe” ainda é uma citação de path para um detector sintático; a fonte gerada deve expressar a ausência sem fabricar ghost literal.

## Próximos passos

- [ ] Medir o piloto por algumas PRs antes de promover `topico` de grace para required.
- [ ] Construir resolvedor reclamação → módulo → tópico → tela/rota/controller/função/model/teste, expondo ambiguidades.

## Referências

- PR: [#4617](https://github.com/wagnerra23/oimpresso.com/pull/4617)
- ADR 0345: `memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md`
- Handoff: `memory/handoffs/2026-07-21-1047-topicos-vivos-aprendizado-critica.md`
