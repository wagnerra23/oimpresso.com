---
sessao: "23"
titulo: Retenções federais na NFS-e (PIS/COFINS/CSLL)
autor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main f363d90b35 (pós-#9182)
prefixo_tocado: Modules/NfeBrasil/Services/RetencoesNfseService.php · Modules/NfeBrasil/Tests/Feature/NfseRetencoesTest.php · .github/workflows/nfebrasil-pest.yml (allowlist) · .github/brl-scan-allowlist.txt · memory/requisitos/NfeBrasil/SPEC.md · este arquivo
decisao: D-NFSE-CONSOLIDA ([W] 2026-10-13, PR #9186) — o prefixo saiu de Modules/NFSe/Services/ para Modules/NfeBrasil/
---
# _saida-23 · Retenções federais na NFS-e

**Resposta curta:** o `RetencoesNfseService` existe no NfeBrasil e **só calcula**. Tomador PJ + prestador em lucro presumido/real: PIS 0,65% + COFINS 3% + CSLL 1% = 4,65%, dispensado quando o total fica em até dez reais. Prestador no Simples (ou MEI): nenhuma retenção e aviso pra confirmar com o contador. Regime lido de `nfe_business_configs.regime` do próprio tenant. Nenhuma nota, título ou tela muda.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/NfeBrasil/Services/RetencoesNfseService.php` | `calcular(centavos, tomadorPj, regime, servicoSujeito)` e `calcularParaBusiness(businessId, …)`. Lei 10.833/2003 arts. 30, 31, 31 § 3º e 32 III citados **literalmente** (planalto, compilado, lido em 2026-10-13). Conta em centavos inteiros, por tributo, meio centavo arredonda pra cima. |
| `Modules/NfeBrasil/Tests/Feature/NfseRetencoesTest.php` | R-NFSE-001: C21, dispensa, fronteira exata do § 3º, controle PF e serviço fora do art. 30. R-NFSE-002: C22 (simples e mei), controle "vira regime normal → retém", regime ausente sem palpite, isolamento 98 × 99. |
| `.github/workflows/nfebrasil-pest.yml` | teste entra na allowlist da lane MySQL. |
| `memory/requisitos/NfeBrasil/SPEC.md` | R-NFSE-001 e R-NFSE-002 (números livres no repo em 2026-10-13: `git grep R-NFSE-[0-9]` vazio). |

**Por que não está no `Modules/NFSe`:** a decisão D-NFSE-CONSOLIDA. Nenhum código foi movido de lá; a deprecação tem plano próprio.

**Por que `servicoSujeito` é parâmetro:** o art. 30 só alcança os serviços que lista (limpeza, manutenção, segurança, locação de mão de obra, assessoria creditícia/mercadológica, serviços profissionais...). O ERP não tem a tabela item LC 116 × art. 30, então quem chama declara. O aceite da ficha supunha sempre sujeito; o código não presume.

## 2 · Regra mestre de valor: dupla confirmação

Valores em centavos.

```
caso                               caminho 1 · Python Decimal      caminho 2 · RetencoesNfseService::calcular
                                   (total × 4,65%, HALF_UP)        (PHP 8.4 no CT 100, por tributo em centavos)
1.500,00 · PJ · presumido (C21)    69,75 · retém                   PIS 975 + COFINS 4500 + CSLL 1500 = 6975 · retém
  150,00 · PJ · real               6,98 · dispensa                 total 698 · dispensada · aviso
  215,06 · PJ · presumido          10,00 · dispensa                total 1000 · dispensada · aviso
  215,30 · PJ · presumido          10,01 · retém                   140 + 646 + 215 = 1001 · retém
1.500,00 · PF · presumido          —                               nada
1.500,00 · PJ · simples (C22)      —                               nada · aviso contador
1.500,00 · PJ · mei                —                               nada · aviso contador
1.500,00 · PJ · regime ausente     —                               nada · aviso configurar
```

Os dois caminhos batem nos 4 casos com valor. Arredondar por tributo e somar dá o mesmo total que aplicar 4,65% de uma vez nesses casos.

**Antes → depois em produção:** antes nada calculava a retenção. Depois, ainda nada muda: o service não tem chamador e não grava. Nenhuma NFS-e, título ou saldo muda de valor.

## 3 · Não feito, e por quê

- **A emissão viva não consome.** A DPS de hoje é montada pelo `Modules/NFSe` (`NfseEmissaoPayload::valorIss`, só ISS). Ligar o cálculo nela é mexer no módulo que vai ser deprecado; o `EmitirNFSeJob` do NfeBrasil é stub sem coluna de retenção. Ligar é a próxima thread, junto da consolidação.
- **IRRF não entrou.** A ficha diz "1,5% conforme o item", mas não há aceite nem tabela item × alíquota no ERP. Citar sem a tabela seria palpite.
- **INSS 11%** fora, como a ficha manda.
- **Art. 30 § 2º**: tomador optante pelo Simples não é obrigado a reter. O regime do tomador não existe no cadastro de contato; fica declarado.
- **Exceção do DARF via Siafi** (§ 3º): só pagador órgão público federal; não modelada.
- **Pest não rodou local** (CT 100/CI only). `php -l` limpo nos dois arquivos no PHP 8.4 do CT 100. O teste roda pelo nome na lane `nfebrasil-pest`.

## 4 · Pedido literal pro [CL]/[W]

```
1. [W] merge do PR (regra mestre de valor: a tabela da seção 2 é o antes→depois).
2. Próxima thread: a DPS da NFS-e chama RetencoesNfseService e grava as retenções,
   com o item LC 116 dizendo se o serviço está no art. 30.
3. IRRF: precisa da tabela item × alíquota antes de virar regra.
```
