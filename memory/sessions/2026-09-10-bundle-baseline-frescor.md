# Bundle e baseline antigos — 2026-09-10

## Contexto

[W] reportou que a comparação/aplicação usava cópia antiga em Patrimônio e Governança. A análise continuou na branch `codex/reanalisa-processo-prototipo-20260910`, base `af09f7c3a0`.

## Diagnóstico medido

- `ancora.mjs Patrimonio/Index` resolveu `prototipo-ui/cowork/patrimonio-page.jsx`; `Governance/Dashboard` resolveu `prototipo-ui/cowork/governance-page.jsx`. As duas tiveram último recibo por arquivo em 2026-09-09T18:58:08.939Z. O resolvedor também avisou sobre 74 arquivos remotos não recebidos, medição de 2026-09-09T19:09:16.299Z. Esse histórico não certifica o remoto de hoje nem todas as dependências.
- Não foram encontrados proto-baselines versionados desses dois módulos. Portanto não foi demonstrado que o incidente específico passou por `render-proto-baseline --extract`; a brecha desse comando foi verificada separadamente.
- O hook do DS aceitava cache pela existência de referências diretas: bundle antigo presente e fonte indireta antiga não provocavam atualização.
- O gerador de proto-baseline usava Downloads legado por default; `--extract` extraía a célula sem revalidar âncora/frescor. O git-sha de um JSX não media mudança de CSS, DS ou bytes ainda sem commit.

## Correção

O hook passou a comparar bytes de todo o plano canônico `previewDsPlan`, incluindo fontes e CSS importado, e a invocar o produtor existente para atualizar cache divergente. Não foi criado outro importador.

O gerador passou a usar MIRROR_DIR por default e materializar o runtime DS pelo produtor existente. A identidade `render_sha256` usa o grafo existente `payloadDependencyGraph`: depende dos bytes dos arquivos alcançáveis pelo shell, incluindo fontes binárias. Grafo incompleto, cache divergente do runtime ou runtime divergente dos artefatos correspondentes na fonte importada recusam captura/extração. Hashes antes/depois da captura recusam fontes alteradas durante o render.

`--extract` passou a resolver novamente a âncora e exigir identidade atual do grafo. Baseline antigo sem esse campo requer regeneração; não recebeu carimbo retroativo. `--check` manteve leitura dos registros históricos com aviso explícito; baselines novos também tiveram o grafo conferido. O nudge passou a indicar regeneração antes da extração.

## Prova

- Selftest de proto-baseline: todos os casos passaram, incluindo novos controles de HTML, JSX, CSS e fonte alterados sem commit, baseline histórico e dependência removida.
- Testes do hook: todos passaram, incluindo atualização real de bundle existente antigo e fonte indireta antiga, silêncio para cache idêntico e escape existente.
- CLI real `--extract`, com fingerprint sintético explicitamente de teste: fontes locais atuais → exit 0; hash antigo → exit 1; identidade ausente → exit 1. Nenhum desses fingerprints foi versionado como evidência de tela.
- Preview local materializado: dez artefatos repostos, nenhuma fonte ausente/inválida; grafo real completo e identidade computada.
- Painel `protocolo.config --selftest` e integridade hard passaram. DS-guard não se aplica ao conteúdo MJS (arquivos ignorados pelo guard); isso não é prova visual.

## Limites e continuidade

As ferramentas DesignSync não foram encontradas entre as disponíveis. Nenhum bundle remoto novo foi baixado nesta sessão. As correções provam coerência com os arquivos importados no checkout, não que o remoto permaneceu igual. Dependências externas de CDN também não são identificadas pelos bytes locais. A comparação real de Patrimônio/Governança com o design vivo continua exigindo recepção atual dos dois projetos pelo transporte canônico e captura das telas.

Nenhuma Page, regra de negócio, cálculo, baseline de produção ou dado foi alterado. Não houve Pest/PHPStan local nem smoke da aplicação. Os cinco achados da revisão anterior continuam registrados; esta correção tratou o relato adicional sobre referência antiga.
