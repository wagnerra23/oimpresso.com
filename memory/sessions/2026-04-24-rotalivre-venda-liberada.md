# Sessão — 2026-04-24 — ROTA LIVRE: venda liberada (permissão location faltando)

## Contexto

Mesmo depois do revert do `format_date` (timezone resolvido), ROTA LIVRE continuou reclamando que o cliente dele não conseguia vender — em `/sells/create` o campo de busca de produto estava **disabled**, só o botão `+` (quick add) funcionava.

## Causa raiz

Role **`Vendas#4`** (do business 4 — ROTA LIVRE) tinha **lista vazia de permissões de location**.

Usuário `rota.vendas-04` (id=11) é o operador que usa a tela de venda. Ele tem role `Vendas#4`. Como o role não tinha `location.4` nem `access_all_locations`, o método `$user->permitted_locations()` retornava `[]`.

Fluxo do bug:
```
user#11 → SellController::create
  → BusinessLocation::forDropdown(4) filtra por permitted_locations()=[]
  → retorna Collection vazia
  → foreach não roda
  → $default_location = null
  → blade renderiza: 'disabled' => is_null($default_location) ? true : false
  → #search_product fica disabled no HTML
```

## Users do business 4 ROTA LIVRE (diagnóstico):

| ID | Username | Role | perm_locs |
|---|---|---|---|
| 9 | wr2.rotalivre | Admin#4 | `all` ✅ |
| 10 | larissa-04 | Admin#4 | `all` ✅ |
| 11 | **rota.vendas-04** | **Vendas#4** | **`[]`** 🔴 |
| 72 | caixa-04 | Caixa#4 | `all` ✅ |

Só o role Vendas#4 estava sem location — provavelmente foi criado depois ou perdeu a permissão em alguma migração/seed.

## Fix aplicado (direto no banco de produção)

Via script SSH one-shot na Hostinger:

```php
$role = Role::where('name', 'Vendas#4')->first();
$perm = Permission::firstOrCreate(['name' => 'location.4', 'guard_name' => 'web']);
$role->givePermissionTo($perm);
app()['cache']->forget('spatie.permission.cache');
```

Resultado imediato:
- Permission `location.4` criada (id=113, guard=web)
- Atribuída ao role Vendas#4
- `$user11->permitted_locations()` → `[4]` ✅
- `BusinessLocation::forDropdown(4)` → 1 entry (BL0001 ROTA LIVRE) ✅
- `php artisan cache:clear` + `permission:cache-reset` pra garantir

Após logout/login do `rota.vendas-04`, o campo `#search_product` deve ficar habilitado.

## Por que NÃO afetou outros businesses

O controller `SellController::create` e o blade `sell/create.blade.php:359` são compartilhados por todos os businesses. A lógica `is_null($default_location) ? true : false` vale pra qualquer business. Mas o problema só aparece quando o **role específico** do user não tem location — e isso era local ao Vendas#4.

## Dívida futura / cuidados

1. **Outros businesses podem ter o mesmo bug silencioso** em roles custom (ex: "Vendedor", "Atendente", "Caixa" sem location). Candidato a comando `php artisan roles:audit-location-perms` pra scan.
2. **UI engana o usuário**: o select de localização no topo da tela mostra "ROTA LIVRE (BL0001)" selecionada (porque esse dropdown usa outra lógica), mas backend considerou sem location. Deveria dar feedback visual "sua role não tem permissão para esta location" ao invés de silenciosamente desabilitar o search.
3. **Mudança de permissão em produção** foi feita direto via SSH — OK nesse caso (fix pontual autorizado pelo Wagner), mas o ideal seria uma interface de admin pra ajustar sem precisar SSH.

## Artefatos

- Fix aplicado via script SSH inline (não commitado — é dado de produção, não código)
- Memória durável (este arquivo)
- Não há commit de código porque a correção foi via DB/cache, não arquivos

## Lição aprendida

Dois bugs diferentes podem aparecer ao mesmo tempo na mesma tela e cada um precisa de investigação independente. Neste caso:
1. Timezone de vendas antigas → fix de código (revertido)
2. Campo de busca bloqueado → fix de dados (permission seed)

Se eu tivesse assumido que "liberar venda pra ele" = "deploy o revert", o cliente continuaria travado porque o revert não resolve o disabled.
