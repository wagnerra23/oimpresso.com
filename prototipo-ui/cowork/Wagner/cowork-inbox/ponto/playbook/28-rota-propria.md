<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "rota própria (8 páginas)").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 28 · D-PONTO-DETALHE — rota própria nas 9 páginas (bloco 1 da ata)

> **Decisão de [W] 2026-09-14:** **ROTA PRÓPRIA.** *"In-page não é alternativa, é 2ª pele sobre 9 páginas que já existem, com charter e casos ao lado."* Por **R2**, quem muda é o protótipo.
> **Estado:** ✅ **mecanismo pronto e provado** · ⏳ **8 páginas na fila**.

---

## 1 · O mecanismo — feito e medido neste turno

Não inventei rota nova: usei **o padrão que o shell já tem** para Patrimônio (`pat-`), Repair (`rep-`), HRM (`hrm-`) e Essenciais (`ess-`).

**`app.jsx`** — uma linha:
```jsx
if (route === "ponto" || (typeof route === "string" && route.indexOf("pt-") === 0))
  content = <window.PontoPage view={route} />;
```

**`ponto-page.jsx`** — a rota passa a ser a **fonte**, não o estado interno:
```jsx
const daRota = (v) => {
  if (!v || v === "ponto") return { aba: "painel", id: null };
  const s = String(v).replace(/^pt-/, "");
  const m = s.match(/^(espelho)-(\d+)$/);
  if (m) return { aba: m[1], id: Number(m[2]) };
  return { aba: s, id: null };
};
```
`espelhoDe` nasce de `rota0.id`; o `useEffect([view])` re-sincroniza; e a navegação virou rota:
```jsx
const irRota  = (r) => { if (window.__go) { window.__go(r); return true; } return false; };
const irPara  = (k)  => { setEspelhoDe(null); if (!irRota("pt-" + k)) setAba(k); };
const abrirEspelho = (id) => { if (!irRota("pt-espelho-" + id)) { setEspelhoDe(id); setAba("espelho"); } };
```
> **Fallback de propósito:** se o shell não expuser `__go`, o componente volta ao estado interno. O protótipo nunca fica sem navegação por causa do host.

**Medido (T1 estável, dark, após `__oiLazyDone`):**

| rota | o que apareceu |
|---|---|
| `pt-aprovacoes` | `aprovacoes-fila-de-aprovacoes` |
| **`pt-espelho-1`** | `espelho-dados-colaborador` · `espelho-totais` · `espelho-apuracao-diaria` · `espelho-folha-impressao` · h2 "Wagner Ramos" |
| `ponto` | os 4 contratos do painel |

**Deep link de detalhe funcionando** — 1 das 9 páginas fechada (`Espelho/Show`), e as 13 abas passaram a ter rota.

---

## 2 · As 8 que faltam — tabela de rota, e o que sai de cada símbolo

| # | página viva no `main` | rota do protótipo | estado interno que SAI | símbolo |
|---|---|---|---|---|
| 1 | `Intercorrencias/Show` | `pt-intercorrencias-<id>` | `foco`/`onFoco` (hoje abre **Drawer**) | `Intercorrencias :: 168-288` |
| 2 | `Intercorrencias/Create` | `pt-intercorrencias-novo` | `nova` | idem + `FormIntercorrencia` |
| 3 | `Intercorrencias/Edit` | `pt-intercorrencias-<id>-editar` | `editando` | idem |
| 4 | `BancoHoras/Show` | `pt-banco-horas-<colab>` | `sel` (o `if (sel)`) | `BancoHoras :: 289-394` |
| 5 | `Escalas/Form` (create) | `pt-escalas-nova` | `form: {escala:null}` | `Escalas` + `EscalaForm` |
| 6 | `Escalas/Form` (edit) | `pt-escalas-<id>-editar` | `form: {escala}` | idem |
| 7 | `Colaboradores/Edit` | `pt-colaboradores-<id>-config` | `edit` | `Colaboradores` + `ColaboradorForm` |
| 8 | `Importacoes/Show` | `pt-importacoes-<id>` | `sel` | `Importacoes :: 635-767` |
| 8-bis | `Importacoes/Create` | `pt-importacoes-nova` | `nova` (Card inline) | idem |

**Regex única para todas** (uma só linha no `daRota`, não nove):
```
^(intercorrencias|banco-horas|escalas|colaboradores|importacoes|espelho)-(novo|nova|\d+)(-editar|-config)?$
```

---

## 3 · Ordem, e por quê

1. **Intercorrências** primeiro — é a que tem **3 páginas** e a que o `D-INTERC-ACOES` já mexeu (a lista só tem `Ver`; se o `Ver` não abrir rota, a tela fica **sem caminho para a ação**). Hoje o `Ver` abre Drawer: é a única das 8 que está **funcionalmente incompleta** enquanto a rota não existe.
2. **Importações** — 2 páginas, e o upload inline (`nova`) é o caso mais fácil de virar rota (não tem id).
3. **Escalas** — 2 rotas no mesmo símbolo (`nova` × `<id>-editar`), e destrava o redirect que o `D-ESC-TURNOS` exige (*"vá configurar os turnos"* só faz sentido com rota).
4. **BancoHoras** e **Colaboradores** — 1 cada, mecânicos.

**Cada um é 1 PR ≤ 300 linhas.** Nenhum deles toca o `app.jsx` de novo (a linha do prefixo já está lá) — só o símbolo e o `daRota`.

---

## 4 · O que a rota destrava além de si mesma

- **`Voltar` deixa de ser estado e vira navegação** — hoje `onVoltar={() => setSel(null)}` some do histórico do navegador; com rota, o botão do navegador funciona.
- **O `map.json` ganha endereço por tela** — hoje `BancoHoras/Show` e `Index` são **o mesmo símbolo com faixas diferentes**; com rota, cada um tem entrada própria e o `prototipo_sha` deixa de invalidar os dois de uma vez.
- **`data-contract` do detalhe passa a ser alcançável por deep link** — condição para o `design-diff --probe` medir a seção sem cliques.
- **Fecha o F4 parcialmente:** `Fechamento`/`Conformidade`/`REP-P` continuam **sem receptor** no `main` — a rota não resolve isso, é decisão de [W].

---

## 5 · PARAR SE

- o `__go` do shell não aceitar um prefixo novo ⇒ **parar e reportar**: o dono do roteamento é o shell, não o módulo;
- a rota exigir **rota nova no `main`** (ex.: `/ponto/intercorrencias/{id}/editar` não existir) ⇒ **parar**: rota de produção é [W], e o protótipo não inventa endpoint;
- qualquer PR passar de 300 linhas ou tocar 2 símbolos ⇒ **quebrar em dois**.

---

## 6 · Declarado, não medido

- **Não li** os 9 `.tsx` vivos neste turno — a tabela de rotas acima vem do **charter** (`page:` do frontmatter) e da árvore, não do código de roteamento do Laravel. Quem executar **confere a rota real** em `Routes/web.php` antes de escrever o `daRota`.
- **Não medi** o vetor de estilo das telas de detalhe em nenhuma largura (`D-ALVO-1280` segue pendente) ⇒ **nenhuma destas 8 threads autoriza pixel**; elas mudam **navegação**, não forma.
