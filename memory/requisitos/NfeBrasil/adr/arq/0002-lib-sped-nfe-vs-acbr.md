# ADR ARQ-0002 (NfeBrasil) · Lib base: `eduardokum/sped-nfe`, não ACBr

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001

## Contexto

3 alternativas pra emissão fiscal BR em PHP:

1. **`eduardokum/sped-nfe`** — biblioteca PHP nativa, mantida ativamente, cobre NFe/NFC-e/MDF-e/CT-e, usada por dezenas de ERPs BR.
2. **ACBr** — biblioteca Delphi/C++ via COM ou serviço REST. Maduríssima, mais features, padrão da indústria desktop.
3. **Implementação própria** — escrever do zero usando schemas XSD da SEFAZ.

Critérios:
- **Stack atual:** Laravel 13.6 + PHP 8.4 — quer ferramenta nativa
- **Manutenção:** legislação fiscal BR muda constantemente (Reforma 2026-2033, CBS/IBS); precisa lib que se atualiza
- **Multi-tenant SaaS:** ACBr Service rodando em servidor compartilhado é problema (1 instância serve N businesses)
- **Custo zero/baixo:** ACBr Premium é pago; ACBr free não tem suporte direto a CT-e/MDF-e
- **Comunidade:** quanto mais usuários, menos bugs órfãos

## Decisão

`eduardokum/sped-nfe` (com `eduardokum/sped-da` pro DANFE).

Razões:
- PHP nativo (mesmo stack)
- Multi-tenant trivial (cada call recebe cert do business correto)
- Cobre os 4 modelos (55, 65, 57, 58)
- Atualização rápida (commits semanais durante mudanças regulatórias)
- Open source MIT
- Comunidade Laravel BR usa em massa (ex: Bling, Tiny)

## Consequências

**Positivas:**
- Time não precisa aprender Delphi/COM
- Deploy do oimpresso (só PHP) sem dependência externa
- Update da lib via composer (`composer update eduardokum/sped-nfe`)
- Schema flexível pra Reforma Tributária (lib aceita campos opcionais CBS/IBS)

**Negativas:**
- ACBr é mais maduro em features extremas (XML manipulation, DANFE customizado pixel-perfect) — vamos pagar isso em casos edge raros
- Performance ligeiramente inferior (~10% mais lento que ACBr Service)
- Dependência de mantenedor único (Eduardokum) — risco de bus factor; mitigar fork interno se necessário

## Pattern de uso

```php
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;

class NfeBuilderService {
    public function __construct(
        private CertificadoService $certService,
        private MotorTributarioService $motor
    ) {}

    public function build(NfeEmissao $emissao): string {
        $nfe = new Make();
        $nfe->taginfNFe(['Id' => 'NFe' . $emissao->chave_acesso]);
        // ... montar tags
        return $nfe->getXML();
    }

    public function send(string $xml, Certificado $cert): array {
        $tools = new Tools($this->certService->buildConfig($cert), $cert->asPfxObject());
        return $tools->sefazEnviaLote([$xml], 1);
    }
}
```

## Alternativas consideradas

- **ACBr Service** — rejeitado: complica deploy SaaS multi-tenant, custo licença, stack mista
- **Implementação própria** — rejeitado: meses de trabalho que `sped-nfe` já fez; manutenção contínua quando lei muda
- **`nfephp/sped-nfe-cb`** (variante CB) — rejeitado: menos manutenção que `eduardokum/sped-nfe`
- **API SaaS (Tecnospeed, eNotas)** — rejeitado: vendor lock-in + custo recorrente que oimpresso quer evitar (queremos VENDER esse pricing, não pagar)

## Referências

- https://github.com/nfephp-org/sped-nfe
- https://github.com/nfephp-org/sped-da
- `_Ideias/NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`
- ACBr Project (referência rejeitada)
