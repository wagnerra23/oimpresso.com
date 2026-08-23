---
page: /documentacao/{slug}
component: resources/js/Pages/Documentacao/Doc.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-documentacao.md
owner: wagner
status: draft
last_validated: "2026-08-06"
parent_module: Documentacao
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
tier: B
charter_version: 2
---

# Page Charter — Documentacao/Doc (DRAFT · carimbado do PT-03)

> Migração Blade→Inertia (`doc.blade.php`) sob o contrato de paridade
> [ANTI-REGRESSAO-documentacao-blade.md](../../../../memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md).
> Golden do PT-03 ainda `draft` — o ciclo-completo não fecha antes de o Design terminá-lo.

## Mission

Abrir qualquer documento do acervo com o mesmo visual da leitura guiada, preservando os links
internos escritos a partir da pasta de origem do documento.

## Goals — Features (faz)

- Abre o documento do corpus pelo slug, com rail e lente ativa preservados
- Resolve link relativo a partir da **pasta do próprio documento**, não de `memory/` — o acervo tem doc em subpasta
- Marca no rail o item correspondente ao documento aberto
- Distingue os estados de falha: 503 sem corpus · 404 para slug inexistente ou de tipo fora da documentação
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

_Declarados por [W] em 2026-08-06._

- ❌ **Não troca a fonte de dados** — o conteúdo continua vindo do corpus sincronizado do git
- ❌ **Não converte markdown no cliente** — recebe HTML já convertido e sanitizado no servidor
- ❌ **Não gera manifesto de navegação commitado** — cópia da estrutura drifa (ADR 0256)
- ❌ **Não é tela de escrita** — read-only, sem painel de ação FSM (o arquétipo PT-03 traz um; removido de propósito)
- ❌ **Não constrói o gate automático de paridade da Onda 0d**
- ❌ **Não expõe documento de tipo fora da documentação** (session, handoff) — 404 é a resposta correta, não vazamento

## Automation Anti-hooks

- ❌ Nenhum agente amplia os tipos visíveis do acervo sem decisão de [W]

## UX Targets

- Cabe em 1280px sem scroll horizontal
- Documento longo continua legível com o rail fixo

## Refs

- Padrão de Tela: PT-03 Detalhe · Constituição UI v2: UI-0013
- RUNBOOK da superfície: [RUNBOOK-documentacao.md](../../../../memory/requisitos/Documentacao/RUNBOOK-documentacao.md)
- Contrato de paridade: AR-DOC-030 a AR-DOC-034 · AR-DOC-010 a AR-DOC-014
