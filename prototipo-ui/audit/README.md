# Worklist de auditoria paralela — frente paralela-segura (read-only)

> **Origem:** Cowork [CC] 2026-05-31 ("feature A") + dossier [`memory/sessions/2026-05-30-arte-task-system-cowork-code.md`](../../memory/sessions/2026-05-30-arte-task-system-cowork-code.md).
> **Princípio-chave (Wagner, NÃO violar):** tela não fecha por OPINIÃO. Fecha por **EVIDÊNCIA OBJETIVA** reproduzível (regra mecanizada = 0 violações, `ds/*` = 0, print que bate golden). O agente CONFERE contra evidência — não narra.
> **Status:** scaffold (Code [CL]). GOLDEN-REFERENCE = draft a reconciliar com a cópia do Cowork.

## O que é

N agentes do Code rodam **read-only** (não tocam `Modules/*` nem `resources/js`), pontuam **cada tela** contra as **10 regras da [GOLDEN-REFERENCE](GOLDEN-REFERENCE.md) + `ds/*`**, e cospem **1 `design-report.json` por tela** em `reports/`. Zero colisão (1 agente = 1 tela = 1 arquivo). Um consolidador determinístico ([`consolidate.mjs`](consolidate.mjs)) junta tudo num **placar único** que estende o [`DS_ADOCAO_INDICE.md`](../DS_ADOCAO_INDICE.md).

É a **frente paralela-segura** porque é toda read-only + append-de-arquivo-próprio: pode rodar em paralelo com qualquer implementação sem race.

## Por que não duplica o board 2026-05-30

O [`SCREEN-GRADE-BOARD-2026-05-30`](../../memory/governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) foi um run **one-off** (19 agentes, 1 JSON global + 1 MD). Esta worklist é a **versão repetível e mecanizada**:
- saída **por tela** (`design-report.json`), não 1 blob global → cada PR-C re-pontua só a tela que tocou;
- regra **pass/fail mecanizada** (regex/ESLint) separada do julgamento LLM (`mechanized: true/false`) → evidência ≠ opinião (anti-"Gaming the Judge");
- `measured_against_sha` em cada report → anti-stale (sabe-se contra qual HEAD foi medido).

## Como roda (5 passos)

1. **DISPATCH** *(Cowork ou Wagner)* — gera a lista de telas (glob `resources/js/Pages/**/*.tsx`) e dispara N agentes read-only, 1 lote de telas por agente.
2. **SCORE** *(cada agente)* — pra cada tela: roda os checks mecanizados (ver GOLDEN-REFERENCE §Detecção) + julga as regras não-mecanizáveis, e **escreve `reports/<slug>.design-report.json`** seguindo [`design-report.schema.json`](design-report.schema.json). NÃO edita nenhum outro arquivo.
3. **CONSOLIDATE** *(Code)* — `node prototipo-ui/audit/consolidate.mjs` → gera [`CONSOLIDADO.md`](CONSOLIDADO.md) (placar worst-first) + `CONSOLIDADO.json` (rollup).
4. **EXTEND** — o placar vira a dimensão "Adoção DS / Pre-Flight" no `DS_ADOCAO_INDICE.md` (link, não cópia).
5. **CLOSE-BY-EVIDENCE** — uma tela "sobe" só quando um novo `design-report.json` (medido contra HEAD mais novo) mostra a regra zerada. Ratchet: nota só sobe ([ADR 0236](../../memory/decisions/0236-screen-grade-ratchet.md)).

## Regra de ouro da paralelização (zero colisão)

- **1 agente escreve SÓ os arquivos das telas do seu lote**, com nome `reports/<slug>.design-report.json` onde `<slug>` = caminho da tela com `/`→`__` (ex `NfeBrasil/Transactions/NfceStatus` → `NfeBrasil__Transactions__NfceStatus.design-report.json`).
- Nenhum agente toca `CONSOLIDADO.*` (só o consolidador, depois, single-threaded).
- Nenhum agente toca `Modules/`, `resources/`, migrations, rotas. **Read-only.** Viola = descartar o run.

## Prompt do agente-scorer (template — o "1 prompt" que o Cowork gera)

```
Você é um auditor de UI READ-ONLY. NÃO edite nenhum arquivo de produção.
Telas do seu lote: <LISTA DE PATHS .tsx>
Para CADA tela:
  1. Leia o .tsx (e o .charter.md ao lado, se houver).
  2. Rode os 10 checks da prototipo-ui/audit/GOLDEN-REFERENCE.md. Para os mecanizados,
     baseie-se na evidência textual exata (cite o trecho). Para os julgados, marque mechanized:false.
  3. Puxe a contagem ds/* do módulo (se disponível em config/eslint-baseline.json).
  4. Escreva prototipo-ui/audit/reports/<slug>.design-report.json conforme o schema.
     measured_against_sha = HEAD atual (git rev-parse --short HEAD).
NÃO escreva mais nada. NÃO consolide. 1 arquivo por tela.
```

## Arquivos

| Arquivo | Papel |
|---|---|
| [`GOLDEN-REFERENCE.md`](GOLDEN-REFERENCE.md) | As 10 regras + `ds/*` · fonte canon de cada uma · método de detecção · peso |
| [`design-report.schema.json`](design-report.schema.json) | Contrato do `design-report.json` por tela |
| `reports/*.design-report.json` | 1 por tela (escrito pelos agentes) — append do próprio arquivo, zero colisão |
| [`consolidate.mjs`](consolidate.mjs) | Determinístico: lê `reports/` → `CONSOLIDADO.md` + `CONSOLIDADO.json` |
| `CONSOLIDADO.md` / `.json` | Placar único (gerado — nunca editado à mão) |

## Pendências (Wagner decide)

1. **Reconciliar GOLDEN-REFERENCE** com as "10 regras" da cópia do Cowork (COWORK_NOTES → Pendentes, fora do git).
2. **Disparar o run completo** (222 telas) — gated em "avisa antes" (Cowork/Wagner). O scaffold + pilot de 2 telas já validam o contrato.
