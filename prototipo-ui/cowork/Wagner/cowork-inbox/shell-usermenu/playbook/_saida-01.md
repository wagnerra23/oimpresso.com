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
Inertia, logo a prop fica velha até a próxima visita. Conserto: **uma** instância do `useTheme` no
menu, passada ao subpainel, e o ✓ segue o `effective`. Efeito colateral bom: o subpainel remontava
o hook a cada abertura, e o efeito dele reaplicava o `mode` velho.

**Um dono só pro estado:** nenhuma chave de `localStorage` nova, nenhum `useState` paralelo. O
teste confere que a escolha escreve `data-theme` no `<html>` e no `.cockpit`, a classe `.dark`, e
faz `POST /user/preferences/theme` com `{theme}`.

## 2 · Decisão tomada do lado de cá (reversível, declarada)

`Sistema` saiu do menu porque o protótipo tem duas opções. Quem tem `ui_theme = null` continua
seguindo o SO até escolher. O ✓ marca o tema efetivo, então nunca fica sem marcação.

## 3 · Provas

- `npx vitest run tests/sidebarAparencia.spec.tsx tests/sidebarMenuSemantics.spec.tsx`: **13/13**.
- **Mordida:** M1 (`Sidebar.tsx` do main) → **3 de 3 caem**; M2 (✓ volta pro `mode` da prop) → cai
  o teste do ✓ com `AssertionError: expected 'false' to be 'true'`, não erro de execução. Restauração
  conferida por hash.
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
