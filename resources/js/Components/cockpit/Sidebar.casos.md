---
casos: Sidebar — menu do shell (corpo, topo, rodapé, modos)
irmaos: Sidebar.charter.md (lei) · ../../Layouts/AppShellV2.casos.md (largura/auto-rail)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: a sidebar aparece em TODA tela — regressão aqui atinge o ERP inteiro de uma vez.
owner: wagner
last_run: "2026-09-25"
---

# Casos de Uso & Aceite — `Sidebar`

> **Status:** ✅ teste existe e passou na medição de 2026-09-25 · 🧪 teste cita o UC e passa ·
> ⬜ sem teste que prove · ⏳ pendente (thread do playbook ainda não entregue).
>
> ⚠️ **Fora do `casos-gate`.** O guard lista telas por `raizesDePages()`, e este arquivo mora em
> `Components/cockpit/`, então nenhum UC daqui é cobrado pelo G-2. Os testes abaixo **não citam
> os ids** (nasceram antes deste arquivo). ✅ quer dizer: rodei o teste nomeado e ele passou. Não
> quer dizer que um gate o exija.
>
> **Medição de 2026-09-25:** `npx vitest run` nas 9 specs da sidebar → **49/49** com o #7962
> aplicado (sem ele, 43/49: o rodapé montado fora do Inertia quebrava 6 casos, regressão do
> #7960). ⚠️ **Nenhuma lane de CI roda essas specs** (`git grep` em `.github/` e `package.json`
> = 0). Hoje, só quem roda à mão enxerga uma regressão aqui.

---

## Corpo

### UC-SB-01 · Grupos na ordem canon, PLATAFORMA por último e fechado ✅
- **Aceite:** Dado o menu montado · Então os grupos seguem a ordem de `SIDEBAR_GROUPS`,
  PLATAFORMA vem depois de SISTEMA, sem hue, e nasce fechado (os canon nascem abertos).
- **Teste:** `tests/js/sidebar-plataforma-forja.test.tsx` (6 casos).

### UC-SB-02 · "Visão geral" fica no topo, fora dos grupos ⬜
- **Aceite:** a entry de landing (`group: landing`) não cai em grupo nenhum e aponta pra
  `/dashboard-legacy`.
- **Teste:** `tests/Feature/Sidebar/VisaoGeralLandingTest.php` (Pest, lane sqlite). Prova o
  **contrato** do adapter, não o render. Não rodei aqui (PHP só no CI/CT 100).

### UC-SB-03 · O usuário vê onde está ✅
- **Aceite:** Dado que a rota atual está num grupo fechado no localStorage · Quando a tela abre ·
  Então o grupo abre sozinho **sem gravar** a preferência, e só o item ativo leva
  `aria-current`. Na sub-tela, o ghost leva `aria-current` e o pai não duplica.
- **Teste:** `tests/js/sidebar-item-ativo.test.tsx` (5 casos, com controle negativo).

### UC-SB-04 · Sub-telas com teto, sem esconder a ativa ✅
- **Aceite:** Dado um item com mais de 5 sub-telas e a ativa além da 5ª · Então a ativa aparece
  na 5ª vaga sem clique; "mais N" abre tudo e "mostrar menos" volta ao teto.
- **Teste:** `tests/js/sidebar-ghosts-teto.test.tsx` (4 casos).

### UC-SB-05 · Atalho de 2 teclas `G X` ✅
- **Aceite:** o item com atalho declarado mostra a dica no slot `.sb-item-end`. Colisão
  (dois itens com o mesmo atalho) não mostra dica em nenhum dos dois.
- **Teste:** `tests/js/sidebar-atalho-render.test.tsx` (render da dica). O formato do atalho
  é travado em `tests/Feature/Sidebar/SidebarMenuItemContractTest.php`. A navegação por
  teclado (`useSidebarShortcut`, janela de 1,5 s) **não** tem teste de comportamento ⬜.

### UC-SB-06 · Sub-tela com ícone próprio ⏳
- **Aceite:** ghost com `icon` declarado mostra o ícone; sem `icon`, a linha fica sem ícone.
- **Pendente:** thread 14 do playbook.

## Rail e modos

### UC-SB-07 · No rail, o grupo se reconhece pelo ícone e mostra onde estou ✅
- **Aceite:** o botão do grupo usa o ícone do **grupo**; o grupo que contém a tela atual fica
  `.active`; a dica é camada fixa no hover/foco e some quando o flyout abre.
- **Teste:** `tests/js/sidebar-rail.test.tsx` (5 casos).

### UC-SB-08 · Largura automática e escolha manual 🧪
- Coberto em `../../Layouts/AppShellV2.casos.md` (UC-SHELL-01..04,
  `tests/Browser/Shell/SidebarAutoRailTest.php`). Não duplicado aqui.

### UC-SB-09 · Modo oculto tem volta ⬜
- **Aceite:** no modo `hidden`, a alça "Mostrar sidebar" aparece e devolve o menu.
- **Sem teste de comportamento.** Entregue pela thread 04. A copy é travada no contrato de tela
  (`sb-alcas`), que não prova o clique.

## Topo

### UC-SB-10 · Seletor de empresa anuncia menu e estado ✅
- **Aceite:** o botão se nomeia pela empresa atual, `aria-expanded` alterna, cada empresa é
  `menuitemradio`, e só a ativa fica `aria-checked`.
- **Teste:** `tests/sidebarMenuSemantics.spec.tsx` (bloco "seletor de empresa", 7 casos).

## Rodapé (menu da conta)

### UC-SB-11 · Sair pede confirmação ✅
- **Aceite:** Sair é um botão, não link. Clicar abre a pergunta inline (Encerrar · Cancelar);
  Cancelar e clique fora não encerram.
- **Teste:** `tests/sidebarSair.spec.tsx` (4 casos). ⚠️ Esteve vermelho no `main` entre o
  #7960 e o #7962.

### UC-SB-12 · Aparência, modo de trabalho e Buscar tela ✅
- **Aceite:** Aparência tem 2 opções (Escuro primeiro), e o ✓ segue a escolha mesmo com a prop
  velha. O trigger do modo mostra o valor atual. "Buscar tela" dispara o ⌘K da paleta e fecha o
  menu. O atalho morto `⌘/` não aparece.
- **Teste:** `tests/sidebarAparencia.spec.tsx` (7 casos).

### UC-SB-13 · Presença clicável e persistida ✅
- **Aceite:** 4 estados na ordem do protótipo. O trigger mostra o atual, e clicar grava em
  `users.ui_presence` por `POST /user/preferences/presence`. Valor fora do enum dá 422.
- **Teste:** `tests/Feature/Sidebar/presenca.spec.tsx` (render, 4 casos ✅ medidos) +
  `tests/Feature/Sidebar/PresencaPreferenciaTest.php` (rota, lane sqlite, verde no CI do #7960).
