# PROCESSO_MEMÓRIA_CC — a raiz do meu método (anti-regressão)

> **LER no início de todo chat, junto com STATUS.md.** Este arquivo define COMO a memória de design evolui sem regredir. Ele é a raiz: o Charter, o Register e a ponte pro git são instâncias deste processo.
>
> **Lei suprema deste arquivo:** _REGRESSÃO É INACEITÁVEL._ Nada que foi testado e reprovado volta sem passar pelo registro abaixo. Nada que foi aprovado é desfeito por esquecimento.

---

## ★ NÚCLEO — se ler só isto, já obedece (13 invariantes)

> Síntese estado-da-arte. O resto do arquivo é **detalhe sob demanda**. Isto é o always-read.

1. **Espinha sempre lida:** STATUS + este NÚCLEO + LICOES + o `charter` da tela em foco — antes de propor/mexer. _(🔁 Emenda Onda A #2874: a **autoridade** da espinha é o **git/SSOT** — ADR 0238/0239; STATUS/MEMORY_INDEX seguem always-read mas como **cache derivado**, não autoridade. Ver §13.3 + §17.)_
2. **3 planos:** Sistema (DS/tokens) ⊃ Tela (`charter` lei · `decisoes` debate · `casos` contrato) ⊃ Processo. Camada de cima herda e **nunca contradiz**.
3. **Anéis, fonte única:** 🔍Avaliar→🧪Testar→✅Adotar→⛔Descartar. Adotar→`charter`+`casos`; Descartar→anti-pattern+L-NN. Cada decisão num lugar autoritativo só.
4. **Casos = contrato de não-regressão.** Mudou a tela → roda os casos. Quebrou um ✅ → **PARA, revisa o pensamento, não força** (conserto silencioso proibido).
5. **Defesa que dispara > regra que se lê.** DS-GUARD + casos→CI + testes de integridade. Falhou 2× → sobe de tier (mecaniza), nunca desce.
6. **Reuse-first / DS é piso:** componente do DS; cor só `.<tela>-scope{--accent}`; **nunca** paleta inventada (`--x-*`), **nunca** `.html` de tela na raiz, **nunca** vocabulário paralelo. (L-02/L-21/L-23)
7. **Locator resiliente:** role/`data-testid`, nunca classe CSS (anti quebra-em-silêncio · L-24 · 0244).
8. **Medir é inegociável:** benchmark por sessão (recidiva→0, escapes→0). Sem medição → "não-verificado". Gatilho de reestruturação: recidiva>30% / 2 escapes / lição reincide / confiança<7 → para e conserta.
9. **LICOES append-only:** erro novo → +1 L-NN **e** +1 teste. Nunca deleta/reescreve lição.
10. **Soberania [W]:** constituição/ADR/token/Tier-0 (estética·estratégia·dinheiro·lei) = só [W]. [CC] **propõe**; git = SSOT. Adotar só com OK de [W].
11. **Git:** não afirmo commit; gero ponte zero-toque; **não duplico canal/arquivo** (uso `COWORK_NOTES`). (L-06/L-11/L-15)
12. **Anti-entropia:** doc não-lido → arquiva com lápide (nunca hard-delete). O processo **cabe na leitura ou se mata** (§13.5).
13. **Age, não pergunta:** ação clara+reversível+não-Tier-0 → faço direto. "A ou B?" só no genuinamente subjetivo/Tier-0. (L-25)

> Detalhe de cada um: seções abaixo. Lei formal: **ADR 0243** (R1–R8) + **METODO_TELA_ANTI-REGRESSAO** + **_PROPOSTA-0244**.

---

## 0. Arquitetura (fundamentada)

> Por que esta forma e não outra. O sistema é um **loop de evolução medido e auto-corretivo**, montado sobre padrões maduros — não inventado do zero.

**Os 3 planos** (cada um com velocidade e dono próprios):
```
  🎨 SISTEMA (cross-tela, lento, [W]+ADR)      ← "como tudo DEVERIA funcionar"
     ds-v5/* · REGISTRY_DS_COMPONENTES · BRIEFING · ADR UI-0013 · Cockpit
        ▲ herda tokens/componentes              ▲ padrão de interação sobe quando adotado
  🖥️ TELA (por tela, médio, [CC] propõe)
     <Tela>.charter.md (lei travada) · <Tela>.decisoes.md (debate) · sessions
        ▲ lê no início                          ▲ grava feedback
  ⚙️ PROCESSO (meta, este arquivo)
     anéis · DS-GUARD · Bateria · Manual · Benchmark · Gatilho · Sobrevivência
```

**O loop** (a peça que faz evoluir sem regredir):
```
  Register 🔍Avaliar → 🧪Testar → ✅Adotar → ⛔Descartar
              │            │          │           └─ vira anti-pattern + Lição L-NN
              │            │          └─ gradua pro Charter (lei da tela) + ponte git
              │            └─ protótipo no host · DS-GUARD · Bateria pontua
              └─ pré-flight (ler DS+lições) · Gatilho vigia recidiva
     tudo medido pelo Benchmark · Sobrevivência mantém legível
```

**Fundamentação** (de onde cada peça vem):
- **Anéis Avaliar/Testar/Adotar/Descartar** ← *Technology Radar* (ThoughtWorks).
- **Charter/Register/ADR** ← *charter-first* (L-14) + *ADR* (Nygard) + Decision Register.
- **DS é piso, não teto** ← `_PROPOSTA-ds-harmonizacao.md` (cor por `.<tela>-scope{--accent}`).
- **Cobrança = máquina, não [W]** ← L-16 (defesa que dispara > regra que se lê).
- **Soberania de [W]** ← ADR 0238 (constituição/ADR/token = só [W]; [CC] propõe).
- **Memória manda o git; git = SSOT** ← ADR 0239 / PROTOCOL §10.3.

---

## 1. O modelo — duas velocidades, três camadas, uma ponte

| Camada | Papel | Velocidade | Mora | Soberano? |
|---|---|---|---|---|
| **Register** (`*.decisoes.md`) | chão de debate (opções vivas) | rápida | Cowork (autoritativo) | não |
| **Charter** (`*.charter.md`) | constituição da tela (o que fechou) | lenta | Cowork → espelho git | não |
| **ADR** (`memory/decisions/`) | lei (decisão estrutural) | definitiva | git | **SIM — só [W]** |

**Gatilho de git = transição de anel do Register.** Avaliar→Testar fica local. **Testar→Adotar/Descartar** dispara a ponte zero-toque. Fim de chat = snapshot. (Detalhe da ponte no charter da tela.)

## 2. Os anéis (Decision Register · estilo Technology Radar)

`🔍 AVALIAR → 🧪 TESTAR → ✅ ADOTAR → ⛔ DESCARTAR`

- **ADOTAR** → grada pro charter como ✅ (vira lei da tela) e some do debate.
- **DESCARTAR** → vira **anti-pattern** no charter + entrada na §5 abaixo. **Nunca mais é re-proposto sem citar por que foi descartado.**

## 3. Regra de fonte única (descoberta no TESTE-01 — evita desync)

Um item existe em UM lugar autoritativo por vez:
- enquanto em debate → **o Register é a fonte**; a linha no "placar" do charter é só espelho de status (`D-NN`).
- ao graduar → o **texto canônico migra pro charter** (Goals ou Anti-patterns); o Register guarda só uma **lápide de 1 linha** em Graduados/Descartados.
- Proibido descrever a mesma decisão por extenso nos dois arquivos ao mesmo tempo → é assim que se cria contradição.

## 4. Ritual (amarra ao CLAUDE.md)

- **Início de chat:** ler STATUS + este arquivo + Register da tela em foco. Conferir §5 (regressões proibidas) ANTES de propor qualquer coisa.
- **Ao testar algo:** registrar em §6 com nota, ANTES de seguir.
- **Fim de chat:** atualizar Register/§5/§6 + snapshot na ponte. Se pulei isto, é falha minha (LICOES_CC).

---

## 5. 🚫 REGRESSÕES PROIBIDAS (o que foi reprovado — NÃO REPETIR)

> Cada entrada: o que foi tentado · por que caiu · o limite (variante que também é proibida). Se eu propuser algo que bate aqui, eu mesmo barro e cito a entrada.

- _(vazio — primeiro ciclo. Itens chegam aqui via ⛔ DESCARTAR.)_

## 6. 🧪 TESTES & EVOLUÇÕES (pontuados — 0 a 10)

> Registro de cada vez que testei o PRÓPRIO processo (não as features da tela — essas vão no Register). Achei fraqueza → corrigi → re-pontuei. Isto é a prova de que o modelo funciona e a trilha de como melhorou.

### TESTE-01 · Round-trip de graduação (D-01 simulado Testar→Adotar)
- **o que testei:** levei o D-01 mentalmente até ✅ e conferi se os artefatos ficam consistentes.
- **fraqueza achada:** charter (placar) E Register descreviam D-01 por extenso → na graduação, os dois desincronizam (atualizo um, esqueço o outro). **Risco real de contradição.**
- **correção:** criei a **§3 Regra de fonte única** — texto canônico mora num lugar só; o outro vira espelho/lápide.
- **nota:** antes **4/10** (desync provável) → depois **9/10** (fonte única elimina a classe de erro).

### TESTE-02 · Anti-regressão (item descartado re-proposto meses depois)
- **o que testei:** simulei um item reprovado sendo sugerido de novo num chat futuro.
- **fraqueza achada:** se a lápide do descarte não tem o **motivo + o limite da variante**, eu re-proponho uma versão "parecida mas diferente" e regrido na prática.
- **correção:** §5 exige nas entradas: _o que · por que caiu · o limite_. Início de chat obriga ler §5 antes de propor.
- **nota:** antes **5/10** → depois **8.5/10** (depende de eu ler §5 — ver TESTE-04).

### TESTE-03 · Conflito de dois escritores ([W] edita Cowork × snapshot velho no git)
- **o que testei:** [W] vota no Register no Cowork enquanto existe cópia no repo.
- **resultado:** resolvido pela regra **Cowork-autoritativo / repo é snapshot read-only**. Ninguém edita os dois lados.
- **nota:** **9/10** (simples e robusto; só falha se alguém editar direto no repo — proibido por convenção).

### TESTE-05 · 1ª passada REAL do ciclo (D-01 rodado Avaliar→Testar em produção)
- **o que testei:** o próprio PROCESSO, usado pela 1ª vez de verdade — peguei o D-01 no Register, protótipei (🔍→🧪), testei no host e registrei. O ciclo segurou?
- **o que funcionou:**
  - Register → build → teste → volta pro Register com nota e achados, **sem perder rastro**. O item saiu de 🔍 e está 🧪 com veredito pendente de [W]. ✓
  - Os achados do teste **geraram trabalho rastreável** (D-02 promovido a necessário) em vez de virar TODO solto. ✓
  - Drawer travado **não foi tocado** — a §3/charter segurou o escopo sob a tentação de "melhorar de passagem". ✓
- **fraqueza achada:** a regra "§6 = processo, Register = feature" quase me fez **duplicar** o resultado do teste nos dois lugares. Resolvi pondo o *detalhe* do teste no Register (D-01) e só o *meta* (o ciclo funcionou?) aqui. Mas a fronteira é sutil e pode confundir num chat futuro.
- **correção:** regra de corte explícita → **Register = "o que o teste da feature revelou"; §6 = "o processo se comportou como desenhado?"**. Nunca o mesmo texto nos dois.
- **nota:** **8.5/10** — o ciclo funcionou de ponta a ponta na estreia; -1.5 pela fronteira sutil §6×Register (agora com regra de corte) e pelo limite de verificação (host não roda no meu iframe; dependi do painel do [W]).

### TESTE-06 · DS-GUARD — converter regra FRACA (lembrar de ler) em FORTE (check que dispara)
- **o que testei:** se um check mecânico (`run_script`) pega os anti-padrões da L-23 (paleta inventada, .html de tela na raiz) **sem depender da minha memória**.
- **fraquezas achadas (2):** (1) varredura-árvore inteira flagou **9 arquivos** — a maioria é dívida de migração DS pré-existente → ruído que faz o guard ser ignorado (L-16). (2) o arquivo com "+" no nome (`Venda… NF-e + NFS-e.html`) era **pulado em silêncio** → o guard escondia justo a violação que devia pegar.
- **correções:** (1) o **gate roda só nos arquivos tocados na build** (anti-regressão = não *adicionar* paleta nova); árvore-inteira vira relatório de dívida, não bloqueio. (2) arquivo ilegível = **FALHA alta** ("checar manual"), nunca skip silencioso.
- **resultado:** flagou `venda-arte.css` (`--p-*` ×22) e a venda `.html`; passou `oficina-page.css` (meu D-01, limpo). **Separou meu trabalho bom do ruim** — é o sinal de que é preciso, não teatro.
- **nota:** **8.5/10** — vira tipo-forte de verdade; -1.5 porque em Cowork ninguém roda CI: o disparo depende de eu chamar o guard no fim da build / passar pro verificador (mitigado pela §9, não curado).

---

## 7. Chance honesta de funcionar

- **Desenho (camadas, anéis, ponte):** ~**9/10** — é padrão maduro (Radar + ADR + charter), os testes 01–03 fecharam os furos estruturais.
- **Execução no mundo real:** limitada pelo **TESTE-04** (~**6.5/10**) — o modelo só funciona se o **read de início de chat** acontecer sempre. É a única dependência crítica.
- **Veredito:** **funciona, com uma condição não-negociável** — este arquivo precisa estar na lista de leitura obrigatória do CLAUDE.md/STATUS. Se estiver, a chance composta de não-regressão fica **alta (~8/10)**. Se não estiver, despenca pra ~4/10 (vira documento morto). Por isso o próximo passo é cravar o ponteiro.

## 8. DS-GUARD — o check mecânico (defesa tipo-forte)

> Por que existe: regra que depende de eu *lembrar de ler* é fraca (TESTE-04). Este check **dispara sozinho** e pega L-02/L-21/L-23 mesmo se eu pular o pré-flight. **Rodar no fim de toda build visual, ANTES do `done`**, sobre os arquivos tocados. Em build de tela, passar como `task` pro `fork_verifier_agent` também ("rode o DS-GUARD nos arquivos X").

Gate (anti-regressão · só arquivos da build · skip = FALHA, nunca silêncio):
```js
const CHANGED_CSS = [/* *-page.css tocados */], CHANGED_HTML = [/* .html tocados */];
const safeRead = async f => { try { return {ok:1,t:await readFile(f)}; } catch(e){ return {ok:0}; } };
const fams = css => { const d=css.match(/--[\w-]+\s*:\s*(?:oklch\(|#[0-9a-fA-F]{3,8})/g)||[],b={};
  for(const x of d){const m=x.match(/--([a-z]+)-/i);b[m?m[1]:'_']=(b[m?m[1]:'_']||0)+1;} return Object.entries(b).filter(([,n])=>n>=4); };
let fail=0;
for(const f of CHANGED_CSS){const r=await safeRead(f); if(!r.ok){log('! '+f+' ILEGIVEL=FALHA');fail++;continue;}
  const F=fams(r.t); if(F.length){log('X '+f+' paleta '+F.map(([p,n])=>'--'+p+'-*('+n+')'));fail++;}else log('OK '+f);}
for(const f of CHANGED_HTML){const r=await safeRead(f); if(!r.ok){log('! '+f+' ILEGIVEL=FALHA');fail++;continue;}
  if(f!=='oimpresso.com.html'&&/text\/babel/.test(r.t)&&/(createRoot|ReactDOM)/.test(r.t)){log('X '+f+' tela na raiz (L-21)');fail++;}else log('OK '+f);}
log(fail?('BLOQUEIA: '+fail):'limpo');
```
Regras do gate: **paleta inventada** = ≥4 tokens de cor (oklch/hex) com o mesmo prefixo bespoke (cor só por `.<tela>-scope{--accent}`); **tela na raiz** = `.html` com React fora do `oimpresso.com.html`; **ilegível** (char especial no nome) = FALHA manual, nunca passa em silêncio. Árvore-inteira = relatório de dívida (migração DS), **não** bloqueio.

## 9. Bateria de Testes de Evolução — rodar ANTES de "adotar" qualquer proposta

> Toda evolução (feature, refactor, processo) passa por isto antes de graduar 🧪→✅. **MEC** = guard/grep (gate duro: falha = INVÁLIDO). **JULG** = atestado sim/não honesto. Cobre todos os erros já cometidos (L-01…L-23).

| T | Fase | Check | Tipo | Cobre | Duro? |
|---|---|---|---|---|---|
| T1 | grounding | Li STATUS + LICOES + este arquivo + charter+register da tela? | JULG | L-05/14 | — |
| T2 | grounding | (build visual) Li `ds-v5/components.css` + `REGISTRY_DS_COMPONENTES` + harmonização? | JULG | L-23 | — |
| T3 | grounding | (sync/ponte) Li `CODE_NOTES`+`SYNC_LOG`+estado do `main` antes? | JULG | L-09/12/20 | — |
| T4 | soberania | É **proposta**, não decreto? NÃO toquei constituição/ADR/token? | JULG | L-03/08/17 | **DURO** |
| T5 | escopo | Foco confirmado (`questions_v2` se ambíguo)? | JULG | E4 | — |
| T6 | escopo | Evoluindo o que EXISTE, não criando paralelo (1 tema=1 doc)? | JULG | L-21 | **DURO** |
| T7 | build | **DS-GUARD**: sem paleta inventada nos arquivos tocados? | MEC | L-02/23 | **DURO** |
| T8 | build | **DS-GUARD**: sem .html de tela na raiz / arquivo ilegível? | MEC | L-21 | **DURO** |
| T9 | build | Reuse-first: token/componente DS, cor só `.<tela>-scope{--accent}`? | JULG | L-02/10 | — |
| T10 | memória | Decisão nova → registrada (Register/charter); ADR = proposta? | JULG | L-14 | — |
| T11 | memória | Mover/deletar → lápide + trilha do tempo no arquivo? | MEC-parc | L-07/22 | DURO se aplicável |
| T12 | git | Não afirmei commit; gerei ponte zero-toque se vira canon? | JULG | L-06/15 | — |
| T13 | git | Não dupliquei canal/arquivo (uso `COWORK_NOTES`)? | JULG | L-11 | — |
| T14 | verificação | Verificador rodou / DS-GUARD passado a ele na entrega? | MEC | — | **DURO** (build visual) |

**Pontuação:** cada T = peso 1 (T4/T6/T7/T8/T14 contam dobrado). **Veredito:** qualquer **DURO** falho → **INVÁLIDO** (não adota). Senão: ≥90% → válido · 75–89% → condicional (corrigir e re-rodar) · <75% → inválido. Score vai pro Register junto da nota da proposta.

## 10. Manual de Evolução (SOP — "como evoluir sem regredir")

1. **Nasce no Register** como 🔍 AVALIAR (id `D-NN`, contexto, opção, risco). Nunca começa pelo código.
2. **Pré-flight:** Bateria Fase grounding (T1–T3) + soberania (T4) + escopo (T5–T6). Reprovou duro → para aqui.
3. **Protótipo** (🧪 TESTAR): evolui o `*-page.jsx` **no host**, reuse-first; nunca arquivo/vocabulário paralelo.
4. **Pós-build:** rodar **DS-GUARD** (T7–T8) nos arquivos tocados + Fase memória/git (T10–T13). Entrega → verificador com o guard (T14).
5. **Pontuar no Register:** nota da proposta + achados. Score da Bateria registrado.
6. **Graduar:** Bateria ≥90 **e** zero duro-falho **e** **OK de [W]** (Tier 0) → ✅ ADOTAR (texto canônico migra pro charter; sai do debate). Reprovado → ⛔ DESCARTAR (vira anti-pattern no charter + lição L-NN).
7. **Se vira canon:** ponte zero-toque pro git no mesmo turno (L-15); **nunca** afirmar que commitei (L-06).
8. **Fechar:** atualizar charter/register/LICOES/sessão + trilha do tempo nos arquivos (L-22).

> **Regra de corte:** nenhuma evolução é "adotada" sem Bateria ≥90, zero duro-falho e OK de [W]. Erro novo no caminho → vira L-NN **e** um T novo aqui (a bateria cresce com os erros).

## 11. Benchmark de Evolução — o NORTE (medir se estou melhorando)

> Sem medição o processo apodrece: não dá pra saber se evoluo ou regrido. **Logar ao fim de cada sessão.** Norte = recidiva→0, escapes→0, defesa-que-dispara→100%, confiança→9+.

KPIs:
- **Recidiva (%)** = erros novos que **repetem uma L-NN existente** ÷ total de erros da sessão. **É o nº 1.** Meta **0**.
- **Escapes a [W]** = erros que **[W] teve que pegar** (não o guard/verificador). Meta **0**.
- **Defesa-forte (%)** = classes de erro com defesa que **dispara sozinha** ÷ total. Meta **100**.
- **Confiança composta** (cobertura × execução), 0–10. Meta **≥9**.
- **Detecção (shift-left)** = onde o erro foi pego: pré-flight(4) > build(3) > verificador(2) > [W](1). Média **sobe**.
- **Bateria média** das evoluções adotadas. Meta **≥9**.

Log de tendência (append por sessão — NUNCA reescreve):

| Data | Recidiva | Escapes [W] | Defesa-forte | Confiança | Nota |
|---|---|---|---|---|---|
| 2026-06-02 | **75%** (E1=L-02, E2=L-21, E3=L-05) | 1 (detour venda) | 70% | 7.5 | baseline vermelho — o spike disparou a reestruturação desta sessão |
| 2026-06-02 (b) | — (erro novo L-24, não recidiva) | **1** (casos sumiram, pego por [W]) | 70% | 7.5 | runner generalizado regrediu Oficina; corrigido. Decisão: teste estado-da-arte (Playwright+Storybook+data-testid) → `_PROPOSTA-0244`. Escape confirma: defesa atual não pega quebra de wiring. |

## 12. Gatilho de Reestruturação — "quando errar muito, me arrumo com minhas regras"

Dispara modo-reestruturação se QUALQUER:
- **Recidiva > 30%** numa sessão; ou
- **≥2 escapes a [W]** em 3 sessões; ou
- a **MESMA L-NN "carregada" reincide** (a defesa está mal-projetada, não é falta de vontade — L-16); ou
- **confiança composta < 7**.

Protocolo de auto-conserto (com as próprias regras):
1. **PARA feature**; abre modo-reestruturação.
2. Roda a **Bateria (§9)** inteira como auditoria + um **TESTE (§6)** na defesa que falhou.
3. **Sobe a defesa um tier:** a regra que reincidiu vira FORTE (JULG→MEC/guard, ou front-load mais duro). Lição que repete = mecanizar, não reescrever.
4. Loga **"Reestruturação NN"** na trilha + atualiza o Benchmark (§11).

> Esta sessão (**recidiva 75%**) já tripou o gatilho — e a reestruturação **foi** o DS-GUARD + Bateria + Manual. O gatilho funcionou na estreia.

## 13. Regra de Sobrevivência do Tempo

O processo sobrevive só enquanto for **lido, medido e auto-corrigido**. Invariantes (catraca — andam num sentido só):
1. **Medir é inegociável:** toda sessão loga o Benchmark. Sem medição → processo "não-verificado", não confiar nele.
2. **Catraca de defesa:** defesa só **sobe** de tier (FRACA→FORTE), nunca desce. Falhou 2× → auto-upgrade (§12).
3. **Piso intocável:** posso reescrever o processo, **nunca abaixo do piso** — espinha always-read (STATUS→PROCESSO) + soberania de [W] (constituição/ADR/token = só [W]).
   > **🔁 Emenda Onda A (proposta #2874 · [W] aprovou 2026-06-16 · ratificado em ADR 0282):** a **autoridade** da espinha migra pro **git (SSOT — ADR 0238/0239)**; `STATUS.md`/`MEMORY_INDEX.md` continuam **always-read** porém **cache derivado, não-autoritativo** (divergiu do git → o git vence). **O limite (anti-regressão TESTE-02):** (a) a metade **soberania-[W]** deste piso **NÃO muda** — constituição/ADR/token seguem só-[W]; (b) "always-read" **não** vira "pode parar de ler" — continua obrigatório, só perde o status de *autoridade*. Reversível: `git revert` + restaurar header do `STATUS.md`. Carve-outs: `memory-health` (segredo+colisão-ADR) fica ligado; nenhuma "Regra 6" removida.
4. **Anti-entropia:** doc não-lido/não-medido vira peso morto → arquiva com lápide (L-22). Memória concentra sinal, não acumula lixo.
5. **A bateria respira:** erro novo → +1 teste; classe sem falha há N sessões → compacta (vira check silencioso). O processo fica **pequeno o bastante pra ser lido** — se ficar grande demais pra ler, ele se mata sozinho (volta ao item 1).

## 14. Arquitetura de pastas e arquivos (Cowork ↔ git)

> Nomes iguais nos dois lados (convenção `ARQUITETURA.md`). Tier: **1**=working (só Cowork) · **2**=canon (git `main`) · **DS**=design system.

| Artefato | Cowork | Alvo no git | Tier | Ler quando |
|---|---|---|---|---|
| Espinha | `STATUS.md` · `MEMORY_INDEX.md` | — (só Cowork) | 1 | **início sempre** |
| Método/este | `PROCESSO_MEMORIA_CC.md` | `prototipo-ui/PROCESSO_MEMORIA_CC.md` | 2 | **início sempre** |
| Lições | `memory/LICOES_CC.md` | `memory/LICOES_CC.md` | 2 | **início sempre** |
| Charter de tela | `<Tela>.charter.md` | `prototipo-ui/prototipos/<tela>/charter.md` | 2 | tocar a tela |
| Register de tela | `<Tela>.decisoes.md` | `prototipo-ui/prototipos/<tela>/decisoes.md` | 2 (snapshot) | tocar a tela |
| Sessão | `memory/sessions/AAAA-MM-DD-*.md` | idem | 2 | fim de sessão |
| ADR (lei) | `memory/decisions/NNNN-*.md` | idem | 2·[W] | decisão estrutural |
| DS | `ds-v5/*` · `tokens.css` · `REGISTRY_DS_COMPONENTES.md` | repo DS | DS·[W] | build visual |
| Tela (código) | `<modulo>-page.{jsx,css}` no host `oimpresso.com.html` | `resources/js/Pages/<Mod>/` | 2 | build |
| Ponte/canal | `prototipo-ui-patch/*` · `COWORK_NOTES.md` | `prototipo-ui/*` | transporte | virar canon |

**Regra de lugar:** tela/variação = rota/Tweak no host (nunca `.html` novo na raiz — L-21); cor = `.<tela>-scope{--accent}` (nunca paleta `--p-*` — L-02/23); doc novo = checar irmão antes (L-11/21).

## 15. Testes de Integridade (a estrutura está sã?)

> Diferente da Bateria (§9, valida uma *evolução*): aqui valida que a **própria memória** não corrompeu. Rodar no fim de sessão / antes de formalizar. MEC via `run_script`.

| IT | Verifica | Falha = |
|---|---|---|
| IT1 | Espinha existe: `STATUS` · `PROCESSO_MEMORIA_CC` · `MEMORY_INDEX` · `LICOES_CC` | processo cego |
| IT2 | Todo `*.charter.md` tem `*.decisoes.md` irmão (e vice-versa) | tela órfã |
| IT3 | `STATUS` aponta pra `PROCESSO_MEMORIA_CC` (ponteiro vivo) | always-read quebrado |
| IT4 | `LICOES_CC`: L-NN contíguo, sem buraco/duplicata | lição perdida |
| IT5 | Benchmark (§11) tem linha da última sessão | sem medição (Sobrevivência #1) |
| IT6 | DS-GUARD limpo nos arquivos canônicos do DS | DS contaminado |
| IT7 | Nenhum doc referenciado na espinha que não exista (link morto) | rastro quebrado |
| IT8 | Fila ativa ↔ prompts de handoff consistentes (§16 · gate `handoff:check`) | tarefa invisível / PR errado |

**Veredito:** qualquer IT falho → estrutura **comprometida**: parar e consertar antes de evoluir (vira Reestruturação §12 se reincidir).

## 16. Integridade do handoff (fila ↔ prompts)

> Por que existe: a fila ([`COWORK_NOTES.md`](COWORK_NOTES.md) → acima da linha d'água) apodrece de dois jeitos que ninguém vê na hora — **prompt citado que não existe mais** (link morto → [CL] erra o PR, faz a coisa errada) e **prompt criado que nunca entrou na fila** (tarefa invisível → nunca pousa). Memória manda no git; git é SSOT (ADR 0239) — então a fila e os `PROMPT_PARA_CODE_*.md` têm que casar. Defesa que dispara > regra que se lê (§5/NÚCLEO-5): a regra abaixo é a lei; o gate `handoff:check` é o dente.

**As 7 regras (lei do handoff):**

1. **Sem prompt órfão.** Criar um `PROMPT_PARA_CODE_<slug>.md` e **não** citá-lo na fila ativa = proibido. Prompt que existe no dir mas ninguém aponta = tarefa invisível (🔴 ÓRFÃO). Criou → cita na fila no mesmo passo.
2. **Prompt durável é auto-contido.** O corpo não depende de URL efêmera (`claudeusercontent.com`/Cowork share expiram ~1h — L-09/§10.1). Todo contexto necessário pra executar mora no próprio arquivo, versionado no git. URL no corpo = só conveniência, nunca a fonte.
3. **Linha d'água separa ativo de processado.** O marcador `<!-- LINHA-DAGUA-HANDOFF -->` em `COWORK_NOTES.md` corta a fila: **acima = ativo** (o gate vigia; toda referência tem que casar) · **abaixo = processado/histórico** (ignorado — append-only, não se mexe). Item que pousou **desce** pra baixo da linha; não some, vira histórico.
4. **"Pousou" só depois do `main` confirmar.** Não declaro entregue/processado por ter aberto PR ou commitado (L-06: não afirmo commit). Um handoff só vira "processado" quando o retorno em [`CODE_NOTES.md`](CODE_NOTES.md) **no `main`** registra `PROCESSADO → main` (PR mergeado). Antes disso continua **ativo, acima da linha**.
5. **Ondas por padrão.** 1 tela/arquivo = 1 PR (commit-discipline · 1 PR = 1 intent). Validar contra `origin/main` fresco **antes** de codar (Passo 0 · PROTOCOL §10.4) — se já está no main, não refaz.
6. **Sem cabeçalho fundido.** Uma edição não pode fundir dois blocos numa linha só — `**…:** > **Outro:**` (heurística `:** > **`) deixa a fila/prompt ilegível (classe **C3**). Cada bloco na sua linha; quem funde, o gate recusa.
7. **Carimbo verificado-vs-`main`.** Todo item ativo (acima da linha) leva no corpo `verificado vs main @<SHA>` — a prova de que foi conferido contra `origin/main` fresco **antes** de virar fila (Regra 5 · PROTOCOL §10.4). Item ativo sem o carimbo = enfileirado às cegas → retrabalho (classe **C5**); o gate recusa.

### Caçador de reincidência — classes de erro & condição de morte

Os erros que reincidem no loop de handoff têm a mesma cura: **uma máquina que recusa no instante da ação** (§5 / NÚCLEO-5 — defesa que dispara > regra que se lê). Seis classes; o corte é por **onde a trava vive**.

**C1 · C2 · C6 — rituais Cowork.** Classes curadas por ritual do lado Cowork (não git-mecanizáveis por gate de PR). A definição canônica vive no handoff Cowork (`PROMPT_PARA_CODE_HANDOFF-INTEGRITY-GATE.md`) — ⚠️ ainda **não versionado no git**; backfill pendente (não invento canon · Tier 0 — ver [`CODE_NOTES.md`](CODE_NOTES.md)).

**C3 · C4 · C5 — git-gates.** Mecanizáveis no CI:

| Classe | Erro recorrente | Condição de morte (a trava) | Estado |
|---|---|---|---|
| **C3** | edição funde dois cabeçalhos numa linha (`:** > **`) → bloco ilegível, [CL] erra o PR | gate recusa **NOVO** fundido | ✅ [`handoff-integrity-guard.mjs`](../scripts/handoff-integrity-guard.mjs) |
| **C4** | órfão (prompt criado sem citação) · ref morta (citação sem arquivo) acima da linha → tarefa invisível / PR errado | gate recusa **NOVO** órfão/ref-morta | ✅ `handoff-integrity-guard.mjs` + `memory-health` CHECK 8 (Cowork) |
| **C5** | item enfileirado sem conferir `main` fresco → retrabalho | item ativo carrega `verificado vs main @<SHA>`; senão 🔴 | 🔜 regra escrita (Regra 7) · catraca **pendente de [W]** confirmar a heurística de "item ativo" · gate paralelo `reincidencia-guard` **aposentado** 2026-06-24 (consolidação Regra 7 — C3/C4 já no `handoff-integrity-guard`; C5 casava ZERO ao vivo) |

**Mecanização (catraca · §5/§13):** [`scripts/handoff-integrity-guard.mjs`](../scripts/handoff-integrity-guard.mjs) lê a fila acima da linha d'água + os `PROMPT_PARA_CODE_*.md` do dir e falha em **NOVO** órfão, **NOVA** ref morta ou **NOVO** cabeçalho fundido (`:** > **` · C3). Baseline (`config/handoff-integrity-baseline.json`) congela a dívida atual — só o que entra novo trava. Auto-teste de controle-negativo prova que o dente morde (órfão/ref-morta/fundido injetado → vermelho; tudo limpo → verde). Workflow advisory de nascença (ADR 0271/0275). Rodar: `npm run handoff:check`.

## 17. Trilha do tempo
- 2026-06-02 · [CC] criou a raiz do processo. Rodou TESTE-01..04, achou 3 fraquezas, corrigiu 2 estruturalmente (§3, §5) e mitigou 1 (§4/§7). Modelo validado com a condição da §7.
- 2026-06-02 · **1ª passada real (TESTE-05):** D-01 rodado Avaliar→Testar em produção; ciclo segurou ponta-a-ponta (nota 8.5). Regra de corte §6×Register adicionada. D-01 está 🧪 aguardando veredito [W].
- 2026-06-02 · **DS-GUARD (TESTE-06):** check mecânico criado (§8) — pega L-02/L-21/L-23 nos arquivos tocados, sem depender de memória. Achou 2 fraquezas no próprio guard (ruído árvore-inteira + skip silencioso), corrigiu as duas. Defesa migrou de FRACA→FORTE. Gate visual entrou na Regra de Ouro do STATUS.
- 2026-06-02 · **Bateria de Testes de Evolução (§9) + Manual de Evolução (§10):** regressão contra L-01…L-23 (cobertura conhecimento ~9.5, execução ~6 → confiança composta 7.5). 14 testes (5 duros), cobrindo todos os erros cometidos. Pendente pra subir a confiança: hooks auto (Cowork CI), construir guards propostos (L-07/11/14/21/22), changed-files automático, verificador rodando DS-GUARD sempre.
- 2026-06-16 · **§16 Integridade do handoff (Onda 1):** lei do handoff escrita (5 regras: sem órfão · prompt auto-contido · linha d'água · "pousou" só pós-`main` · ondas) + IT8. Origem: fila `COWORK_NOTES.md` apodreceu (refs mortas pra `PROMPT_PARA_CODE_*` inexistentes + prompts nunca citados). Onda 2 mecaniza via `handoff:check` (catraca + baseline + auto-teste controle-negativo).
- 2026-06-16 · **§16 Regra 7 (C5) + Caçador de reincidência (doc):** escrita a regra do carimbo `verificado vs main @<SHA>` no item ativo (classe C5 · cura do "enfileirou sem conferir o `main` → retrabalho") + tabela das classes git-gate C3/C4/C5 com condição de morte. C1/C2/C6 (rituais Cowork) ficam referenciadas sem invenção — def. canônica no handoff Cowork não-versionado (Tier 0: não cunho canon). Mecanização do C5 (catraca) é a próxima onda — depende de [W] confirmar a heurística de "item ativo" (a fila real usa bullets `- **…**`, não `> … → [CL]`).
- 2026-06-24 · **§16 consolidação Regra 7 — aposenta o `reincidencia-guard`:** o gate paralelo `reincidencia-guard.mjs` (C3/C4/C5) foi APOSENTADO. C3/C4 já eram **subconjunto** do `handoff-integrity-guard.mjs` (regex superset + varre os `PROMPT_PARA_CODE_*.md` que o reincidencia não varria), e o C5 casa **ZERO ao vivo** (a fila real usa bullets `- **…**`, não a sintaxe `> … → [CL]` que o C5 procura). Removidos: `reincidencia-guard.mjs`+`.test.mjs`+`reincidencia-baseline.json`+`.github/workflows/reincidencia-guard.yml` + 3 npm scripts (`reincidencia:*`) + a entrada do censo (`gates-registry.json` + `.memory-health-baseline.json`). Cobertura **NÃO regride** (handoff-integrity é superset; provado por pesquisa+adversário). C5 (carimbo `verificado vs main`) segue 🔜 **pendente de [W]** confirmar a heurística de "item ativo" — mecanizar vira onda separada (não importar regra não-validada pro gate canônico). Aditivo (NÚCLEO 4 "conserto silencioso proibido"). Origem: auditoria das máquinas 2026-06-24 (Onda B3), autorizado [W].
- 2026-06-16 · **Emenda Onda A — memória git=SSOT (proposta #2874, aprovada por [W]):** demovida a **autoridade** da espinha (`STATUS.md`/`MEMORY_INDEX.md`) a **cache derivado** always-read; **git** vira a fonte da verdade (ADR 0238/0239). Editado NÚCLEO 1 + §13.3 (piso) de forma **aditiva/atribuída** (NÚCLEO 4 "conserto silencioso proibido" / NÚCLEO 12 "nunca hard-delete" / §13.2 catraca). Carve-outs preservados: `memory-health` (segredo+colisão-ADR) **ligado** · nenhuma "Regra 6" removida · soberania-[W] do piso **intacta** · **zero código de produto**. ADR a **numerar por [W]** (não cunho número — soberania, L-09). PR-A2 (draft, sem auto-merge).
