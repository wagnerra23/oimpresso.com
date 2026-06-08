# ADR 0007 · Estrutura expandida: Glossário + Runbook + Diagramas + Contratos + Auditorias

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Complementa**: ADR 0004 (pasta-por-módulo), ADR 0005 (rastreabilidade tripla)

## Contexto

Estrutura atual (README + ARCHITECTURE + SPEC + CHANGELOG + adr/) cobre ~90% dos tipos de conhecimento, mas 5 categorias caem em "terra de ninguém" ou ficam misturadas no ARCHITECTURE:

1. **Glossário de termos** do módulo (ex.: o que é "intercorrência", "banco de horas", "REP-P")
2. **Runbook operacional** (troubleshooting, comandos emergência, onboarding de oncall)
3. **Diagramas** (ER do banco, fluxogramas, sequências) — hoje vira ASCII art dentro de ARCHITECTURE.md
4. **Contratos** de API (OpenAPI), eventos, schemas externos
5. **Auditorias periódicas** (relatórios de qualidade da documentação ao longo do tempo)

Além disso, **decisões transversais** (design system, convenções UI globais) não têm casa — hoje ficam espalhadas em ADRs de módulos isolados.

## Decisão

### Nova estrutura por módulo (tudo opcional)

```
memory/requisitos/{Modulo}/
├── README.md              ← porta de entrada (existente)
├── ARCHITECTURE.md        ← visão macro (existente)
├── SPEC.md                ← user stories + regras Gherkin (existente)
├── CHANGELOG.md           ← versão-a-versão (existente)
├── GLOSSARY.md            ← NOVO — termos específicos do domínio
├── RUNBOOK.md             ← NOVO — operação e troubleshooting
├── adr/                   ← existente
│   ├── arq/, ui/, tech/
├── contracts/             ← NOVO — OpenAPI, eventos, schemas
│   ├── api.yaml
│   └── events.md
├── diagrams/              ← NOVO — Mermaid (renderizado no markdown)
│   ├── er.md
│   └── flow.md
└── audits/                ← NOVO — relatórios periódicos
    └── YYYY-MM-DD.md
```

### Módulos virtuais cross-cutting

Para decisões que atravessam módulos:

```
memory/requisitos/
├── _DesignSystem/         ← tokens Tailwind 4, shadcn, dark mode, iconografia
├── _Glossary/             ← termos de domínio do negócio (CLT, REP-P, MEI…)
├── _Deploy/               ← runbook global de deploy/rollback
└── _Standards/            ← convenções de código, nomenclatura, i18n
```

Prefixo `_` mantém agrupamento visual separado dos módulos reais.

### Regras de escrita

- **GLOSSARY.md**: cabeçalho H2 é o termo, body é a definição. Ordem alfabética.
- **RUNBOOK.md**: seções por problema (`## Problema: DB lock na migração AFD`). Cada seção tem **Sintoma / Causa / Correção / Prevenção**.
- **contracts/api.yaml**: OpenAPI 3.1 (fonte canônica). Swagger UI pode renderizar.
- **contracts/events.md**: lista eventos Laravel/domain events que o módulo emite/consome.
- **diagrams/*.md**: markdown com bloco `mermaid` — renderizado automaticamente no viewer.
- **audits/YYYY-MM-DD.md**: gerado por `docvault:audit-module`, nunca manual.

## Consequências

**Positivas:**
- **Glossário** acaba com mal-entendidos ("banco de horas" em Ponto vs "saldo bancário" em Accounting).
- **Runbook** acelera resolução de incidentes — oncall acha a solução em minutos.
- **Diagramas versionados** em Mermaid (git diff faz sentido, ao contrário de .drawio binário).
- **Contratos** permitem geração de cliente SDK, validação de contratos, mock servers.
- **Auditorias** dão histórico de qualidade — dashboard mostra curva ao longo do tempo.
- **Cross-cutting decisions** ficam centralizadas (`_DesignSystem/`) — não duplicadas em 20 módulos.

**Negativas:**
- Escopo maior de escrita. Mitigação: tudo opcional, módulos pequenos continuam com 4 arquivos.
- Risco de criar arquivos vazios "só pra cumprir tabela". Mitigação: auditor detecta arquivos vazios/placeholder.

**Trade-off consciente**: fricção extra de estrutura em troca de clareza operacional. Pra sistemas com > 20 módulos e ciclo de vida longo, paga em oncall e onboarding.

## Alternativas consideradas

- **Manter tudo dentro de ARCHITECTURE.md**: rejeitado — fica arquivo de 2000 linhas impossível de scan.
- **1 arquivo `FULL.md` por módulo com tudo concatenado**: rejeitado pelos mesmos motivos do ADR 0004.
- **Wiki externa (Confluence/Notion/Docusaurus)**: rejeitado — contradiz ADR 0002 (file-based).
- **Storybook + MDX pra diagramas**: complementar pra UI, mas não cobre ER/fluxo/runbook.

## Plano de implementação

- **Commit 1** (este): ADR 0007 aceito + Reader lê os novos arquivos + Viewer tem tabs condicionais.
- **Commit 2**: Comando `docvault:audit-module {Nome}` com 15 checks + integração com Dashboard.
- **Commit 3**: Self-audit MemCofre + criar `_DesignSystem/` piloto com ADRs UI globais (tokens, shadcn, dark mode).
- **Futuro**: migração gradual dos módulos — cada um escolhe quais arquivos opcionais usar.
