# ADR ARQ-0001 (NfeBrasil) · Módulo isolado via nwidart, sem monkey-patch no core

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: `Financeiro/adr/arq/0001-modulo-isolado-via-nwidart.md` (mesma decisão, contexto diferente)

## Contexto

UltimatePOS 6.7 é fork comprado. Próximos upgrades Laravel via Shift vão sobrescrever `app/` core. NfeBrasil precisa: (a) emissão fiscal complexa, (b) observers em `Transaction`, (c) menu admin, (d) permissões Spatie, (e) license check Superadmin, (f) seeders gigantes (NCM 15k rows).

NfeBrasil é maior que Financeiro (motor tributário + 4 modelos de doc + SPED). Misturar com `app/` é receita pra desastre no upgrade.

## Decisão

Caminho idêntico ao Financeiro: módulo nwidart isolado em `Modules/NfeBrasil/`.

- Namespace `Modules\NfeBrasil\`
- Tabelas com prefix `nfe_` (não conflita com Financeiro `fin_*`)
- Storage isolado em `storage/app/nfe-brasil/{business_id}/...`
- Hooks via `\App\Utils\ModuleUtil::moduleData('nfe-brasil', [...])`
- ZERO edição em arquivos `app/` core
- Observer registrado no boot (não monkey-patch no Model core)
- Listeners em queue `nfe` separada (não `default` nem `financeiro` — emissão pesada não atrapalha outras filas)

## Consequências

**Positivas:**
- Próximo upgrade Laravel não toca em `Modules/NfeBrasil/`
- Tenant pode ativar/desativar NfeBrasil sem afetar core/Financeiro
- Testes isolados (`Modules/NfeBrasil/Tests/`)
- Datasets fiscais (NCM/CEST/CFOP) ficam em seeders próprios — atualizáveis independentemente
- `php artisan module:disable NfeBrasil` desativa sem impacto em outros módulos

**Negativas:**
- Performance: observer em `Transaction` adiciona ~2ms por venda (aceitável)
- Cross-módulo (NfeBrasil → Financeiro) só por evento → mais código boilerplate
- Datasets fiscais em seeders crescem o repositório (~50MB CSVs); migrar pra storage externo se necessário

## Alternativas consideradas

- **Adicionar em `app/`** — rejeitado: NfeBrasil é o módulo mais complexo do oimpresso; sobrescrever no upgrade é trauma
- **Composer package separado** — rejeitado: não vai rodar standalone; perde scaffold
- **Plugar via observer global sem nwidart** — rejeitado: perde organização (controllers, views, migrations no mesmo lugar)

## Referências

- `Financeiro/adr/arq/0001` (mesma decisão)
- `auto-memória: reference_ultimatepos_integracao.md`
- `Modules/PontoWr2/Providers/PontoWr2ServiceProvider.php` (exemplo)
