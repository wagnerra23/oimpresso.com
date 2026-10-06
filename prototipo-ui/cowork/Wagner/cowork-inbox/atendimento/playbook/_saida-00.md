---
thread: "00"
titulo: PUXAR Caixa Unificada → inbox-page.jsx
dono: "[CC]"
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lido 2026-10-05 20:48 UTC)
veredito: "entregue — topo, broadcast e atalhos puxados de produção; 3 divergências declaradas; a A1 mede a rota inbox."
---

# _saida-00 · Atendimento (Caixa Unificada)

**Lido no `main`:** `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` (28 KB) inteiro. Os 21 `_components/` (≈ 250 KB) **não** — comparei pela Index o que cada um faz e contei a presença no protótipo (5 arquivos `inbox-*.jsx`, 141 KB): filas, tags, contexto, canais, banner de saúde, reconectar, transcrição, apresentação, mobile, favoritos, nota interna, janela 24h já existem.

## Mapa rota ↔ Page (para a A1)
| rota | Page | medida |
|---|---|---|
| `inbox` | `Atendimento/CaixaUnificada/Index` | `targets/medidas/Atendimento--CaixaUnificada--Index/` (existente, não li) |

## O que entrou no build (`inbox-page.jsx`)
1. **Topo igual ao de produção:** Templates · Filas · Canais · **Broadcast** · **Guia** · + Nova conversa. Antes: sem Broadcast no topo (o drawer existia sem botão que abrisse) e "Troubleshooters" + "Trilhas" separados — produção junta os dois no `InboxGuiaDialog`.
2. **Broadcast = fase 1:** o CTA era "Disparar broadcast". Produção (`BroadcastSheet`, ADR 0268) faz pre-flight + **rascunho**; o disparo é fase 2. CTA virou "Salvar rascunho" e o aviso diz isso.
3. **Atalhos:** J/K/E/? já existiam no protótipo (`useInboxKeyboard`). Entraram os 2 que faltavam em relação a produção: `/` foca a busca e A marca aguardando atendente — ignoram campo de texto e teclas modificadoras.

## Só o protótipo tem — [W] decide
| # | o quê | produção |
|---|---|---|
| 1 | **Templates** abre uma biblioteca única (Jana + HSM numa tela — decisão [W] registrada no código) | dropdown com 2 links pra páginas separadas |
| 2 | Contexto auto-aberto em tela ≥1440px | sempre fechado; abre pelo botão |
| 3 | "Selecione uma conversa." sem contagem | mostra "N conversas na caixa" |

## Não fiz
- Não li os 21 `_components` linha a linha. Fidelidade de cada um (composer, lista, contexto) fica para as threads da tela, depois da A1.
- Não medi (é a A1).
