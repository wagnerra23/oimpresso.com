---
sessao: "30"
titulo: Seed ICMS interestadual em produção — rodado
dono: "[CL]"
data: 2026-10-13
base: produção e2ea5797b0 (deploy de 2026-10-13 08:50 -0300)
---

# _saida-30 · Seed ICMS interestadual

Rodado em produção em 2026-10-13 pelo gerente da fila de merges, por decisão [W] (D-SEED-ICMS de 07/10,
confirmada no chat em 13/10: *"rode"*).

- Pré-condição: a migração `2026_10_07_000003_create_nfe_icms_uf_table` aparece como `Ran` (lote 235). A tabela
  estava vazia antes do seed.
- Comando: `php artisan db:seed --class="Modules\NfeBrasil\Database\Seeders\NfeIcmsUfSeeder" --force`. É
  idempotente: só insere o par origem→destino que o negócio ainda não tem.
- Contagem depois, agrupada por negócio e alíquota interestadual:

| business_id | alíquota | linhas |
|---|---|---|
| 1 | 7% | 146 |
| 1 | 12% | 556 |
| 1 | vazia (interna) | 27 |

Bate exatamente com o esperado pela ficha: 146 a 7%, 556 a 12% e 27 internas vazias. Só o biz=1 recebeu linhas,
porque o seeder percorre `nfe_business_configs` e hoje só o biz=1 tem configuração fiscal.
