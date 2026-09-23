---
sessao: "_saida-01"
thread: "01 · Aparência: a cascata de tema abre e escolhe"
dono: "[CL]"
data: 2026-09-23
prefixo_tocado: resources/js/Components/cockpit/Sidebar.tsx · tests/sidebarAparencia.spec.tsx · tests/sidebarMenuSemantics.spec.tsx
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-01

## 0 · A premissa do playbook estava ERRADA — medido antes de editar

O playbook diz *"o botão não tem handler; o usuário clica e nada acontece"*. **Falso no vivo**,
e falso também no `base_lido` dele: o `ThemeSubpanel` existe desde `77e0d74be` (2026-05-05) e
estava presente em `6fc8b8fac31d` (3 ocorrências em `git show 6fc8b8fac31d:<Sidebar.tsx>`).

Medido em **produção**, logado, 2026-09-23, clicando pelo browser: o menu abre, **Aparência abre a
cascata** e mostra `Claro` · `Escuro` (✓, `aria-pressed=true`) · `Sistema`. O que o playbook
descreve é o protótipo antigo, não o `main`.

## 1 · O que sobrou de trabalho real, e foi feito

| delta | antes (main) | depois |
|---|---|---|
| opções | 3 (Claro · Escuro · Sistema) | **2**, Escuro primeiro — protótipo `sidebar.jsx` `TEMAS` (UI-0029) |
| descrição | só o rótulo | "Padrão do balcão" · "Escritório, luz alta" (padrão do `VibesSubpanel`, sem CSS novo) |
| valor no trigger | ausente | `escuro`/`claro` na classe `.kbd` já existente do `.um-item` |
| **✓ depois do clique** | **ficava no tema ANTIGO** | segue a escolha |

A última linha é um defeito que a leitura do código expôs e o teste provou: o ✓ vinha do `mode`
do `useTheme`, que é a prop `auth.user.ui_theme`; o `setTheme` persiste por `fetch` sem reload do
Inertia, logo a prop fica velha até a próxima visita. Conserto: o ✓ segue o `effective` da MESMA
instância do hook que chama `setTheme`; o trigger lê o tema do DOM e recebe a escolha por callback.

### 1b · A 1ª versão deste PR REGREDIU o dark — o VRT pegou (2026-09-23)

A 1ª versão montava o `useTheme` no `SidebarUserMenu`, que está **sempre montado**. Com
`ui_theme` null na prop, o efeito de montagem do hook re-sincroniza com o SO e **tira o `.dark`**
que o servidor/anti-flash aplicou. O `visual-regression` (run `35911653090`, step "Estados isolados
matriz") reprovou `estado=dark` em 5 telas (diff 74–85%). Medido decodificando as imagens do
artifact: baseline com luminância média **37** (escura) e render atual com **215** (clara). Em
produção, o mesmo caminho pega quem tem `ui_theme` null e `oi.theme=dark` no localStorage.

Conserto: o hook voltou a montar **só com a cascata aberta**, como no main. Teste de regressão
novo: `ui_theme` null + `.dark` aplicado, abrir o menu mantém dark. A versão regredida faz esse
teste cair (M1 abaixo).

**Retratação:** a 1ª redação deste recibo dizia que o conserto também tirava um reaplicar do `mode`
velho ao reabrir o subpainel. Com o hook de volta ao subpainel, isso **não vale**: é comportamento
pré-existente do main (escolher um tema, fechar e reabrir a cascata na mesma visita reaplica o
`mode` da prop). Fica declarado. Consertar isso exige mexer no `useTheme.ts`, fora do prefixo.

**Um dono só pro estado:** nenhuma chave de `localStorage` nova, nenhum `useState` paralelo. O
teste confere que a escolha escreve `data-theme` no `<html>` e no `.cockpit`, a classe `.dark`, e
faz `POST /user/preferences/theme` com `{theme}`.

## 2 · Decisão tomada do lado de cá (reversível, declarada)

`Sistema` saiu do menu porque o protótipo tem duas opções. Quem tem `ui_theme = null` continua
seguindo o SO até escolher. O ✓ marca o tema efetivo, então nunca fica sem marcação.

## 3 · Provas

- `npx vitest run tests/sidebarAparencia.spec.tsx tests/sidebarMenuSemantics.spec.tsx`: **14/14** (depois do conserto do dark).
- **Mordida (1ª versão):** M1 (`Sidebar.tsx` do main) → **3 de 3 caem**; M2 (✓ volta pro `mode` da
  prop) → cai o teste do ✓ com `AssertionError`, não erro de execução.
- **Mordida (conserto do dark):** M1 (a versão que o VRT reprovou) → cai o teste novo de regressão
  (`AssertionError: expected false to be true`, o `.dark` sumiu); M2 (✓ no `mode`) → cai o teste do ✓.
  Restauração conferida por hash nas duas rodadas.
- **Controle positivo** (pedido do playbook): renderizar com `light` guardado abre em `light`
  (trigger `claro`, ✓ em Claro).
- `npm run lint && npx tsc --noEmit` **não sai exit 0 nem no main** (154 erros de lint no repo; 2
  erros de tsc no próprio `Sidebar.tsx`, linhas 434 e 720, pré-existentes). **Delta medido nos
  arquivos tocados: 0 → 0 erros de lint; 2 → 2 de tsc, os mesmos.**

## 4 · O que NÃO foi provado (em voz alta)

- **Runtime em produção do código NOVO:** só depois do deploy. A tentativa de clicar `Claro` em
  produção antes de editar (para ver o ✓ velho no vivo) foi **bloqueada pelo classificador**: ela
  gravaria a preferência do Wagner. A prova de comportamento é jsdom, com clique real no DOM.
- **Persistência real no reload** depende do `POST /user/preferences/theme` (não tocado). O teste
  prova que a chamada sai, não que o servidor grava.
- **Os specs do Sidebar não rodam em nenhuma lane de CI**, nem o `sidebarMenuSemantics` que já
  existia. É resíduo fora do prefixo desta thread.
