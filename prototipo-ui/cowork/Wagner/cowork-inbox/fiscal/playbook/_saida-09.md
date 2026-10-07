---
sessao: "09"
titulo: Tabela ICMS/FCP por UF curada (R-NFE-021)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main (com #8941 da thread 07)
prefixo_tocado: migração 2026_10_07_000003 · Models/NfeIcmsUf.php · Database/Seeders/NfeIcmsUfSeeder.php · Exceptions/AliquotaInternaNaoCadastradaException.php · Tests/Feature/IcmsUfTest.php · SPEC (R-NFE-021) · SCOPE · SUPERFICIE (regenerada) · nfebrasil-pest.yml · este arquivo
---
# _saida-09 · Tabela ICMS/FCP por UF

**Resposta curta:** existe a tabela `nfe_icms_uf` (empresa × UF de origem × UF de destino × vigência).
O seeder traz só as interestaduais, com a Resolução do Senado nº 22/1989 citada literal no docblock;
a interna e o FCP nascem vazios. Pedir a interna vazia dá `AliquotaInternaNaoCadastradaException`
("Alíquota interna de RJ não cadastrada"), nunca 0. O motor ainda não lê a tabela.

## 1 · A norma (lei 4 do módulo)

Texto conferido em duas fontes: publicação original na Câmara
(`www2.camara.leg.br/legin/fed/ressen/1989/resolucao-22-19-maio-1989-481183-publicacaooriginal-1-pl.html`)
e o registro do Senado. Art. 1º: 12%. Parágrafo único, II: 7% "nas operações e prestações realizadas
nas Regiões Sul e Sudeste, destinadas às Regiões Norte, Nordeste e Centro-Oeste e ao Estado do
Espírito Santo".

## 2 · Prova (CT 100, worktree isolado, sha `02d7c08a0`, migração aplicada e depois revertida)

| rodada | resultado |
|---|---|
| `IcmsUfTest` | **3 passed · 1.479 assertions** |
| mutação: seeder esquece "e ao Estado do Espírito Santo" | o caso dos 729 pares cai (*"two arrays are identical"*) |
| mutação: interna vazia devolve 0 | R-NFE-021 cai |
| PHPStan (modelo, seeder, exceção) | sem erros |
| staging depois | tabela e entrada em `migrations` removidas (`tabela=0 migrations=0`) |

**Dupla prova do seed:** (1) oráculo escrito no teste com as regiões do IBGE montadas de novo, lido
da resolução — 0 divergências nos 729 pares; (2) contagem à mão — 7 origens × 21 destinos = 147,
menos ES→ES = **146 a 7%**; 702 − 146 = **556 a 12%**; **27 internos** sem interestadual.

## 3 · Diferença do pedido / o que fica

- **O seed não roda no deploy.** O deploy roda migração, não seeder. Para popular produção:
  `php artisan db:seed --class="Modules\NfeBrasil\Database\Seeders\NfeIcmsUfSeeder"` (idempotente).
  Decisão de quando rodar é do [W]/gerente.
- **4% de importados (Res. Senado nº 13/2012) fora:** depende da origem do produto, não do par de UFs.
- **Sem tela** para o contador preencher a interna/FCP, e **o motor não lê** a tabela: as duas coisas
  são threads seguintes (o playbook põe o fallback do motor em outra lane).
- **Por empresa** (`business_id` + FK): é o contador de cada empresa que preenche a interna.
