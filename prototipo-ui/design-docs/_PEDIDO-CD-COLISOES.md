# Pedido ao [CD] — resolver as colisões antes de descer o resto do espelho

> **Cole isto no chat do projeto Cowork.** Escrito por [C] a pedido de [W] em 2026-08-22.
> Este arquivo é **autoral**, não é espelho de nada.

## Contexto em três linhas

O projeto Cowork está sendo espelhado em `prototipo-ui/design-docs/` do repo
`wagnerra23/oimpresso.com` (decisão [W] 2026-08-21). O espelho é **cópia fiel e congelada** —
ele não corrige a fonte, de propósito.

Problema: parte dos `.md` que faltam descer tem **o mesmo nome** de um arquivo **vivo** do repo.
O espelho é namespaced (`design-docs/…`), então **não sobrescreve nada**. O risco é outro:
alguém faz `git grep LICOES_F3_FINANCEIRO_REJEITADO`, acha duas cópias, e cita a **arquivada**
como se fosse a lei.

## Os números — não os copie daqui, rode

```bash
node scripts/design-sync/colisao-espelho.mjs <listagem-do-cowork.json>
```

Na medição de **2026-08-22**: 294 `.md` na fonte · 133 ainda não desceram · **38 colidem**
(11 no balde A, 20 no B, 7 no C) · **95 podem descer já**. Estes números andam a cada leva —
o comando é o dono deles, este parágrafo é só o retrato do dia.

---

## A · APAGAR na origem — 11 arquivos

**Pasta:** `_arquivo/repo-mirror/`

Isto é **cópia de arquivo do repo dentro do projeto de design**. E não sou eu dizendo que é
errado — é a lápide de vocês, sob mandato do [W] em 2026-06-10
(`_arquivo/LAPIDE-DEDUP-2026-06-10.md`):

> "**Espelho local de arquivo do repo = PROIBIDO existir.** Estado do repo só por
> `github_read_file @main` no turno (Regra 6). Sem cache em disco."

A mesma lápide classifica a pasta `resources/` (que era a mesma coisa) como *"fonte da classe de
erro mais recorrente — fotocópia que envelhece; **reincidência tripla** 06-08"*.

**Medi um deles agora, e confirma a lápide na letra.** `repo-mirror/Configuracoes/Contador.charter.md`
não é cópia igual do canon — é uma versão de **duas gerações de schema atrás**:

| campo | cópia no Cowork | canon vivo no repo |
|---|---|---|
| `id:` | ausente | `resources-js-pages-financeiro-configuracoes-contador-charter` |
| US | `us: US-FIN-037` | `related_us: [US-FIN-037]` |
| `related_prototype:` | ausente | presente — **os gates de âncora exigem hoje** |

Descer isso colocaria no repo um charter em schema morto, com nome idêntico ao vivo.

1. `_arquivo/repo-mirror/Advisor/Dashboard.charter.md`
2. `_arquivo/repo-mirror/Advisor/Login.charter.md`
3. `_arquivo/repo-mirror/Caixa/Index.charter.md`
4. `_arquivo/repo-mirror/Cobranca/Index.charter.md`
5. `_arquivo/repo-mirror/Conciliacao/Index.charter.md`
6. `_arquivo/repo-mirror/Configuracoes/Contador.charter.md`
7. `_arquivo/repo-mirror/ContasBancarias/Index.charter.md`
8. `_arquivo/repo-mirror/Dre/Index.charter.md`
9. `_arquivo/repo-mirror/Extrato/Index.charter.md`
10. `_arquivo/repo-mirror/Fluxo/Index.charter.md`
11. `_arquivo/repo-mirror/Unificado/Index.charter.md`

> **Alternativa append-only**, se preferirem não apagar: mover para
> `_arquivo/repo-mirror-OBSOLETO/` com um `_LEIA.md` de uma linha — *"versões velhas de charter;
> o vivo está no git"*. Resolve igual pro meu lado.

---

## B · MOVER para `prototipo-ui-patch/_processados/` — 20 arquivos

**Não é rename — é mover pra pasta que vocês já têm.** `prototipo-ui-patch/_processados/`
existe no Cowork (10 `.md` hoje), e a contraparte existe no repo
(`prototipo-ui/_arquivo/handoffs-processados/`). **Dois destes já foram parar lá pelo caminho
certo** (`PROMPT_PARA_CODE_DS-ESPELHAR-DOMINIO.md` e `…ESTRUTURA-COWORK-ATUALIZADA.md`) — o
padrão está provado, só não foi aplicado no resto.

Cada um destes é documento de design cujo conteúdo **já foi aplicado no repo**. Ficar na raiz do
`prototipo-ui-patch/` faz parecer ponte pendente quando já é história.

**De `prototipo-ui-patch/` (raiz):**
1. `CHARTER_GOVERNANCA_CC_JANA.md`
2. `CODE_DESIGN_CONTRACT.md`
3. `DS_ADOCAO_INDICE.md`
4. `LICOES_F3_FINANCEIRO_REJEITADO.md` ← **citado na `proibicoes.md` do repo**
5. `MATRIZ_MIGRACAO_DS.md`
6. `ONDA_G_BADGE_VARIANTS.md`
7. `PROMPT_PARA_CODE_DS-ESPELHAR-DOMINIO.md`
8. `PROMPT_PARA_CODE_ESTRUTURA-COWORK-ATUALIZADA.md`
9. `REGISTRY_DS_COMPONENTES.md` ← **citado no §5 da `proibicoes.md`**
10. `REGRAS_DS_LINT.md`

**De subpastas do `prototipo-ui-patch/`:**
11. `Pages/Jana/Chat.charter.md`
12. `memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md`
13. `prototipo-ui/evals/AUTONOMY_LADDER.md`
14. `prototipo-ui/evals/EVAL_PROTOCOL.md`
15. `prototipo-ui/evals/GOLDEN_SET.md`
16. `prototipo-ui/evals/REPLAY_CASES.md`

**De `_arquivo/` (mesma natureza, outro caminho):**
17. `_arquivo/docs-legado/AVALIACAO_OS_GIT_2026-06-09.md`
18. `_arquivo/docs-legado/CHARTER_CHAMPION_AGENTES.md`
19. `_arquivo/docs-legado/CHARTER_GOVERNANCA_W.md`
20. `_arquivo/legado/memory-para-github/sessions/2026-04-28-design-prototype-chat-erp.md`

---

## C · NÃO mexer — 7 arquivos

`INDEX.md` · `README.md` · `SCOPE.md` · `SPEC.md` · `CONTRACTS.md` em várias pastas.

Nome **estrutural** no repo: `README.md` tem **120** ocorrências, `SCOPE.md` tem **32**,
`SPEC.md` tem **96**. O path já desambigua e ninguém confunde. Pedir rename aqui só criaria
ruído sem resolver risco nenhum — e um nome com sufixo esquisito dentro do Cowork atrapalharia
vocês sem me ajudar.

---

## O que acontece depois

Feitos A e B, o espelho desce **sem nenhuma colisão de nome**, e nada arquivado fica parecendo
canon. Os **95 sem colisão não dependem disto** — descem em paralelo.

## Limite honesto

Conferi conteúdo de **1** dos 38 (`Contador.charter.md`). Isso prova a **classe** do balde A,
não que os outros 37 também estejam velhos.

**Se algum item do balde B estiver À FRENTE do repo**, ele não é cópia processada — é ponte
pendente, e a lista está errada quanto a ele. Me diga qual, que ele sai daqui e vira handoff.
