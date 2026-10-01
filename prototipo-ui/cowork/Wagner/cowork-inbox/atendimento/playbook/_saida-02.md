---
sessao: "02"
titulo: Casos — Channels Index + Show (Atendimento)
autor: "[CL]"
data: "2026-09-30"
base: origin/main 7201ce433
---

# _saida-02 · Casos: Channels Index + Show

## Entregue
- `Modules/Whatsapp/Resources/js/Pages/Atendimento/Channels/Index.casos.md` — UC-CNL-01..04.
- `Modules/Whatsapp/Resources/js/Pages/Atendimento/Channels/Show.casos.md` — UC-CNLD-01..05.
- `tests/Feature/Whatsapp/AtendimentoChannelsContratoTest.php` — 1 teste por UC (9), tenant 98 × vizinho 99, schema sintético sqlite.
- `.github/ci-sqlite-pest.list` — +1 linha registrando o teste. Fora do `prefixo` da thread, e necessário: sem ela o arquivo existe mas nenhuma lane o executa (lápide §5 2026-08-02, registrar ≠ rodar).

UCs derivados dos charters + SPEC US-WA-068, não do `.tsx`. Nenhum `.tsx` tocado (`nao_toca` respeitado).

## Provas do json
| prova | resultado |
|---|---|
| `arquivo` `…/Channels/Index.casos.md` | existe |
| `arquivo` `…/Channels/Show.casos.md` | existe |

`casos-coverage-guard` local: sem violação nova; G-2 (UC citado por teste) sem pendência nas duas telas.

## Status dos UCs
Todos `⬜` no commit inicial: a prova de execução é o CI do PR (não rodei Pest local, regra do projeto). Ver o PR para o resultado da lane `PHP / Pest (Unit)`.

## Pendente — decisões [W] (registradas como [BACKLOG] nos casos, sem UC)
1. **Remoção de canal:** charter Index diz *"NÃO permite deletar (apenas soft-disable)"*; `ChannelsController::destroy` faz `delete()` e `Channel` não tem `SoftDeletes`. Um dos dois está errado.
2. **Lista de tipos:** charter proíbe hardcode (*"vem de `ChannelDriverFactory::availableDrivers()`"*); hoje vem de `availableTypesForUi()` no controller.
3. **AuditLog em grant/revoke** (aceite da US-WA-068): o controller só faz `Log::info`.

## Não coberto (sem fonte testável nesta onda)
Re-parear (daemon CT 100), `availableUsers` (depende de permissões Spatie, ausentes no schema sintético), badge de health e empty state (render).

## Errata do índice
Nenhuma.
