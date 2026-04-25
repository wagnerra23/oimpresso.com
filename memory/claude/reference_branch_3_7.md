---
name: Branch 3.7-com-nfe tem código perdido na migração 3.7→6.7
description: origin/3.7-com-nfe é a fonte pra restaurar código perdido na migração 3.7→6.7 (Officeimpresso licença desktop, Connector 147 arquivos, etc)
type: reference
originSessionId: 0a2fc9e1-a031-4636-a1f3-71622c27daa8
---
Branch remoto `origin/3.7-com-nfe` contém o snapshot do código **antes** do upgrade 3.7→6.7 (Eliana upgrou o servidor sem versionar, perdeu muito código).

**Quando usar:**
- Qualquer bug "isso funcionava antes" → diff com `git show origin/3.7-com-nfe:<path>`
- Restauração de módulos perdidos
- Comparar estrutura de rotas, migrations, controllers do 3.7

**Perdas conhecidas até 2026-04-23:**
- **Officeimpresso**: Licenca_Computador entity + 3 controllers + 2 migrations + 6 views + middleware + 9 Transformers (restaurado em 2026-04-23 — commits ec8d88f a 2905f57)
- **Connector**: **147 arquivos perdidos** — controllers de Acabamento, Balanco, Bancos, Boletos, Caixa, CentroCusto, CentroTrabalho, Chat, Cidades, Comissão, Competência, Contas, Contrato, Cte, Dify, Dre, etc. (ainda não restaurados — volume enorme)
- Fiscal, Boleto, Chat, Jana, BI (módulos inteiros — memory `preference_modulos_prioridade`)

**Atenção ao restaurar:**
- Views do 3.7 frequentemente têm typos em route names (plural vs singular), references a controllers renomeados (ex.: `LicencaController` em vez de `ClientController`), keys de tradução ausentes.
- Controllers 3.7 podem não ter `create()`/`edit()` métodos que o `Route::resource` espera — adicionar stubs se necessário.
- Namespaces podem estar errados (`App\Http\Controllers` em vez de `Modules\X\Http\Controllers`).
