<?php

declare(strict_types=1);

/**
 * Helpers dos vetores de credencial, compartilhados pelos dois testes.
 *
 * ── POR QUE ARQUIVO PRÓPRIO, E NÃO UM HELPER DENTRO DE UM DOS TESTES ─────────
 * `CcSecretSweepTest` (toca banco) e `CredentialShapesTest` (roda em qualquer
 * banco) estão em LANES DIFERENTES: a lane sqlite executa só os arquivos da
 * allowlist `.github/ci-sqlite-pest.list`. Se o helper morasse no arquivo puro,
 * a lane sqlite carregaria só o outro e quebraria com "undefined function".
 *
 * `function_exists` porque quando as duas lanes carregam os dois arquivos (rodada
 * local, `--all`), o `require_once` de cada um passaria duas vezes pelo mesmo
 * nome global.
 */
if (! function_exists('vetoresDeCredencial')) {
    /**
     * Vetores compartilhados com o lado cliente (`scripts/cc-watcher/redact.test.mjs`).
     *
     * @return array<int,array<string,mixed>>
     */
    function vetoresDeCredencial(): array
    {
        $caminho = base_path('tests/fixtures/credential-shapes-vectors.json');
        $json = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);

        return $json['vetores'];
    }
}

if (! function_exists('valorSinteticoDe')) {
    /**
     * Valor sintético do vetor que exercita `$shape`.
     *
     * Os valores vivem SÓ no fixture, nunca inline num `*Test.php`:
     * `tests/fixtures/` está no allowlist de path do `.gitleaks.toml`, e um
     * `AKIA...` literal num teste reprovava o `Secret scan` — medido no #7418.
     */
    function valorSinteticoDe(string $shape): string
    {
        foreach (vetoresDeCredencial() as $v) {
            if (isset($v['shapes'][$shape], $v['sumir'])) {
                return $v['sumir'];
            }
        }

        throw new RuntimeException("fixture sem vetor com 'sumir' para o shape {$shape}");
    }
}
