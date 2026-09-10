---
sessao: "02"
titulo: Seção MODOS — auto-rail por largura (UI-0030) e persistir só a escolha manual
dono: "[CC]"
base: af09f7c3a0fd
prefixo: prototipo-ui/cowork/app.jsx
nao_toca: sidebar.jsx · styles.css · Components/cockpit/**
depende: — (vaga 1). Produção está à frente: 🔵 PUXAR, não inventar. Acumula o corte do estado morto do Chat (RESÍDUO-2, §C-bis) — coordenar com a 01.
---
# 02 · Seção MODOS (expanded · rail · hidden)

## A · Identidade — ancoragem dupla
- **alvo:** o comportamento **do vivo**, não do protótipo. Esta é uma thread de PUXAR.
- **âncora (código, lido em `af09f7c3a0fd`):** `resources/js/Layouts/AppShellV2.tsx`
  - inicialização: `localStorage.getItem(LS.SB_MODE)`; **se não houver escolha**, `window.matchMedia(AUTO_RAIL_MQ).matches ? 'rail' : 'expanded'` (ADR UI-0030);
  - `useEffect` com listener de `change` no mesmo mq: enquanto **não** houver escolha manual, o modo acompanha a largura ao vivo (plugar/desplugar monitor não deixa o shell no modo errado);
  - `chooseSidebarMode(v)` — **só ela grava** no `localStorage`; `toggleSidebarMode` passa por ela.
- **Valor lido no `main` (`shared.ts`, 2026-09-10):** `AUTO_RAIL_MAX_W = 1280` · `AUTO_RAIL_MQ = "(max-width: 1280px)"` — **1280 INCLUSIVE**. O nosso build usa `innerWidth < 1280` (rail só a partir de 1279): **a 1280px exatos o vivo nasce em rail e o protótipo nasce expandido.** A ADR UI-0030 justifica o inclusive: a `cockpit.css` já trata `@media (max-width: 1280px)` como a banda estreita (onde o painel Linked colapsa), e 1280 é a largura do monitor do [W] — dois limiares de "estreito" no mesmo shell seria drift. **Corrigir o build pro inclusive; não inventar breakpoint.**
- **Chave de storage diverge:** vivo `LS.SB_MODE = "oimpresso.sb.mode"` × build `"oimpresso.sidebar.mode"`. A paridade exigida é de **comportamento**; se trocar a string, migrar/descartar a chave antiga explicitamente e dizer no `_saida` (storage é do [W]).

## B · O defeito do nosso build (com dono nomeado)
`app.jsx:625-635` grava **todo** valor de `sbMode` num `useEffect([sbMode])` — inclusive o automático. O comentário do vivo diz isto com todas as letras e cita a medição: *uma chave `rail` sobrevivente de um run a 1279px manteve o protótipo em rail a 1728px, em 2026-09-02, e quase virou "o protótipo é rail a 1728" na comparação daquele dia.* É defeito nosso, medido, e é o que esta thread fecha.

## C · O que fazer
1. Ler `AUTO_RAIL_MQ` no `main` e usar **o mesmo** media query.
2. Estado inicial: chave persistida vence; sem chave → largura decide.
3. Trocar o `useEffect` que grava por um `escolherModo(v)` chamado **só** nas ações do usuário (alça `.sb-collapse-handle`, ⌘\\, ⌘⇧\\).
4. Acrescentar o listener de `change` que segue a largura **enquanto não houver chave**.
5. `hidden` continua sendo escolha manual apenas (⌘⇧\\) — nunca automático.
6. Mobile (≤768px) intocado: `app.jsx` já força `expanded` no drawer, igual ao vivo.

## C-bis · Cortar o estado do Chat morto (RESÍDUO-2 — respondido 2026-09-10: remover)
Medido nesta data: `SidebarTabs`/`SidebarChat`/`ConvRow` (em `sidebar.jsx`) e **`ChatPage` (`app.jsx:72`)** têm **zero call sites** — a rota `chat` renderiza `window.JanaPage`. A thread 01 remove o JSX/CSS do lado da sidebar; **esta corta o estado na origem**, porque `app.jsx` é o prefixo dela (Lei 1):
- `const [tab, setTab]` (l.549) e `tab={tab} onTab={setTab}` no `<Sidebar>` (l.954);
- `const [activeConvId, setActiveConvId]` (l.577) + `handleSelectConv` (l.793) + as props (l.955);
- os `useEffectA` de persistência **órfãos**: `oimpresso.sidebar.tab` (l.680) e `oimpresso.conv` (l.682) — estado que ninguém lê, gravando a cada mudança;
- em `handleSelectRoute`, a linha `if (r === "chat") setTab("chat")` (l.783);
- `ChatPage` + `ConvTabsBar`, e `Thread`/`LinkedAppsPanel` **só se não sobrar consumidor** — conferir por busca, não presumir.
**Não apagar as chaves já gravadas** no `localStorage` do [W] sem dizer no `_saida`: parar de escrever ≠ limpar.

## D · Não inventar
- Nome da chave permanece `oimpresso.sidebar.mode` (o vivo usa `LS.SB_MODE`; a paridade é de comportamento, não de string).
- Não migrar/limpar chave existente do usuário sem dizer no `_saida` — storage é do [W].

## Execução
```
ARQUIVOS A EDITAR : prototipo-ui/cowork/app.jsx (só o bloco "Sidebar: modo expanded | rail | hidden")
MEDIR             : com localStorage limpo, abrir em largura acima e abaixo do AUTO_RAIL_MQ e conferir o modo;
                    depois escolher manualmente e reabrir na outra largura — a escolha tem de vencer
PASSO A PASSO     : 1) ler AUTO_RAIL_MQ no main  2) init por chave→largura (1280 INCLUSIVE)  3) escolherModo()  4) listener
                    5) cortar o estado morto do Chat (§C-bis)  6) 4 casos medidos (sem chave × com chave) × (estreito × largo)
                    7) a app monta sem prop faltando no Sidebar  8) _saida-02.md
PARAR SE          : AUTO_RAIL_MQ não existir mais em shared.ts → parar e reportar (não escolher breakpoint à mão)
                    a 01 ainda não tiver removido o JSX → cortar as props aqui quebra o Sidebar: faça a 01 primeiro
```

## Prova
- `app.jsx` contém `matchMedia` no bloco de sidebar e **não** grava `sbMode` num efeito disparado por toda mudança.
- `app.jsx` sem `oimpresso.sidebar.tab`, sem `oimpresso.conv`, sem `function ChatPage`.
- `_saida-02.md` com a matriz de 4 casos medidos e o valor literal do `AUTO_RAIL_MQ` lido do `main`.
