---
id: session-2026-10-26-fase0-nsu-dfe-seco-medicao
date: "2026-10-26"
topic: "Fase 0 da entrada por XML — por que a busca de DF-e por NSU nunca trouxe documento em produção"
tipo: medicao
pii: false
us: [US-COM-003, US-NFE-051]
---

# Fase 0 — por que o NSU nunca trouxe documento (medido em produção)

ADR `memory/decisions/proposals/2026-10-06-entrada-xml-compra.md` §4, fase 0 (dono: Claude, decisão [W]
de 2026-10-26). Medição só de leitura no Hostinger em 2026-10-26, por `php artisan tinker` com SELECTs e
`grep` no `laravel.log`. Nenhum dado foi escrito. Valores de CNPJ não aparecem aqui, só comprimentos.

## Resposta curta

A busca roda todo dia e **morre antes de chamar a SEFAZ**. São quatro causas somadas; consertar só a
primeira que aparece no log não basta.

| # | Causa | Medido |
|---|---|---|
| 1 | Só a **biz=1** tem certificado ativo | `nfe_certificados`: 1 linha ativa, `business_id=1`. Nenhuma empresa cliente tem certificado. `nfe_dfe_nsu_state` tem 1 linha (biz=1), `last_nsu=0`, `ultimo_check_em` nulo |
| 2 | De 2026-07-03 a 2026-09-02 o job falhava lendo `business.state`, coluna que não existe | 44 linhas em `failed_jobs` com `Unknown column 'state'`. Corrigido em 2026-09-04 (`resolverUfEmitente`) |
| 3 | Desde 2026-09-05 falha porque a biz=1 **não tem CNPJ** | 25 linhas em `failed_jobs` com `config.json não é válido [cnpj] ... ^[A-Z0-9]{11,14}`, a última em 2026-10-26 06:17. `business.tax_number_1` da biz=1 tem 0 dígitos e `nfe_certificados.cnpj_titular` também tem 0 |
| 4 | Mesmo sem erro, a busca iria para **homologação** | `config('nfebrasil.ambiente')` é nulo em produção; `DistribuicaoDfeService::buildTools/buildConfig` usam o padrão `2` (homologação), que não devolve nota real |

Outros fatos medidos: ambiente `live`, fila `database`, `jobs` pendentes = 0, `nfe_dfe_recebidos` = 0;
o agendamento `nfebrasil:dist-dfe-puxar` (06:15) roda neste ambiente (`runsInEnvironment` = sim).

## Por que ninguém viu

O comando agendado só **despacha** o `BuscarDfesRecebidosJob` e sai com sucesso; o `onFailure` do
`Kernel.php` nunca dispara. A falha mora no `failed_jobs`, que nenhum alarme lê. São **69** falhas
diárias seguidas desde 2026-07-03.

## O que isso muda no plano da ADR

- O upload de XML (D2) é hoje a **única** fonte que pode funcionar; a fase 2 deve vir antes de depender do NSU.
- Para o NSU funcionar numa empresa: certificado ativo **dessa empresa**, CNPJ preenchido (no cadastro ou no
  titular do certificado) e `nfebrasil.ambiente=1` em produção. O terceiro é decisão de configuração do
  [W] — afeta toda a NfeBrasil, não só a DF-e.
- Sugestão de código (fora desta fase, dono NfeBrasil): `DistribuicaoDfeService` e `ManifestacaoService`
  montam o CNPJ só de `business.tax_number_1`; o `EmpresaFiscalLookupService::cnpjDaEmpresa` já prefere
  `cnpj_titular` do certificado. Usar a mesma fonte nos três. E a falha do job precisa de alarme próprio.
