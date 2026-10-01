---
sessao: "02"
titulo: Recibo — Casos ConsultaOs (portal do cliente)
autor: "[CL]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 7201ce433
---

# _saida-02 · Casos: ConsultaOs

## Entregue

- `resources/js/Pages/ConsultaOs/Index.casos.md` — 7 UC (UC-COS-01..07), todos derivados do
  charter (Goals, Non-Goals, Anti-hooks), nenhum do `.tsx`.
- `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` — 1 `it()` por UC, sem banco
  (portal mock-only, rota pública), com caso discriminante (UC-02), controle positivo da
  varredura (UC-04), anti-vácuo nas rotas (UC-06) e controle de formato válido (UC-07).
- `.github/ci-sqlite-pest.list` — +1 linha para o teste rodar na lane `PHP / Pest (Unit)`.
  ⚠️ Fora do `prefixo` da thread: sem ela o teste ficava fora de toda lane de PR (os 11 testes
  do módulo estão fora do PR, medido com `test-lane-coverage.mjs`) e o G-7 nunca teria veredito.
- `Index.tsx` não tocado (`nao_toca`).

## Provas do json

- `arquivo resources/js/Pages/ConsultaOs/Index.casos.md` — existe.
- `node scripts/casos-coverage-guard.mjs` → sem violação nova (débito −16 vs baseline).
- `npm run screen:files -- ConsultaOs/Index` → trio completo, 7 UC citados, nenhum órfão.
- `node scripts/qa/uc-id-lint.mjs` → 0 ids fora do formato.

## Pendente / decisão [W]

1. **"Sem CTA loud"** (pedido da ficha) — não tem fonte no charter. Ficou como `[BACKLOG]` no
   casos. Achado lido no código, não medido em tela: os dois botões "Voltar à consulta" usam
   `variant="default"` (primário). Se [W] quiser o Non-Goal, ele entra no charter primeiro.
2. **Copy em PT-BR** — o charter pede "sem jargão técnico" mas não fixa texto; sem texto
   contratado o teste leria o `.tsx`. `[BACKLOG]` no casos. A parte da API (404 sem texto técnico)
   está no UC-COS-03.
3. **O que o público pode ver** — charter §Pendências ainda aberto. O mock devolve nome de
   contato, vendedor e designer. UC-COS-04 trava só o que o charter já proíbe.
4. **Teste antigo do módulo** (não tocado, fora do prefixo): `Modules/ConsultaOs/Tests/Feature/CustomerJourneyTest.php`
   espera `client = 'Acme Comércio Ltda'` e o mock tem `'Acme Comercio Ltda'` — falharia se
   rodasse; e usa `->not->toHaveKey($chave, $msg)`, forma que passa sempre (§5 2026-09-22). Não
   roda em lane de PR hoje.
5. Charter não ganhou link para o casos (só o casos linka o charter) — mudança de charter fica
   para quando ele for a `live`.

## PR

Ver corpo do PR desta thread (branch `claude/lote-trio-medida-thread-02`).
