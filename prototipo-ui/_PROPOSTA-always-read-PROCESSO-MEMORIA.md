# PROPOSTA → [W] aplica: cravar PROCESSO_MEMÓRIA_CC no always-read do CLAUDE.md raiz

> ✅ **APLICADO 2026-06-02 — [W] autorizou a Opção A.** O passo **4b** entrou no `CLAUDE.md` raiz no mesmo PR (`feat/cowork-processo-memoria-cc`). Este doc fica como **registro** (trilha L-22) — a Opção B e o contexto de soberania abaixo seguem válidos como histórico/alternativa.

> **Por que isto é proposta (não aplicado por [CC]):** mexer no `CLAUDE.md` raiz é Tier-0 / soberania de [W] (L-08). O §7 do PROCESSO diz que sem o ponteiro na lista de leitura obrigatória o método "vira documento morto (~4/10)". Com ele, **~8/10**. Então o ponteiro é a peça crítica — mas quem crava é [W].
>
> **Como aplicar:** colar **um** dos blocos abaixo no `CLAUDE.md` raiz. Wiring working-tier (COWORK_NOTES banner + STATUS→PROCESSO) já está feito; isto fecha o lado git/Tier-0.

---

## Opção A (recomendada) — emendar o passo 4 do protocolo de sessão

No `CLAUDE.md` raiz, seção **"## Como trabalhar (protocolo de sessão)"**, logo após o passo 4 (UI), inserir:

```markdown
4b. **Antes de tocar design-memory** (`prototipo-ui/**` · charters · `*.casos.md` · build visual): ler [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](prototipo-ui/PROCESSO_MEMORIA_CC.md) (raiz do método anti-regressão — §5 REGRESSÕES PROIBIDAS + NÚCLEO 13 invariantes) + [`memory/LICOES_CC.md`](memory/LICOES_CC.md) (L-01..L-25). No fim da build, rodar `node prototipo-ui/ds-guard.mjs <arquivos tocados>` (§8) e, ao formalizar, `node prototipo-ui/integrity-check.mjs` (§15). _REGRESSÃO É INACEITÁVEL._
```

## Opção B — bullet na seção "Onde NÃO inventar (Tier 0)" (paralelo ao F3)

A seção já tem o bullet do `LICOES_F3_FINANCEIRO_REJEITADO.md`. Adicionar irmão:

```markdown
- **Design-memory (Cowork ↔ git):** antes de evoluir `prototipo-ui/**` ler [`PROCESSO_MEMORIA_CC.md`](prototipo-ui/PROCESSO_MEMORIA_CC.md) + [`memory/LICOES_CC.md`](memory/LICOES_CC.md). Defesas mecânicas: `ds-guard.mjs` (§8 · paleta inventada/tela-na-raiz) e `integrity-check.mjs` (§15 · espinha sã). Anéis Avaliar→Testar→Adotar→Descartar; nada reprovado volta sem citar §5.
```

---

## Contexto de soberania (pra decisão de [W])
- O PROCESSO referencia **ADR 0238** (soberania [W]) e **ADR 0239** (memória manda o git) como fundamentação; STATUS afirma 0238 já é canon em `main` (PR #2007). [CC] **não** criou/numerou ADR (L-09). Se [W] quiser formalizar o método como ADR (mãe 0114), é decisão/numeração de [W].
- Skills Tier A (`brief-first`, `mwart-process`…) são análogas: se o método virar "always-on" de verdade, o caminho canônico é skill + ADR (ver tabela "Como propor mudança" do CLAUDE.md). Esta proposta é o mínimo (ponteiro no CLAUDE.md); a versão forte é uma skill `design-memory-first`.

> Detalhe do que foi landeado: `prototipo-ui/README-DESTINO-PROCESSO-MEMORIA-2026-06-02.md`.
