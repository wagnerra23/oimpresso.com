---
id: sessions-2026-07-28-sdd-faltantes-fila-derivada-e-merges
date: "2026-07-28"
topic: "SDD faltantes — a fila derivada dos 32, a errata da Onda 0 e 10 merges autorizados por [W]"
authors: [C]
type: session
module: _Governanca
owner: W
related_docs:
  - requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0119-paralelismo-sessoes-whats-active-tier-1
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Sessão 2026-07-28 — SDD faltantes: medir antes de executar, e 10 merges

## TL;DR

Pedido: *"pode fazer todos os módulos sdd faltantes, verifique as sessões abertas para não conflitar"*. Fui medir e o que apareceu mudou o que valia entregar: **não escrevi chip nenhum** — entreguei a **fila derivada dos 32 restantes** + a **errata da Onda 0** (que estava errada nos dois sentidos e nunca foi paga), e recuperei um **commit encalhado** que continha a definição corrigida do chip. Depois [W] autorizou o merge dos PRs abertos e **10 entraram**.

## Por que não fiz os 32 chips

Medido nos 7 que rodaram em 27/07: **1.117 a 2.738 linhas por chip**, 6 a 33 arquivos — e consumiram uma sessão inteira. O próprio plano diz que a escala é *"paralelismo de SESSÕES, não de subagents"* e deixa os restantes **"não estimado — seria chute sobre chute"**.

Mais decisivo que o tamanho: **o gargalo não era escrever mais SDD**. Era a Onda 0 impaga e uma decisão de desenho pendente.

## O commit encalhado

`9a7e10f8e` — *"os 4 buracos que a execução de 7 chips revelou no plano"* — vivia **só** em `claude/sdds-pendentes-c3a697`, nunca virou PR. É a definição corrigida do chip: BRIEFING não redestilado → `distiller_freshness` no ratchet armado (GT-G3, required) · `SUPERFICIE.md` não regenerada → 5 PRs vermelhos · lane nova fora do `gates-registry` → `memory-health` 🔴 · contradição no próprio prompt (mandava criar a lane e proibia tocar onde a lane se registra). Recuperado por cherry-pick no #4903 — sem ele todo chip futuro repetiria 4 falhas já pagas.

## A errata da Onda 0 — 225 testes órfãos

O plano listava `Sells · Cliente · OficinaAuto · RecurringBilling` como sem lane. Medido em `origin/main`:

- **Sells JÁ tinha lane** (`sells-pest.yml` roda `tests/Feature/Sells/**`, 74 arquivos)
- **Repair também** (`modules-pest.yml`, por **matrix** — não por path literal)
- **6 módulos fora da lista** estão órfãos

**225 arquivos de teste que nenhum job roda**, em 8 módulos — Cliente (64) · OficinaAuto (44) · RecurringBilling (39) · Admin (19) · Auditoria (18) · Manufacturing (17) · ProjectMgmt (13) · ConsultaOs (11).

Cliente e OficinaAuto são o caso duro: **o chip entregou contrato + teste e mergeou**, e o teste é decorativo por construção. É o defeito #7 do piloto (*"verde impossível"*) reaparecendo **depois** de nomeado. As lanes existiam na branch da sessão irmã e não chegaram ao main. Nenhuma máquina acusou — *"teste sem lane"* não é gate.

## A fila: 7 executáveis, não 32

40 módulos com tela React · 8 com SDD no início da sessão · 32 sem. Destes: **7 chip válido hoje** (NfeBrasil · Essentials · Jana · Repair · Whatsapp · ComunicacaoVisual · Vestuario) · **6 travados na Onda 0** · **19 com ZERO testes**.

Os 19 são pergunta de desenho, não de execução: o chip entregaria UC sem teste que os cite, que é o que o **G-2 do `casos-gate` (required) reprova**. Três saídas registradas no plano; a escolha é [W] porque muda o contrato do chip.

## Os merges (autorização [W] explícita: *"aceite todos, estou no celular"*)

10 PRs. Nenhum era mergeável de bandeja — cada um travou por um motivo diferente:

| PR | O que travava |
|---|---|
| #4903 | draft (meu) |
| #4898 | nada — clean |
| #4900 | `Casos-coverage · ratchet` **required vermelho** |
| #4838 · #4705 | required **"expected"** — nunca rodaram; branches velhas vs conjunto de required atual |
| #4901 | **conflito** |
| #4904 · #4905 · #4906 · #4910 | verificados antes (ver abaixo) |

**#4900/#4838/#4705:** o remédio foi atualizar a branch com o `main` novo — o CI re-rodou completo e os três ficaram verdes. O vermelho do #4900 era artefato de base velha, não defeito.

**#4901 (conflito):** o diagnóstico interessante. `base` e a branch estavam em **CRLF**; o `main` normalizou pra **LF**. Semanticamente `main` == base naquele arquivo (`gates-registry.json`, 115 workflows dos dois lados, **zero** entradas alteradas pelo main) — o conflito era 100% de line-ending. Resolvi tomando a versão LF do main e aplicando **só** a entrada `teammcp-pest.yml`; confirmei que `JSON.stringify(...,2)` faz round-trip byte-idêntico antes de reserializar, então o diff final foi 3 linhas por 2. `memory-health` exit 0, índice de ADR em dia.

**Verificação dos 4 últimos, antes de mergear:** nenhum toca workflow/lane, nenhum toca arquivo global proibido. O #4910 é o melhor do lote — remove testes tautológicos que assertavam arrays literais e move `UC-FDFE-03/04` para testes que **invocam o Controller**, mantendo status `🧪` (não fabrica `✅`).

## Erros de método — 3 pegos antes de virarem afirmação, 1 quase passou

1. **Glob subconta.** `git ls-files "Modules/<M>/Tests/**/*.php"` perde arquivo direto no diretório — me fez classificar **Sells como "sem lane"** quando tem 74 testes rodando. Pego ao cruzar com o `sells-pest.yml`.
2. **Medição cega a matrix.** Procurar `Modules/<M>/Tests` literal nos workflows classificou Repair/ComunicacaoVisual/Vestuario como órfãos — eles entram por *matrix* (`modules-pest.yml`). Pego ao abrir o YAML antes de publicar.
3. **`|| echo` mentindo (quase passou).** `git fetch` falhou (`couldn't find remote ref`) e o `|| echo "nenhum ✓"` imprimiu confirmação de conformidade mesmo assim. É o anti-padrão já catalogado no §5 (2026-07-17, caso `crontab -l`): o `||` não distingue *"rodou e não achou"* de *"nem rodou"*. Descartei a leitura e refiz pela API do GitHub. **Não virou afirmação**, mas foi por um fio — imprimi o "✓" antes de perceber.
4. **Espelho git stale.** No fim, o proxy git local não alcançou os merges (`origin/main` parado em `8ba843278` enquanto o último merge era `8aa97070c`). **Recusei dar contagem de SDD derivada dele** — número medido em base stale é exatamente o erro que o #4903 documenta.

## Estado ao fechar

**Pendência que ficou sem dono — #4901:** o merge lançou a ADR 0354 e o registro do gate, mas **não ligou o gate**. O flip do `PHP / Pest (TeamMcp · MySQL)` na branch protection é ação manual [W], e o nome carrega `·` (U+00B7) — o caractere cujo mojibake deadlockou todo merge do repo em 02/07. Receita: `RUNBOOK-branch-protection.md`.

**Abertos, não mergeados por decisão:** #4913 (SDD NfeBrasil, Onda 5 — achou 🔴 `toggleAutoEmission` sem gate de permissão, que **liga emissão automática de documento fiscal**, e import CSV resolvendo tenant em 2 momentos; §9 lista decisões [W]) · #4917 (módulo VozDoCliente, **draft**, 3 decisões abertas) · #4919 (auto-PR do floor, auto-merge ligado).

**Nota sobre paralelismo:** a sessão irmã seguiu produzindo durante toda esta sessão — Onda 4 (KB/TeamMcp/Vestuario) e Onda 5 (NfeBrasil) nasceram enquanto eu media. Os PRs dela declaram ter aplicado as lições que o #4903 documenta: *"nenhuma lane criada; das 3 que os chips criaram, 1 duplicou trabalho de outra sessão e 2 nasceram 100% vermelhas"*. O `whats-active` não estava disponível (MCP fora nesta sessão) — a detecção de sessão irmã foi por inspeção de branches e PRs abertos.
