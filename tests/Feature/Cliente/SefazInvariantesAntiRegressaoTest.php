<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0186 IRREVOGÁVEL — guardas anti-regressão dos 10 invariantes.
 *
 * Cada test bloqueia merge se um invariante regredir. NÃO REMOVER nem fazer
 * skip permanente — qualquer mudança que viole tem que vir via NOVA ADR com
 * supersedes: [186] + aprovação Wagner.
 *
 * CI workflow `governance-gate.yml` roda:
 *   ./vendor/bin/pest --filter SefazInvariantesAntiRegressao --stop-on-failure
 *
 * @see memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md §Invariantes
 * @see memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md §Guardas
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->base = base_path();
});

// ---------------------------------------------------------------------
// Invariante #1 — Ordem da chain de cert imutável (primário antes de institucional)
// ---------------------------------------------------------------------

test('INVARIANTE 1: chain de cert tenta primário ANTES de institucional', function () {
    // Lê CertificadoService::carregarParaSefazComFallback. Garante que a chamada
    // a `carregarParaSefaz` (camadas 1+2) acontece ANTES de qualquer query
    // `withoutGlobalScope` (camada 3 institucional).
    $arquivo = $this->base . '/Modules/NfeBrasil/Services/CertificadoService.php';
    $conteudo = file_get_contents($arquivo);
    expect($conteudo)->not->toBeFalse('CertificadoService.php ausente');

    // Localiza o método.
    $inicio = strpos($conteudo, 'function carregarParaSefazComFallback');
    expect($inicio)->not->toBeFalse('Método carregarParaSefazComFallback ausente — invariante violada');

    $metodo = substr($conteudo, $inicio, 5000); // trecho do método

    $posPrimario = strpos($metodo, '$this->carregarParaSefaz(');
    $posInstitucional = strpos($metodo, 'withoutGlobalScope');

    expect($posPrimario)->not->toBeFalse('Chamada $this->carregarParaSefaz ausente — chain quebrada');
    expect($posInstitucional)->not->toBeFalse('withoutGlobalScope ausente — fallback institucional quebrado');

    // Camada 1 (primário) tem que vir LEXICAMENTE antes de camada 3 (institucional).
    expect($posPrimario)->toBeLessThan($posInstitucional);
});

// ---------------------------------------------------------------------
// Invariante #2 — withoutGlobalScope(ScopeByBusiness) em nfe_certificados restrito a 1 ocorrência
// ---------------------------------------------------------------------

test('INVARIANTE 2: withoutGlobalScope(ScopeByBusiness) em NfeCertificado SÓ no CertificadoService', function () {
    // Pesquisa o codebase por uso de withoutGlobalScope combinado com NfeCertificado.
    // Aceita 0 ou 1 ocorrência. Se 1, tem que estar em CertificadoService.
    $cmd = sprintf(
        'grep -rEn "NfeCertificado::withoutGlobalScope|withoutGlobalScope.*ScopeByBusiness.*NfeCertificado" %s/Modules %s/app 2>nul || grep -rEn "NfeCertificado::withoutGlobalScope|withoutGlobalScope.*ScopeByBusiness.*NfeCertificado" %s/Modules %s/app 2>/dev/null',
        escapeshellarg($this->base),
        escapeshellarg($this->base),
        escapeshellarg($this->base),
        escapeshellarg($this->base),
    );
    $output = shell_exec($cmd) ?? '';
    $linhas = array_filter(explode("\n", trim($output)), fn ($l) => $l !== '');

    // Filtra comentários.
    $callsReais = array_filter($linhas, function (string $line) {
        $partes = explode(':', $line, 3);
        if (count($partes) < 3) {
            return false;
        }
        $codigo = trim($partes[2]);
        return ! str_starts_with($codigo, '//')
            && ! str_starts_with($codigo, '*')
            && ! str_starts_with($codigo, '#');
    });

    expect(count($callsReais))->toBeLessThanOrEqual(1);

    if (count($callsReais) === 1) {
        $linha = reset($callsReais);
        expect($linha)->toContain('CertificadoService.php');
    }
});

// ---------------------------------------------------------------------
// Invariante #3 — Audit log obrigatório no fallback institucional (sha256 cnpj)
// ---------------------------------------------------------------------

test('INVARIANTE 3: fallback institucional contém mcp_audit_log insert + sha256', function () {
    $arquivo = $this->base . '/Modules/NfeBrasil/Services/CertificadoService.php';
    $conteudo = file_get_contents($arquivo);

    expect($conteudo)->toContain("DB::table('mcp_audit_log')");
    expect($conteudo)->toContain("'sefaz.cert.fallback_institutional_used'");
    expect($conteudo)->toContain("hash('sha256'");

    // NUNCA o conteúdo plain do CNPJ vai pro audit log.
    expect($conteudo)->not->toMatch('/[\'"]cnpj[\'"]\s*=>\s*\$contextoConsulta[^h]/i');
});

// ---------------------------------------------------------------------
// Invariante #4 — Promise.all paralelo (não pode virar sequencial)
// ---------------------------------------------------------------------

test('INVARIANTE 4: handleCnpjLookup usa Promise.all (paralelo, não sequencial)', function () {
    $arquivo = $this->base . '/resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    $conteudo = file_get_contents($arquivo);

    expect($conteudo)->toContain('Promise.all([brasilApiP, sefazP]');

    // Anti-regressão: não pode ter await sequencial brasilApi → await sefaz.
    // Padrão proibido: "await fetch.*lookup/cnpj.*\n.*await fetch.*sefaz"
    expect($conteudo)->not->toMatch('/await\s+fetch\(`\/cliente\/lookup\/cnpj\/\$\{digits\}`[^P]*\n[^}]*await\s+fetch\(`\/cliente\/lookup\/cnpj\/\$\{digits\}\/sefaz/s');
});

// ---------------------------------------------------------------------
// Invariante #5 — Autoridade merge: IE → SEFAZ sempre
// ---------------------------------------------------------------------

test('INVARIANTE 5: merge prioriza IE da SEFAZ (autoridade), nunca BrasilAPI', function () {
    $arquivo = $this->base . '/resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    $conteudo = file_get_contents($arquivo);

    // Padrão canônico de merge IE: SEFAZ se presente, BrasilAPI fallback.
    // Aceita variações de whitespace.
    expect($conteudo)->toMatch('/sefazData\?\.ie.*\|\|.*ieBrasilApi/');

    // Anti-regressão: não pode ter padrão invertido (BrasilAPI || SEFAZ pra IE).
    expect($conteudo)->not->toMatch('/ieBrasilApi.*\|\|.*sefazData\?\.ie/');
});

// ---------------------------------------------------------------------
// Invariante #6 — Contrato 13 campos canônicos do Service
// ---------------------------------------------------------------------

test('INVARIANTE 6: SefazConsultaCadastroService::consultar retorna 13 campos canônicos', function () {
    $arquivo = $this->base . '/Modules/NfeBrasil/Services/SefazConsultaCadastroService.php';
    $conteudo = file_get_contents($arquivo);

    // Todos os 13 campos do contrato têm que estar no return array.
    $camposCanon = [
        "'ie'", "'situacao'", "'situacao_label'", "'nome'", "'uf'", "'fonte'",
        "'cert_source'", "'cert_business_id'",
        "'ind_ie_dest'", "'ind_cred_nfe'", "'regime_apuracao'",
        "'endereco_sefaz'", "'alertas'", "'consultado_em'",
    ];

    foreach ($camposCanon as $campo) {
        expect($conteudo)->toContain($campo);
    }
});

// ---------------------------------------------------------------------
// Invariante #7 — Matriz UFs em config/fiscal.php, NÃO hardcoded
// ---------------------------------------------------------------------

test('INVARIANTE 7: matriz UFs supported está em config/fiscal.php (não hardcoded)', function () {
    // Config existe + tem matriz.
    $config = $this->base . '/config/fiscal.php';
    expect(file_exists($config))->toBeTrue();

    $confContent = file_get_contents($config);
    expect($confContent)->toContain('sefaz_consulta_cadastro_ufs_supported');

    // Service NÃO pode ter UF hardcoded literal (ex: in_array($uf, ['RS', 'SP', ...])).
    $service = $this->base . '/Modules/NfeBrasil/Services/SefazConsultaCadastroService.php';
    $svcContent = file_get_contents($service);

    // Procura padrão proibido — array literal com 2+ UFs juntas.
    expect($svcContent)->not->toMatch("/\[\s*'(RS|SP|PR|MG|BA|SC)'\s*,\s*'(RS|SP|PR|MG|BA|SC)'/");

    // Service tem que ler do config.
    expect($svcContent)->toContain("config('fiscal.sefaz_consulta_cadastro_ufs_supported");
});

// ---------------------------------------------------------------------
// Invariante #8 — Migration idempotente (Schema::hasColumn)
// ---------------------------------------------------------------------

test('INVARIANTE 8: migration sefaz_consulta_fields é idempotente', function () {
    $arquivo = $this->base . '/database/migrations/2026_05_23_120000_add_sefaz_consulta_fields_to_contacts.php';
    $conteudo = file_get_contents($arquivo);

    // Cada coluna na up() tem hasColumn check ANTES.
    expect($conteudo)->toContain("Schema::hasColumn('contacts', 'ind_ie_dest')");
    expect($conteudo)->toContain("Schema::hasColumn('contacts', 'sefaz_cad_sit')");
    expect($conteudo)->toContain("Schema::hasColumn('contacts', 'sefaz_cad_ind_cred_nfe')");
    expect($conteudo)->toContain("Schema::hasColumn('contacts', 'sefaz_cad_consultado_em')");

    // down() também idempotente (dropa só se existir).
    $downIdx = strpos($conteudo, 'public function down');
    expect($downIdx)->not->toBeFalse();
    $downSection = substr($conteudo, $downIdx);
    expect($downSection)->toContain('Schema::hasColumn');
});

// ---------------------------------------------------------------------
// Invariante #9 — ind_ie_dest enum 1/2/9 estrito (já em SefazConsultaCadastroChainTest)
// ---------------------------------------------------------------------

test('INVARIANTE 9: validator ind_ie_dest rejeita fora enum {1,2,9}', function () {
    // Lê o controller e procura a regra estrita.
    $arquivo = $this->base . '/Modules/Crm/Http/Controllers/ClienteAutosaveController.php';
    $conteudo = file_get_contents($arquivo);

    expect($conteudo)->toMatch("/'ind_ie_dest'\s*=>\s*\[.*'in:1,2,9'\]/s");
});

// ---------------------------------------------------------------------
// Invariante #10 — Warning UI cSit≠habilitado (compromisso UX)
// ---------------------------------------------------------------------

test('INVARIANTE 10: Service gera alertas severity high pra cSit cancelado/baixado', function () {
    $arquivo = $this->base . '/Modules/NfeBrasil/Services/SefazConsultaCadastroService.php';
    $conteudo = file_get_contents($arquivo);

    // Códigos canônicos de alertas.
    expect($conteudo)->toContain("'code' => 'cad_nao_habilitado'");
    expect($conteudo)->toContain("'code' => 'cad_suspenso'");
    expect($conteudo)->toContain("'code' => 'cad_cancelado'");
    expect($conteudo)->toContain("'code' => 'cad_baixado'");
    expect($conteudo)->toContain("'severity' => 'high'");
    expect($conteudo)->toContain("'severity' => 'medium'");

    // Frontend processa alertas + mostra warning visual.
    $front = $this->base . '/resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    $frontContent = file_get_contents($front);
    expect($frontContent)->toContain("alertasSefaz");
    expect($frontContent)->toContain("'high'");
});

// ---------------------------------------------------------------------
// Invariante #11 — Timeout enforcement backend + frontend (anti-hang)
// ---------------------------------------------------------------------

test('INVARIANTE 11a: Service aplica timeout via $tools->soap->timeout()', function () {
    $arquivo = $this->base . '/Modules/NfeBrasil/Services/SefazConsultaCadastroService.php';
    $conteudo = file_get_contents($arquivo);

    // Service chama $tools->soap->timeout(N) ANTES de sefazCadastro.
    expect($conteudo)->toContain('$tools->soap->timeout(');
    // Valor lido do config (não hardcoded).
    expect($conteudo)->toContain("config('fiscal.sefaz_consulta_cadastro_timeout_seconds'");
});

test('INVARIANTE 11b: config tem timeout_seconds + frontend_timeout_ms', function () {
    $conf = $this->base . '/config/fiscal.php';
    $conteudo = file_get_contents($conf);

    expect($conteudo)->toContain('sefaz_consulta_cadastro_timeout_seconds');
    expect($conteudo)->toContain('sefaz_consulta_cadastro_frontend_timeout_ms');
});

test('INVARIANTE 11c: frontend handleCnpjLookup usa AbortController + signal nos fetches', function () {
    $arquivo = $this->base . '/resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    $conteudo = file_get_contents($arquivo);

    // AbortController instanciado.
    expect($conteudo)->toContain('new AbortController()');
    // setTimeout cancela após N ms.
    expect($conteudo)->toMatch('/setTimeout\(\s*\(\s*\)\s*=>\s*\w+\.abort\(\)/');
    // fetch usa signal.
    expect($conteudo)->toContain('signal: brasilApiCtrl.signal');
    expect($conteudo)->toContain('signal: sefazCtrl.signal');
    // Trata AbortError graceful.
    expect($conteudo)->toContain("AbortError");
});

test('INVARIANTE 11d: badge UI tem mensagem específica pra timeout SEFAZ', function () {
    $arquivo = $this->base . '/resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    $conteudo = file_get_contents($arquivo);

    expect($conteudo)->toContain('sefazTimeoutFlag');
    // Mensagem do badge contém "demorou" pra orientar usuário.
    expect($conteudo)->toMatch('/demorou.*tente.*novo|demorou.*preencha/i');
});

// ---------------------------------------------------------------------
// Meta-invariante — ADR existe e está marcada IRREVOGÁVEL
// ---------------------------------------------------------------------

test('META: ADR 0186 existe + frontmatter lifecycle=irrevogavel + status=aceito', function () {
    $adr = $this->base . '/memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md';
    expect(file_exists($adr))->toBeTrue();

    $conteudo = file_get_contents($adr);

    // Frontmatter Tier 0.
    expect($conteudo)->toContain('lifecycle: irrevogavel');
    expect($conteudo)->toContain('status: aceito');
    expect($conteudo)->toContain('authority: canonical');

    // Tags obrigatórios.
    expect($conteudo)->toContain('tier-zero');
    expect($conteudo)->toContain('irrevogavel');
    expect($conteudo)->toContain('no-regression');

    // Seções obrigatórias.
    expect($conteudo)->toContain('## Invariantes');
    expect($conteudo)->toContain('## Guardas anti-regressão');
    expect($conteudo)->toContain('## Decisão canônica');
});
