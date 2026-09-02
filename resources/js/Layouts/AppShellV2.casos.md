---
casos: AppShellV2 — largura do sidebar (auto-rail responsivo)
irmaos: AppShellV2.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o shell é a moldura de TODA tela — regressão aqui aparece em 144 telas de uma vez.
owner: wagner
last_run: "2026-09-02"
---

# Casos de Uso & Aceite — `AppShellV2` · largura do sidebar

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Fora do `casos-gate`.** Medido em 2026-09-02: o guard lista telas por
> `raizesDePages()` (só `resources/js/Pages/**`), então nenhum UC deste arquivo é
> cobrado pelo G-2. Quem defende estes 4 UCs é
> `tests/Browser/Shell/SidebarAutoRailTest.php`, que cita cada id no título do `it()`.
> Registro isto no arquivo para que ninguém leia a existência dele como cobertura de gate.

---

## UC-SHELL-01 · Numa tela estreita o sidebar já vem recolhido 🧪
- **Persona:** [W] no monitor de **1280px** — a largura em que o header da Forja quebrava
  os controles pra uma 2ª linha porque o conteúdo ficava espremido.
- **Aceite:** Dado um usuário **sem** escolha de sidebar gravada · Quando abre qualquer tela
  do shell numa viewport de largura **≤ 1280px** · Então o sidebar ocupa **56px**
  (`grid-template-columns` da `.cockpit` começa em `56px`) e `data-sidebar="rail"`.
- **Teste:** `tests/Browser/Shell/SidebarAutoRailTest.php` — `UC-SHELL-01`, 2 asserções
  (estado do React + coluna resolvida pelo browser).
- **Regressão que defende:** conteúdo espremido a 1280 — o pedido que originou a ADR UI-0030.

## UC-SHELL-02 · Numa tela larga o sidebar continua legível 🧪
- **Persona:** Eliana no desktop 1440 — quer o menu com rótulo, não só ícone.
- **Aceite:** Dado um usuário **sem** escolha gravada · Quando abre o shell numa viewport
  **> 1280px** · Então o sidebar ocupa **260px** e `data-sidebar="expanded"`.
- **Teste:** `tests/Browser/Shell/SidebarAutoRailTest.php` — `UC-SHELL-02`, 2 asserções.
- **Regressão que defende:** rail vazando pra telas largas (seria o inverso do pedido).

## UC-SHELL-03 · A escolha do usuário manda, em qualquer largura 🧪
- **Persona:** qualquer pessoa que recolheu/expandiu o sidebar de propósito (alça ou `⌘\`).
- **Aceite:** Dado que o usuário escolheu explicitamente um modo (gravado em
  `oimpresso.sb.mode`) · Quando a janela muda de largura, inclusive cruzando 1280 · Então o
  modo escolhido **permanece** — a largura não o sobrescreve.
- **Teste:** `tests/Browser/Shell/SidebarAutoRailTest.php` — `UC-SHELL-03`, 3 asserções
  (pré-condição + estado + coluna após estreitar).
- **Regressão que defende:** o automático "roubar" a preferência do usuário — que é o defeito
  simétrico e mais irritante do que não ter automático nenhum.

## UC-SHELL-04 · Sem escolha, o shell acompanha a janela ao vivo 🧪
- **Persona:** [W] plugando/desplugando o monitor externo no meio do expediente.
- **Aceite:** Dado um usuário **sem** escolha gravada · Quando a viewport passa de 1440 → 1280
  → 1440 **sem recarregar** · Então o sidebar vai 260px → 56px → 260px.
- **Teste:** `tests/Browser/Shell/SidebarAutoRailTest.php` — `UC-SHELL-04`, 3 asserções.
- **Regressão que defende:** decidir só no mount (é o que o protótipo faz) e deixar o shell no
  modo errado até um F5 — delta deliberado vs a fonte, registrado na ADR UI-0030 §Delta.

---

## [BACKLOG] — prosa honesta, sem id (viram UC quando ganharem teste)

- O protótipo tem um 3º modo `hidden` (`⌘⇧\`) que produção não porta. Se um dia portar, o
  auto-rail precisa dizer o que faz quando a escolha gravada é `hidden` numa tela estreita.
- Mobile (≤768px): o drawer abre sempre expandido. Não há caso escrito para a transição
  1280 → 768 → 1280.
