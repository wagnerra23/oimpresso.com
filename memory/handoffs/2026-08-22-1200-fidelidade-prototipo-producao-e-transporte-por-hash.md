---
date: "2026-08-22"
time: "12:00"
slug: "fidelidade-prototipo-producao-e-transporte-por-hash"
tldr: "Pergunta do [W]: 'alguém reproduz fiel o protótipo em produção?'. Resposta medida: NÃO, e a indústria não mede isso — o baseline canônico de visual regression é o build anterior, não o mockup. Este repo já tinha chegado lá sozinho (ADR 0290 + lápide §5 17/07). O gargalo real não é fidelidade, é TRANSPORTE: 2 aplicações reais na vida, verificação por COMPRIMENTO e não por hash, e o fnv64 do produtor nunca é lido. Nada mergeado além de docs; 3 decisões abertas pro [W]."
decided_by: []
prs: [6137]
related_adrs:
  - 0290-fidelity-lock-v0-render-diff-recusado
  - 0282-protocolo-v2-colapso-ratificacao
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
---

# Handoff — fidelidade protótipo→produção, e o transporte que a sustenta

## Estado: PESQUISA FECHADA · 3 DECISÕES ABERTAS PRO [W]

Nenhuma mudança de comportamento foi mergeada. [PR #6137](https://github.com/wagnerra23/oimpresso.com/pull/6137)
está **em draft**, docs-only, 3 session logs + 1 commit de fix de frontmatter. CI verde,
incluindo o `Session log (memory/sessions/*.md)` que reprovou e foi consertado nesta sessão.

## Estado MCP no momento do fechamento

⚠️ **O MCP não estava conectado nesta sessão.** O hook de `SessionStart` reportou
`settings.local.json não encontrado e sem cofre local pra restaurar`, e nenhuma tool
`brief-fetch` / `my-work` / `cycles-active` / `whats-active` esteve disponível. Registro isso
como ausência de medição, não como estado saudável — a §5 de 2026-07-29 é explícita: instrumento
que não conseguiu medir não afirma verde.

**Fallback usado, com o que ele cobre e o que não cobre:**

| Pergunta | Oráculo usado | Limite |
|---|---|---|
| Sessão paralela ativa? | `mcp__github__list_pull_requests` (16 PRs abertos) | vê PR aberto, **não** vê sessão sem PR |
| Estado do meu PR | `pull_request_read` + `get_check_run` por id | ok |
| História / datas | API do GitHub (`list_commits`) | o clone é **shallow**; `git log` daria o piso do clone |
| Cobertura de tela | `design-coverage`, `pt-conformance`, `casos:report` rodados localmente | ok |

**Sessão paralela detectada:** [PR #6133](https://github.com/wagnerra23/oimpresso.com/pull/6133)
`claude/cowork-docs-resources` — *"concilia casos-financeiro — espelho fiel + medição + porta viva"*,
atualizado 2026-08-21T21:57Z. **Sem colisão de arquivo** com o #6137 (que só toca
`memory/sessions/2026-08-22-*`), mas **colisão de tema** no espelho/medição. Quem retomar o
applier confere o diff dela antes.

## A pergunta, e a resposta medida

**"Alguém consegue reproduzir fiel o protótipo em produção? Como?"**

Não. E o achado que reordena a pergunta: **a indústria não mede fidelidade ao mockup.** O
baseline canônico de visual regression (Chromatic, Percy, Argos, Playwright) é o **build
anterior** — o que essas ferramentas provam é *"mudou sem querer desde a última aprovação"*.
Comparar contra design existe só como plugin Figma em beta (Sauce Visual, Applitools), por pixel,
sem medição publicada.

O que os maduros medem no lugar é **conformidade ao sistema**: Pinterest (`design adoption`/FigStats),
Spotify (estatística diária de versão), Atlassian (adoption scanner próprio), Mews (adoção a
partir de produção). A Figma lê **detach rate** alto como *"o componente precisa de mais
flexibilidade"* — divergência como sinal sobre o sistema, não como infidelidade do implementador.

**A convergência que mais importa:** este repo já tinha chegado nessa resposta, por argumento
próprio e antes da pesquisa.
- [ADR 0290](../decisions/0290-fidelity-lock-v0-render-diff-recusado.md) — primeiro `recusado`
  canônico — matou render pareado prod×proto porque fica **verde quando os dois lados quebram**.
  É a mesma razão que a literatura externa dá.
- Lápide §5 de 2026-07-17 proíbe nota agregada de fidelidade porque *"os vereditos não são
  comensuráveis"*. É a mesma razão pela qual a **Productboard desistiu** de medir cobertura
  visual (*"telas raramente usam só componentes do design system"* — cada tela precisaria de
  threshold à mão), e a mesma pela qual o "20-40% de FP" que circula no mercado é folclore.

**O que a pesquisa acrescenta de fato:** fonte comum (token + componente) resolve **valor e
peça**, não resolve **composição** — Pinterest publicou tela com 85 componentes code-backed
adotados e **1% de design system na tela**. E terceirizar triagem para IA é filtro de *sinal*,
não de ruído: o melhor VLM acha 40,7% das mudanças visuais reais, <23% no tier difícil.

## Medido nesta sessão (rodando os comandos, não citando comentário)

| Porta / sonda | Resultado |
|---|---|
| `design-coverage` | 216 charters de página · **173 (80%)** com fonte declarada · 42 `.tsx` sem charter |
| `pt-conformance --json` | **91 declaram PT · 91/91 CONFORME · 0 mismatch** → 52,6% cabem no template |
| `pt-conformance --selftest` | **14/14**, inclui os casos MISMATCH — a porta **morde** |
| `transcribed-provenance.json` | **192 `NAO PROVADA` × 1 `POR CONSTRUCAO`** |
| espelho `prototipo-ui/cowork/` (265) | 117 verificados · 49 `NAO PROVADA` · **100 sem nem uma coisa nem outra** |
| `prototipo-ui/design-docs/` | **144 de 144 `NAO PROVADA`** |
| `render-proto-baseline --check` | **9 de 9 STALE**, e roda com `continue-on-error` |
| `visual-regression` | cobre **26 de 213 telas (12,2%)** |

Benchmark de escala: SAP Fiori Elements cobre ~**80%** dos apps do S/4HANA Cloud com ~5-6
floorplans gerados de metadata; aqui são **52,6%**, e a porta que mede isso é advisory.

## O gargalo real é transporte, não fidelidade

**Duas aplicações reais do applier na vida inteira**, ambas medidas contra o ledger e a API:
- **2026-08-17** ([PR #5854](https://github.com/wagnerra23/oimpresso.com/pull/5854)): 118 arquivos,
  3.504.544 bytes, `missing: []`. Dos 7 stale, **6 aplicados e 1 revertido conscientemente** — o
  `qa-conformance.js` do espelho era v2.5 e o vivo v2.4; aplicar teria apagado dois gates de a11y.
- **2026-08-18** ([PR #5915](https://github.com/wagnerra23/oimpresso.com/pull/5915)): 8 arquivos,
  incluindo bundle de 289.864 B que **[W] teve que fornecer à mão** porque o `get_file` cortou.

Depois disso, zero. **`--require-complete-shell` nunca rodou com dado real** — a flag nasceu em
`efc438f` (2026-08-18T14:37Z), **um dia depois** do único payload completo. As 4 menções fora do
código são `next_steps` pendentes.

**Causa medida:** `sync/payload.json` tem ~3,5 MB, o `get_file` corta em 256 KiB
(`truncated:true`), e o lado do design **nunca emitiu `payload-NN.json`** em partes.

## O que o transporte prova hoje — e o que ele não prova

Ele verifica **comprimento**, não conteúdo. Comprimento é ~100% em truncagem (a falha dominante
do teto), mas para as classes que preservam tamanho a probabilidade de escapar **é 1, não é
pequena**: substituição de bytes, reordenação, troca entre arquivos de mesmo tamanho.

**Quatro afirmações do docblock que a medição desmente** (família LC-10 — artefato afirmando mais
do que mediu; a engenharia está certa, a prosa é que envelheceu):

1. *"o `fnv64` é impresso como referência"* — **`f.fnv64` nunca é lido**. Medido: `rg -q 'f\.fnv64'`
   → `rc=1`; as duas menções no arquivo estão em comentário. O que se imprime é o `calc`, a
   recomputação **local**, truncada em 12 dos 16 hex.
2. *"o controle positivo provou que o errado era eu"* — aquele controle prova **bytes**. O controle
   do hash é outro, e foi rodado: a `fnv1a64()` local bate **5 de 5 vetores publicados**
   (`""`, `"a"`, `"b"`, `"c"`, `"foobar"`). Nossa implementação é canônica.
3. *"a convenção NÃO é reproduzível"* — conclusão tirada de 5 variantes que cobrem **um eixo só**
   (codificação), e o menos promissor: em ASCII, `charCodeAt` e bytes UTF-8 **coincidem**. Faltou
   o eixo do caso Git — `git hash-object` ≠ `sha1sum` porque o Git hasheia `blob <len>\0<conteúdo>`.
   Mesmo algoritmo, input diferente.
4. *"duas rotas concordando é evidência melhor que um hash opaco"* — 21 de 118 limita a taxa a
   **≤13,3%** (binomial) / **até 14 arquivos corrompidos** (hipergeométrica). E a amostra é
   **enviesada**: os 21 são exatamente os arquivos grandes que persistiram em disco, com poder
   **zero** sobre a rota inline — que é onde nasceu o STALE de 2026-08-11.

**A contradição que ninguém tinha nomeado:** `prototipo-ui/design-docs/sync/README.md:32`, escrito
pelo **lado do design**, declara *"`fnv1a-64` (16 hex) sobre o conteúdo UTF-8. **Mesma função nas
duas pontas**"*. O applier mediu 0/118. Como nossa implementação é canônica, uma das duas está
errada — ou o gerador não implementa o que documenta, ou normaliza antes de hashear.
**Indecidível daqui sem corpus**, e nenhum payload jamais foi versionado
(`search_code fnv64` → `total_count: 2`, ambos prosa).

**Custo, medido sobre 3,5 MB:** SHA-256 nativo **3,2 ms** × FNV-1a 64 em BigInt **285,4 ms** —
**89× mais lento**. O hash "barato" é o caro; qualquer objeção de custo a trocar está invertida.

**Hipótese testável que explica os dois fatos sem culpar ninguém:** `Buffer.from(f.content,'utf8')`
na linha 179 é sítio de corrupção **dentro do consumidor** — surrogate solitário vira `U+FFFD`,
3 bytes → 3 bytes, e a conferência de comprimento passa. Custa uma regex testar.

## Pendente — só [W] destrava

1. **Decidir o [#5757](https://github.com/wagnerra23/oimpresso.com/pull/5757)** (aberto desde
   2026-08-13). É o único item que segura entrega pronta: telas F3 com backend já mergeado.
2. **Perguntar ao lado do design:** o gerador normaliza o conteúdo antes do `fnv1a-64`? Ou —
   mais barato — emitir digest **autodescrito** (`sha-256=:base64:` do RFC 9530, ou `sha256-<hex>`
   estilo SRI). É a lição unânime de multihash/tus/SRI: *"hashes de mesmo comprimento são
   indistinguíveis sem contexto externo"*.
3. **Autorizar (ou não) 3 correções baratas no applier**, nenhuma delas decisão de arquitetura:
   corrigir o docblock (LC-10); varrer surrogate solitário antes do `Buffer.from`; ligar o
   detector de path duplicado em **todos** os modos (hoje só sob `--require-complete-shell`,
   que nunca rodou — e o modo do dia a dia funde lotes por `flatMap`, que é onde a desassociação
   path↔conteúdo aparece).

**Recomendação explícita de NÃO fazer:** construir instrumento de comparação protótipo×produção.
A 0290 já matou com argumento próprio e a pesquisa externa concorda pelas mesmas duas razões.

## Ressalva de método

O egress desta sessão bloqueia arXiv, ACM, Chromatic, Salesforce, Playwright. **Mas fonte primária
hospedada no GitHub abre** — tus, BEP 52, multihash e SMHasher foram lidos no original. Eu afirmei
antes, ao [W], que *"nenhuma fonte primária é alcançável daqui"*; **isso estava errado** e fica
registrado. Os achados de fonte bloqueada estão marcados como segunda mão nos 3 session logs e
precisam ser reabertos na fonte antes de virarem canon. Os números internos desta sessão **não**
têm essa ressalva — foram medidos aqui.

## Evolução deste tema

- **2026-08-13** — [#5757](https://github.com/wagnerra23/oimpresso.com/pull/5757) mede o teto do
  `get_file` (91% do acervo incumprível pela rota canônica) e escala 3 saídas ao [W]. Nenhuma ratificada.
- **2026-08-17** — única aplicação completa do applier: 118 arquivos. Origem dos números
  "118/118" e "21/21" que o docblock carrega até hoje.
- **2026-08-18** — nasce `--require-complete-shell`, um dia depois do payload que a teria exercitado.
- **2026-08-20** — 3 handoffs registram a mesma frase em `next_steps`: *"Design emitir
  `payload-NN.json` de até 256 KiB"*. Telas F3 bloqueadas.
- **2026-08-22 (este)** — a pergunta de fidelidade é respondida e sai do caminho; o gargalo é
  renomeado de "fidelidade" para "transporte"; 4 overclaims do docblock medidos; a contradição
  do `fnv1a-64` entre README do produtor e medição do consumidor é nomeada pela primeira vez.

## Session logs desta sessão

- [`2026-08-22-arte-fidelidade-prototipo-producao.md`](../sessions/2026-08-22-arte-fidelidade-prototipo-producao.md) — mercado e literatura
- [`2026-08-22-arte-escala-centenas-de-telas.md`](../sessions/2026-08-22-arte-escala-centenas-de-telas.md) — consistência em centenas de telas
- [`2026-08-22-arte-agentes-ia-ui-guardrails.md`](../sessions/2026-08-22-arte-agentes-ia-ui-guardrails.md) — agentes de IA gerando UI

Outros 5 eixos rodaram sem doc próprio e estão resumidos acima: inventário interno do transporte,
método de aferição, contrato de dados protótipo↔backend, registro real do transporte em lotes,
e integridade de transporte em chunks.
