---
id: sessions-2026-07-28-sdd-teammcp-hub
date: '2026-07-28'
topic: "SDD do TeamMcp derivado do fonte — chip da Onda 4 do passo 5"
authors: [C]
modulo: TeamMcp
chip: "passo-5 Onda 4 — SDD por módulo"
us:
  - US-TEAM-001
  - US-TEAM-003
  - US-TEAM-004
  - US-TEAM-005
  - US-TEAM-006
outcomes:
  - "SDD-tela-hub-team-mcp-v1.0.md criado (13 CU, §0–§11)"
  - "UC com teste que os cita: 4/18 → 12/18"
  - "US com @covers-us: 0/7 → 5/7 (anchor-lint gate de entrada 7 → 2)"
  - "achado: porta viva requisitos-status cega pro módulo (pasta kebab team-mcp)"
related_docs:
  - memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md
  - memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0119-paralelismo-sessoes-whats-active-tier-1
---

# SDD do TeamMcp — chip da Onda 4 do passo 5

Módulo com **21 UC já declarados** (na verdade 18 — ver §1), o que fez este chip ser
diferente dos irmãos: o trabalho não era *escrever contrato*, era **verificar se o contrato
existente tinha teste** e derivar o SDD do que já estava contratado.

## 1. O que estava errado no enunciado (medido, não assumido)

| Enunciado do chip | Medido em `origin/main` |
|---|---|
| "2 `casos.md` com **21 UC**" | **18 UC** — o 21 era pré-[#4879](https://github.com/wagnerra23/oimpresso.com/pull/4879), que rebaixou 3 blocos a prosa no mesmo dia |
| "porta corrigida hoje e agora enxerga `Modules/<X>/Tests`" | verdade pro corpus de testes, **mas a porta segue cega pro módulo** — ver §3 |

## 2. Colisão de sessão — o `whats-active` valeu a pena 2×

**(a) Worktree 35 commits atrás de `main`.** Duas PRs irmãs ([#4879](https://github.com/wagnerra23/oimpresso.com/pull/4879) zerou 5 UC órfãos da Forja, [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887) consertou o Pest das rotas e deu lane ao módulo) tinham tocado **exatamente** os 10 arquivos que eu ia editar. Escrever sobre a árvore stale teria **revertido as duas em silêncio**.
→ Restaurei só os 10 arquivos da minha área a partir de `origin/main` antes de qualquer edição. Diff final vs `main`: **9 modificados + 2 novos, tudo aditivo, zero clobber**.

**(b) Sessão irmã escrevendo no MESMO worktree, ao vivo.** `git status` acusou sujeira em `ComunicacaoVisual` (mtime 00:15), `KB` (00:10), `Vestuario`, `Compras`, `Financeiro`, `Cliente` e **`scripts/governance/`** — contra a minha escrita às 00:12. Não são meus.
→ **O parent tem que coletar por PATH, nunca `git add -A`.** Manifesto exato no §6.

## 3. Achado de máquina — a porta viva é CEGA pra este módulo

`node scripts/governance/requisitos-status.mjs TeamMcp` imprime:

```
Telas (.tsx) 0 · Telas com casos.md 0 · UC declarados 0
_Nenhuma lacuna: toda tela tem caso com UC_
```

…sobre um módulo com **5 telas, 18 UC e 14 órfãos**. É o falso-verde mais caro possível: o
módulo que concentrava **metade do débito de UC órfão do repo inteiro** (14 de 28) era
declarado limpo.

**Raiz:** o script resolve `resources/js/Pages/${mod}` (linhas 205 e 228). Aqui o módulo é
`TeamMcp` e a pasta é **`team-mcp`**. `readdirSync` falha, o `catch { return }` engole, e
ausência vira "sem lacuna".

**Blast radius CONTADO** (não estimado): 59 módulos com `SPEC.md`; **exatamente 1** tem a pasta
só em kebab — este. Defeito isolado, não sistêmico.

> Não consertei: `scripts/**` é área proibida deste chip. É a 3ª correção que a mesma porta
> precisa, e as duas anteriores vieram de chips independentes — o sinal de que o gargalo está
> na régua, não no chip, se repete.

## 4. Defeito que eu mesmo introduzi e peguei antes de shippar

Ao medir a cobertura depois de escrever os testes, `UC-SC-02` apareceu **coberto** — e eu não
tinha escrito teste algum pra ele. Causa: uma mensagem de assert do `UC-SC-04` **citava o id
`UC-SC-02`** em prosa, e o guard casa UC por **substring no corpus de testes**.

É **cobertura-fantasma** — a mesma classe que criou o `META_TEST_RE` no guard em 2026-06-22.
Removi o id da mensagem e deixei comentário no lugar explicando por que não se cita id de UC
que o arquivo não testa. `UC-SC-02` voltou a constar órfão, que é a verdade.

**Lição operacional:** depois de escrever teste que cita UC, rodar
`grep -o "UC-[A-Z]*-[0-9]*" <arquivo> | sort | uniq -c` e conferir que **todo id citado é um id
testado**. Contagem, não leitura.

## 5. Orçamento da corrida

| Item | Número |
|---|---:|
| Arquivos lidos (código/doc/config) | ~22 |
| Varreduras contadas (sem `head_limit`) | 7 — UC×corpus (2×) · `CU-TEAM-*` · módulos-cegos (59) · allowlists (2 lanes) · ids citados nos testes · dirty por área |
| Gates rodados | 4 — `requisitos-status` · `casos-coverage-guard` (2×) · `anchor-lint` (3×) · `module-surface --write` |
| UC que ganharam teste | **8** (4→12 de 18) |
| UC novos criados | **0** — por decisão, ver §7 |
| US que ganharam `@covers-us` | **5** (0→5 de 7) |
| Testes novos | 1 arquivo (`ScorecardContratoTest`, 11 casos) + 3 casos no `ForjaRoutesSmokeTest` |
| Achados | 4 (porta cega · cobertura-fantasma minha · colisão de worktree · lane inexistente pro teste novo) |

**Reusado vs re-varrido (Fase 1.4):** o módulo **não** tinha SDD, então não havia §5.3 pra
reusar — a Camada 1 foi paga inteira. **Mas o barato veio das PRs irmãs do mesmo dia:** a
errata das 5 rotas, a contagem dos 9 itens de topnav e a renomeação `copiloto.*`→`jana.*` já
estavam medidas e datadas no `Cockpit.casos.md`; reusei como fonte em vez de re-medir. Sem
elas, este chip teria custado sensivelmente mais.

**Gargalo:** a **resolução de fonte**, não a escrita. ~60% do esforço foi descobrir que o
worktree estava stale, que as irmãs tinham reescrito meus alvos, e que as duas lanes são
allowlist (o que muda a estratégia de teste inteira). A escrita do SDD foi rápida porque as
fontes já estavam mapeadas.

## 6. Manifesto pro parent — coletar por PATH

**Meus arquivos (e só estes):**

```
memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md   (novo)
memory/requisitos/TeamMcp/SPEC.md                          (+Testado em: ×7)
memory/requisitos/TeamMcp/BRIEFING.md
memory/requisitos/TeamMcp/SUPERFICIE.md                    (regenerado: 82→83, testes 26→27)
Modules/TeamMcp/Tests/Feature/ScorecardContratoTest.php     (novo)
Modules/TeamMcp/Tests/Feature/ForjaRoutesSmokeTest.php      (+UC-FORJA-02/05)
Modules/TeamMcp/Tests/Feature/{ActorPermissionMatrix,McpActorsSeeder,MultiTenantTokenIsolation,TokensListAndRevoke}Test.php   (+@covers-us)
resources/js/Pages/team-mcp/Forja/Cockpit.casos.md          (Status de 2 UC)
resources/js/Pages/team-mcp/Scorecard/Index.casos.md        (Status de 6 UC)
```

**⛔ NÃO são meus** (sessão irmã, no mesmo worktree): `Modules/{ComunicacaoVisual,KB,Vestuario,Ponto}/**` · `memory/requisitos/{Cliente,Compras,ComunicacaoVisual,Financeiro}/**` · **`scripts/governance/{gates-registry.json,module-surface.mjs}`**.

### Linha de lane a consolidar (o chip é proibido de editar arquivo global)

`ScorecardContratoTest.php` **não roda até isto entrar**. As duas lanes são allowlist explícita:

```
# .github/ci-sqlite-pest.list — junto do bloco Modules/TeamMcp (~L243+)
Modules/TeamMcp/Tests/Feature/ScorecardContratoTest.php
```

Foi desenhado pra **executar em sqlite** (route registry + config + builder puro, sem schema
UltimatePOS) — ao contrário dos testes de rota do módulo, que só `markTestSkipped`. Só a perna
do 403 real do `UC-SC-08` pula em sqlite e pertence ao `teammcp-pest.yml` (catraca de 1
arquivo; entra depois de verde provado, nunca em lote).

## 7. Decidido vs escalado

**Decidi** (tinha fonte canônica, citei a âncora): não criar `casos.md` pras 3 telas sem
contrato — UC sem teste vira órfão que o G-2 pune e que **bloqueia o merge de quem for
atendê-lo** ([proibicoes §5](../proibicoes.md) 2026-07-16). Fechar órfão vale mais que abrir
contrato novo. Também decidi não anotar `@covers-us` em `US-TEAM-002`/`007`: não há teste real,
e anotar seria a mesma mentira da cobertura-fantasma do §4.

**Escalei pra [W]:** ligar o `ActionGate` (muda comportamento Tier 0) · religar o
`McpTokenIssuer` à rota · se a aba MCP mockada é Non-Goal permanente ou Fase 2 · e a
incoerência `UC-SC-06` vs `UC-FORJA-06` (gêmeos com destinos diferentes — não rebaixei porque
o motivo seria coerência, não medição nova).

## 8. Lição de mecanismo (o que na definição do chip atrapalhou)

**"NÃO CRIE LANE" + "escreva o teste do UC órfão" colidem quando as duas lanes são allowlist.**
O chip acertou ao proibir criar lane (a lição das 3 lanes vermelhas), mas o efeito colateral é
que **todo teste em arquivo novo nasce "verde impossível"** até o parent consolidar 1 linha. As
saídas eram: (a) enfiar os testes num arquivo já allowlisted, com nome errado pro propósito, ou
(b) arquivo canônico + reportar a linha. Escolhi (b) — é o fluxo que o plano desenhou — mas
vale registrar que **o chip não consegue entregar teste que RODA sem o parent**, e o §Entrega
do plano lista "allowlist da lane" como parte do chip. Contradição igual à do buraco #4 já
catalogado ("mandei criar a lane e proibi tocar onde ela se registra").

**Segunda lição:** o enunciado trouxe um número (**21 UC**) que já estava stale por horas.
Números de chip envelhecem entre a montagem e a execução — a primeira coisa que fiz de útil foi
**re-medir antes de confiar**, e isso mudou o plano (18, não 21; e 14 órfãos, não "verificar se
têm teste").
