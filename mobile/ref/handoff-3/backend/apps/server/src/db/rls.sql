-- ──────────────────────────────────────────────────────────────
-- Oimpresso ERP · Row-Level Security (defesa em profundidade)
-- Fase 0 · Passo 3 — isolamento de tenant no banco
--
-- A autorização principal é no servidor (Policy), mas o RLS garante
-- que NENHUMA query cruze o tenant mesmo que a aplicação falhe.
-- A app define `set local app.tenant_id = '<uuid>'` por transação.
-- ──────────────────────────────────────────────────────────────

-- Helper: lê o tenant corrente da sessão
create or replace function app_current_tenant() returns uuid
language sql stable as $$
  select nullif(current_setting('app.tenant_id', true), '')::uuid
$$;

-- Aplica RLS a cada tabela com tenant_id.
-- (Repita o bloco para todas as tabelas multi-tenant.)
do $$
declare t text;
begin
  foreach t in array array[
    'users','pessoas','produtos','estoque_movimentos','pedidos',
    'orcamentos','estacoes','producao_jobs','contas_financeiras',
    'titulos','documentos_fiscais','notificacoes','audit_log','sequences'
  ] loop
    execute format('alter table %I enable row level security;', t);
    execute format('alter table %I force row level security;', t);
    execute format($f$
      create policy tenant_isolation on %I
      using (tenant_id = app_current_tenant())
      with check (tenant_id = app_current_tenant());
    $f$, t);
  end loop;
end $$;

-- Tabelas filhas sem tenant_id (pedido_itens, titulo_parcelas, baixas)
-- herdam isolamento pela FK ao pai com RLS. Garanta o join sempre via pai.
