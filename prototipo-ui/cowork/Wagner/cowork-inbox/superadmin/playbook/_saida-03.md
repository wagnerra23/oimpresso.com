---
sessao: "_saida-03"
thread: "03 · Pacotes: create/edit → drawer (Pacotes/Index)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main 5f43e759cb
---
# _saida-03

## Decisão [W] (2026-10-05, no chat) — preço
A thread esbarrava na decisão pendente do RUNBOOK-pacotes §5.1: `store()` lia o preço pelo
`num_uf` (parser pt-BR) e `update()` gravava o texto cru. Aprovado: o drawer manda sempre ponto e
2 casas, e `update()` passa a usar o mesmo `num_uf` do `store()`. **Os pacotes já gravados não
mudam** (sem migração).

## Entregue
- `Modules/Superadmin/Http/Controllers/PackagesController.php`
  - `create()` → `/superadmin/packages?pacote=novo`; `edit($id)` → `?pacote=<id>` (e passa a
    exigir `superadmin`, que a Blade de edição não checava).
  - `index()` ganha `editando` e `formPacote` (deferido; só com `?pacote`): os valores do pacote
    (edição) e o catálogo de módulos liberáveis — `getModuleData('superadmin_package')` sem
    argumento ao criar e com `true` ao editar, como cada Blade fazia. Id inexistente → `null`.
  - `update()`: o preço passa pelo `num_uf` (decisão acima).
- `Pacotes/_components/PacoteForm.tsx` (novo): seções do protótipo (Identidade e preço · Limites ·
  Módulos liberados · Visibilidade) mais o que a Blade tinha e o protótipo não desenha — ordem na
  vitrine, link personalizado e, ao editar, "Aplicar às assinaturas vigentes"
  (`update_subscriptions`). Módulo com `field_type` vira campo de texto; sem ele, botão liga/desliga.
- `Pacotes/Index.tsx`: botão "Novo pacote" no cabeçalho (tecla `n`), "Editar" em cada card,
  drawer como estado da grade, `esc` fecha. Cabeçalho migrado para o PageHeader canon (ratchet).
- Apagados: `Resources/views/packages/create.blade.php` e `edit.blade.php`.
- Casos UC-SAPAC-09 (create/edit redirecionam), 10 (opções só com o drawer aberto) e 11 `[T0]`
  (criar e editar gravam o mesmo preço), com teste em `SuperadminPacotesContratoTest.php`.
- Derivados: `SUPERFICIE.md` regerado; `config/pageheader-shared-baseline.json` 59 → 58.

## Preço — dupla prova (regra mestre de valor)

**Caminho 1 — o que o front envia** (medido rodando `parseDecimalPtBR` do
`resources/js/Lib/numberPtBR.ts` no Node, com a mesma conversão do `PacoteForm`):

| digitado | enviado |
|---|---|
| `49,90` | `49.90` |
| `1.234,56` | `1234.56` |
| `1234,56` | `1234.56` |
| `1234.56` | `1234.56` |
| `0` / `0,00` | `0.00` |
| `389` | `389.00` |
| `25.000` | `25000.00` |
| `12,5` | `12.50` |
| `abc` / `-5` | vazio — o botão Salvar fica desligado |

**Caminho 2 — o que o servidor grava** (Pest UC-SAPAC-11, lane MySQL `verticais-pest`): para
`49.90`, `1234.56`, `25000.00` e `0.00`, criar e depois editar o mesmo pacote grava o mesmo
número; e editar com `49,90` grava 49,90.

**Antes → depois, por caminho:**

| texto que chega | criar (antes) | criar (depois) | editar (antes) | editar (depois) |
|---|---|---|---|---|
| `49.90` (o que o drawer manda) | 49,90 | 49,90 | 49,90 | 49,90 |
| `1234.56` | 1.234,56 | 1.234,56 | 1.234,56 | 1.234,56 |
| `49,90` (o que a Blade mandava) | 49,90 | 49,90 | 49 ou erro | 49,90 |
| `1.234,56` | 1.234,56 | 1.234,56 | 1,234 ou erro | 1.234,56 |

O "antes" do editar com vírgula **não foi medido**: é dedução pelo tipo da coluna (`decimal(22,4)`
recebendo texto cru) e depende do modo do MySQL (estrito → erro e "algo deu errado";
não-estrito → corta). **Nenhum valor gravado é reescrito**: a mudança só vale para o próximo
salvar.

⚠️ O veredito do caminho 2 é do CI (Pest roda no CI/CT 100, nunca local). Confirmar o run verde
antes do merge.

## Prova do json, medida no branch
`PackagesController.php` não contém `view('superadmin::packages.edit')` — ✅

## Sem auto-merge
Mexe em valor (preço) e apaga Blade servida. Fica para o [W] mergear.

## Observado e não mexido
- `update()` grava `custom_permissions = null` quando nenhum módulo fica ligado e não há chave
  preservada; a coluna é NOT NULL, então salvar um pacote sem nenhum módulo dá "algo deu errado".
  Já era assim na Blade.
- `UpdatePackageRequest` (RUNBOOK §5.2) continua sem uso — validar o `update()` é outra decisão.
- `destroy()` sem checar assinantes (RUNBOOK §5.3) — fora desta thread.

## NÃO MEDI
Pest, PHPStan, ESLint e typecheck (CI). Render do drawer em produção: depois do merge.
