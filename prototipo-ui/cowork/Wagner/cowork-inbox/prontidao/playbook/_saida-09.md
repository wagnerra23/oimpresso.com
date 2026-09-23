---
sessao: "09"
titulo: Scorecard · Patrimonio (5 telas)
executor: "[CC]"
base: 317e1b4ec33
---
# _saida 09

## Checklist
1. ✅ Pré-Flight das 5 telas: os 5 charters existem (todos `status: draft`), com casos.md e controller conferidos — o PARAR SE de charter ausente não disparou
2. ✅ 5 scorecards com o slug exato da espec · 3. ✅ `baseline_anterior` = a própria nota
4. ✅ 16 dimensões por tela, cada uma com evidência `arquivo:linha` · 5. ✅ gaps com `best_of_class` + `fix`
6. ✅ só o prefixo tocado (zero `.tsx`, zero charter, zero git)

## Notas

| tela | arquétipo | nota | nível | dimensão mais fraca |
|---|---|---:|---|---|
| `Patrimonio/Index` | dashboard (PT-04) | **80** | Advanced | affordance 70 |
| `Patrimonio/Alocacoes` | list (PT-01) | **78** | Advanced | mobile_fit 66 |
| `Patrimonio/Configuracoes` | config | **78** | Advanced | error_recovery 64 |
| `Patrimonio/Manutencoes` | list (PT-01) | **77** | Advanced | affordance 66 |
| `Patrimonio/Bens` | list (PT-01) | **75** | Advanced | affordance 55 |

Persona `misto` nas cinco: nenhum charter declara `personas_alvo`, e `personas-por-modulo.yml` não tem entrada
`patrimonio` nem `assetmanagement`. Pesos iguais, então a nota é a média das 16 dimensões.

## As notas vêm da leitura do código, não de medição em prod

Nenhum browser foi aberto em produção. Li o `.tsx`, o charter e o controller de cada tela. Os YAMLs dizem isso
no cabeçalho e no campo `medicao: leitura-de-codigo`. O PARAR SE de tela que não abre em prod
(404/500) **não foi verificado**: a thread não fez smoke.

## O achado que atravessa três telas: os links de ação levam a uma página em branco

**Isto é leitura de código, sem medição em runtime.** Cinco métodos de controller só devolvem a view dentro de
`if (request()->ajax())`, e as views são fragmentos de modal sem `@extends`:

- `AssetController::create` (`:484`) e `::edit` (`:552`)
- `AssetAllocationController::create` (`:315`)
- `AssetMaitenanceController::create` (`:305`) e `::edit` (`:395`)

Views conferidas: `asset/create`, `asset/edit`, `asset_allocation/create`, `asset_maintenance/create` e
`asset_maintenance/edit`, todas com 0 `@extends`.

Numa navegação direta, esses métodos devolvem corpo vazio. As telas que apontam `<a href>` para eles:

- **Bens**: 5 destinos — alocar, manutenção, editar, "Novo ativo" e o CTA do vazio
- **Index**: "Adicionar recurso"
- **Manutencoes**: editar

A **Alocacoes** mediu esse mesmo padrão e recusou as ações de linha (`Alocacoes.tsx:23-35`). O próprio `Index.tsx`
recusou o destino do "Alocar recurso" pelo mesmo motivo (`:334-340`), mas manteve "Adicionar recurso"
apontando para `/asset/assets/create`. A conferência em runtime é o passo 4 do agente, fora desta thread.

## Saída do readiness (`node scripts/qa/prototipo-readiness.mjs`)

| | antes | depois |
|---|---|---|
| ✅ PRONTAS | 59 | **64** |
| Patrimonio/* | 5 × `falta: scorecard` | 5 × PRONTA |

## Catraca (`node scripts/qa/screen-grades-ratchet.mjs`)

```
Catraca screen-grade · 197 telas · ✅ 192 ok/subiu · ✨ 5 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```
rc=0.

## Divergências, declaradas

- **Charter da Alocacoes × .tsx.** Os Goals do charter (`:50-51`) ainda prometem "editar · devolver · excluir" por
  linha, e o `.tsx` removeu as três com justificativa medida. Pela regra de precedência, quem perde é o charter.
  Registrei como gap. Não corrigi porque o charter está fora do prefixo.
- **Título da página.** Só o Painel passa `title` ao `AppShellV2`; as outras quatro ficam sem `<Head title>`
  (WCAG 2.4.2). Registrado como gap em cada uma.
- **Escopo da "Prova".** A espec pede `_saida-09.md`. Este arquivo mora em `prototipo-ui/cowork/Wagner/`, que é o
  espelho de leitura (ADR 0374). Segui o molde da `_saida-01.md`, que também mora lá.

## Escopo
Os 5 YAMLs em `memory/governance/scorecards/screens/patrimonio-*.yaml`, mais este recibo. Os gaps ficam no
YAML e não viraram task: a decisão é do [W].
