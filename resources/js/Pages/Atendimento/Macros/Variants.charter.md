---
page: /atendimento/macros/{macro}/variants
component: resources/js/Pages/Atendimento/Macros/Variants.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Whatsapp
related_us: [US-WA-049]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /atendimento/macros/{macro}/variants (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Whatsapp/Http/Controllers/Admin/MacroVariantsController@index` (rota `atendimento.macros.variants.index`, permissão `whatsapp.settings.manage`). Gestão de variantes A/B do body de uma macro (pattern Take Blip de teste multivariado de template).

---

## Mission
Permitir que o atendente crie e compare variantes A/B do corpo de uma macro. Cada variante tem rótulo, body override, peso (distribuição ponderada) e flag ativa; o sorteador escolhe uma no envio conforme o peso, e a taxa de resposta (respostas/envios) mostra qual performa melhor pra eleger uma vencedora.

---

## Goals — Features (faz)
- Lista as variantes da macro em tabela (deferred): rótulo, body, peso, envios, respostas, taxa de resposta e status ativa/inativa.
- Cabeçalho de contexto da macro: shortcut, body padrão e contagem de variantes ativas (com aviso quando 0 ativas → apply usa body padrão).
- Criar/editar variante em modal (rótulo, body até 4096 chars, peso 0-100 via slider, flag ativa) — `store`/`update` via `router`.
- Marcar vencedora: desativa as outras e mantém histórico (`mark_winner`).
- Remover variante com confirmação (preserva histórico de uso) — `destroy`.
- Empty state com CTA "Criar primeira variante".
- Botão "Voltar" pra lista de macros (`atendimento.macros.index`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita a macro-mãe (label/shortcut/body padrão) — isso é na tela de Macros — inferência pendente de Wagner.
- ❌ Não faz o sorteio ponderado em si (é o `MacroVariantPicker` no apply) nem envia mensagens desta tela — inferência pendente de Wagner.
- ❌ Não expõe variantes de macros de outro `business_id` — `findOrFail` scopado + confere FK `macro_id` em cada operação (Tier 0 ADR 0093).
- ❌ Não zera/edita as métricas `sent_count`/`response_count` manualmente.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- `variants` carregado via `Inertia::defer` no controller — query + map pulam execução quando o partial reload não pede (skill `inertia-defer-default`, D-14).
- Peso normalizado no submit (clamp 0-100) e no backend.
- Marcar vencedora automaticamente desativa as demais variantes e ajusta peso (via `mark_winner`), preservando histórico.
- `response_rate` calculada por variante a partir de envios/respostas (tracking 24h fora desta tela).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Remover e marcar vencedora exigem `confirm` explícito — nenhuma ação destrutiva silenciosa.
- ❌ Não envia mensagem nem aplica variante ao abrir a tela.
- ❌ Não muta dados em GET — todas as mutações são POST/PUT/DELETE nomeadas.
- ❌ Não apaga o histórico de uso ao remover uma variante (é preservado).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar regra de soma de pesos (não há validação de que os pesos ativos somem 100)
