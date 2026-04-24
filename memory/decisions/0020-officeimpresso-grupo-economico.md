# ADR 0020 — Grupo econômico (matriz + filiais) no Officeimpresso

> "Grupo econômico" é o termo legal/contábil correto em PT-BR para conjunto de empresas com controle/ownership comum (matriz + filiais, holdings).

**Status:** Proposto (não implementado — roadmap)
**Data:** 2026-04-24

## Contexto

Wagner identificou que **quando um cliente abre o Delphi instalado na filial**, o sistema não encontra o CNPJ principal (da matriz) e "se perde". Sintomas prováveis:
- Validação de licença compara CNPJ da filial com tabela de licenças (que só tem a matriz)
- Versão obrigatória / caminho do banco são do Business da matriz — filial vê `null`
- Limite de máquinas compartilhado entre matriz + filiais não é considerado

No UltimatePOS cada Business é isolado — matriz e filial são registros separados sem relação explícita.

## Decisão (pendente implementação)

Introduzir relacionamento **self-FK** em `business`:

```sql
ALTER TABLE business ADD COLUMN matriz_id INT UNSIGNED NULL;
ALTER TABLE business ADD FOREIGN KEY (matriz_id) REFERENCES business(id) ON DELETE SET NULL;
ALTER TABLE business ADD INDEX idx_matriz (matriz_id);
```

**Regras:**
- `matriz_id = NULL` → Business é matriz ou independente (comportamento atual preservado)
- `matriz_id = X` → Business é filial de X
- Auth do desktop resolve `effective_business_id = matriz_id ?? id` antes de validar licença/versão
- Campos consolidados na matriz: `versao_obrigatoria`, `versao_disponivel`, `caminho_banco_servidor`, `officeimpresso_limitemaquinas`, `officeimpresso_bloqueado`
- Filial pode override com valor próprio (se preenchido) — matriz é fallback

## Impacto nos componentes

| Componente | Mudança |
|---|---|
| `Business` model | Add `matriz()` belongsTo + `filiais()` hasMany + scope `effectiveMatriz()` |
| `LicencaComputadorController` | Usar `$business->effectiveMatriz()` em `viewLicencas($id)` |
| `businessall.blade.php` | Agrupar filiais sob matriz (tree/accordion) |
| `LogPassportAccessToken` listener | Gravar `business_id = effectiveMatriz->id` + `metadata.filial_id = original` |
| `POST /oauth/token` flow | Validar limite de máquinas no **escopo da matriz**, não da filial |
| Config de Versão Obrigatória | Lendo do `matriz` se `filial.versao_obrigatoria = null` |

## Alternativas consideradas

- **Multi-tenant com tenant_id separado** — mais limpo mas muda API do UltimatePOS core. Descartado.
- **Grupos via tabela N:N** — permite matriz múltipla (sem sentido aqui). Descartado.
- **Campo `grupo_empresa` string** (sem FK) — frágil, permite typos. Descartado.

## Perguntas abertas pra Wagner

1. Matriz + filial podem ter **versões Delphi diferentes** em operação simultânea? Ou matriz dita a versão pra todas?
2. O **caminho do banco do servidor** é sempre único do grupo ou pode variar por filial?
3. **Bloqueio na matriz** cascateia pras filiais automaticamente? (sim, provavelmente)
4. Limite de máquinas é por **Business** ou por **grupo**? (provavelmente grupo)
5. A **subscription do Superadmin** é da matriz (filiais herdam) ou cada Business assina?

## Relacionado

- ADR 0017 — Restauração Officeimpresso
- ADR 0018 — Log acesso (listener + middleware)
- ADR 0019 — Delphi autenticação pós-upgrade

## Próximos passos

Quando for implementar:
1. Resposta às perguntas acima com Wagner
2. Migration + seed de teste
3. Atualização do controller + listener
4. UI agrupada em `businessall`
5. Teste end-to-end: abrir Delphi numa filial, validar que resolve matriz
