---
id: requisitos-superadmin-pagar-visual-comparison
title: "Comparação design × produção — superadmin/Pagar/Index"
module: Superadmin
tela: superadmin/Pagar/Index
owner: W
status: rascunho
last_validated: "2026-11-16"
---

# Comparação design × produção — `superadmin/Pagar/Index` (Pagar assinatura)

> **Âncora computada** (`node scripts/design/ancora.mjs superadmin/Pagar --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/superadmin-pagar-page.jsx`, declarada em `related_prototype` do charter.
>
> Estado em **2026-11-16: NÃO MEDIDO.** Nenhuma dimensão tem veredito. Nada aqui pode ser lido como "igual".

## Por que não foi medido

1. **O protótipo vive dentro de uma shadow root.** `superadmin-pagar-page.jsx` não é um componente
   React do shell: é HTML literal guardado em `window.PROTO_HTML["superadmin-pagar"]`, que o
   `prototipo-html-page.jsx` monta numa shadow root (o próprio cabeçalho do arquivo diz isso). A sonda
   `design-diff.mjs --probe` consulta `document.querySelector…`, que não atravessa shadow root — o lado
   protótipo sairia vazio e a comparação mediria nada contra a produção.
2. **A tela React de produção está atrás da flag `useV2SuperadminPagar`** (UC-SAPG-01/02). Sem a flag
   ligada para o negócio, `/subscription/{id}/pay` serve a Blade. O estado da flag em produção não foi
   lido nesta rodada, e ligar flag em produção para medir não é ato desta tarefa.

## O que destrava

- Sonda que entre na shadow root (o dono é `scripts/design/design-diff.mjs`; estender lá, não criar
  outra), ou o protótipo desta tela virar componente do shell como os demais do Superadmin.
- Um tenant com a flag ligada para medir o lado produção (em CI o teste de navegador
  `tests/Browser/Superadmin/SuperadminTelasAssinanteTest.php` liga a flag por stub e prova o render, mas
  não mede forma).
