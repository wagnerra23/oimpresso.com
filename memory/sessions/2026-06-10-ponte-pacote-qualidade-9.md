# Sessão 2026-06-10 (e) — Ponte zero-toque: PACOTE QUALIDADE-9 + OS funcional pro [CL]

**Pedido [W]:** "pode gerar para code — CSS organizado profissionalmente, remover duplicatas, tudo nota 9 acima, protocolo para OS funcionar. O que vai nesse pacote?"

## O que foi feito
- **`prototipo-ui-patch/PROMPT_PARA_CODE_PACOTE-QUALIDADE-9-OS.md`** — prompt único, 4 PRs em série (ordem: PR-1 → PR-3 → PR-2 → PR-4):
  - **PR-1 OS funcional:** port OS-V2-1/2 (F2 APROVADO 06-09) + V2-3/4 (F2 = screenshot staging pós-port) pro `ServiceOrderRichSheet`; upload de fotos (Modules/Arquivos); controller `contacts` + `contact_id` nullable; `printSaleReceipt` espelha fix do print; label 'Caçambas'→Oficina; backfill labels FSM (keys Martinho = Tier 0 intocável). Critério: caminho Larissa completo (criar→DVI→foto→aprovar→executar→imprimir) com teste Pest-browser.
  - **PR-2 CSS profissional:** consolidar bundles duplicados (fin × canon-fin, ⚠validar no main), CSS morto via analyzer #2210 (1 família/PR), padrão único por tela (header de escopo + ordem layout→componentes→estados→responsivo→print), allowlist e baselines SÓ DESCEM.
  - **PR-3 gates novos:** papel de token no conformance-gate (`-fg` em background = 🔴) + espelho G1–G6 em Pest-browser (G2 accentColor, G3 papel, G4 overflow com estado ABERTO) com controle-negativo obrigatório.
  - **PR-4 régua ≥9:** score-mechanized + module-grades → tela <9 entra na TELAS_REVIEW_QUEUE com gap nomeado; identidade única (accent fora do roxo = conformar).
- **5 URLs públicas** dos arquivos de referência do protótipo injetadas (forms/page/css/print/qa-conformance) + URL do próprio prompt. Validade ~1h — regenero se expirar.
- new_design_memories no prompt: decisão piso-9, anti-padrão -fg-como-superfície, golden probe G1–G6.

## Decisões
- Nenhuma nova — pacote executa decisões já tomadas ([W] 06-09 aprovo OS-V2; [W] 06-10 piso 9 + dedup). [CL] valida tudo contra o main (§10.4).

## Erros + correção
- Nenhum novo nesta sessão.

## Residual
- [W] cola o prompt no Code (1 vez). NÃO afirmo que está commitado — o Code resolve com este pedido.
- Se URLs expirarem antes do paste: [CC] regenera via get_public_file_url.

## Refs
`prototipo-ui-patch/PROMPT_PARA_CODE_PACOTE-QUALIDADE-9-OS.md` · sessões 06-10 (a)–(d) · ADR 0265 · PR #2477
