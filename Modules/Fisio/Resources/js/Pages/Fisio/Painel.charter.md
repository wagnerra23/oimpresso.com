---
id: modules-fisio-pages-fisio-painel-charter
page: /fisio
component: Modules/Fisio/Resources/js/Pages/Fisio/Painel.tsx
related_prototype: "n/a (herda PT-04 Dashboard; o canvas Prescreve Pro desenha a tela Painel, mas em 2026-10-18 não havia fonte resolvível: 0 arquivos de Fisio/Prescreve em prototipo-ui/ e o DesignSync não estava acessível na sessão que criou a tela — reconciliar quando o canvas descer ao espelho)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio
  rota_nome: fisio.painel
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio (DRAFT · painel do profissional)

> Nasce na US-FISIO-010 (ADR 0437 §4 e §5; HANDOFF-F1 T-10). Casos: [`Painel.casos.md`](Painel.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao fisioterapeuta, a partir do que os pacientes registraram no portal, a adesão dos últimos 7 dias, a dor e quem precisa de atenção hoje.

## Goals — Features (faz)

- KPIs: adesão média 7d (sem quem não tem programa ativo), pacientes ativos (sem bloqueados), dor média (último registro de cada um) — UC-FPN-02.
- Duas filas separadas: **Alerta** (`dor_subiu`, `dor_alta`, `sinal_de_alerta`) e **Risco de abandono** (`sem_registro`), com o motivo em texto — UC-FPN-03, UC-FPN-04.
- Tabela com adesão, última dor, último registro, nível e sequência derivados — UC-FPN-05.
- Só a empresa da sessão — UC-FPN-06.
- Fallback humano do WhatsApp (etapa 02 T-09e): lista os envios de hoje que não chegaram (falha, fila parada ou canal da clínica caído), com link `wa.me` pré-preenchido; sem consentimento vigente, sem link — UC-FPN-09.
- Estado vazio honesto: sem check-ins, a tela diz isso; nenhum número é inventado.

## Non-Goals — Features (NÃO faz)

- ❌ Sugerir conduta, mudar programa ou falar com o paciente (ADR 0437 §5 — SaMD).
- ❌ Escrever "risco clínico" ou "piora" na interface (etapa 02 §3.7).
- ❌ Pontuação de risco de abandono por regras ponderadas, ações de um toque e "% abriu o portal" (etapa 02 §3.7, T-10a/T-10b): ficam para depois do portal (US-FISIO-007).

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-10) · `etapas/02-f1-constancia.md` §3.7 · PT-04 Dashboard · UI-0013
