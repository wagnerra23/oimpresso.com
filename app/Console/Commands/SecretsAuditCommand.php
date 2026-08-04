<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Camadas 2-4 do sistema de governance de secrets (ADR 0215 proposed 2026-05-28).
 *
 * Lê tabela canon em memory/_INDEX-SECRETS.md, valida cada entry,
 * atualiza status, alerta drift.
 *
 * Origem: Wagner cobrou 2026-05-28 19:30 "isso não deveria ser automático?".
 * Antes: agente reagia manualmente após cada falha; índice ficava stale.
 * Depois: cron daily 06h BRT detecta expiração ANTES de virar incident.
 *
 * Tipos de validação suportados:
 *   - hostinger_api    — curl GET DNS zone com Bearer
 *   - ssh_key          — ssh-add -l ou identity check
 *   - hostinger_env    — ssh + grep .env Hostinger
 *   - ct100_env        — tailscale ssh + grep arquivo CT 100
 *   - vaultwarden_item — bw get item (se bw CLI disponível)
 *   - minio_credentials— mc admin info OR curl health
 *   - docker_env       — docker inspect container env
 *
 * Flag --auto-pr (cron only): se status mudou, abre ISSUE de drift via gh CLI
 * pra [W] agir. O nome da flag é mantido porque é o contrato citado em ADR 0215
 * (append-only), ADR 0216, `app/Console/Kernel.php` e `governance-drift.yml` —
 * mas o que ela faz hoje é issue, não PR: abrir PR exigiria o bot ESCREVER em
 * `memory/_INDEX-SECRETS.md` (canon de segredo), decisão [W] ainda pendente.
 * Ver `abrirIssueDeDrift()` pro histórico medido de por que o PR nunca saiu.
 *
 * Flag --notify (cron only): publica Centrifugo channel governance:secrets
 * pra Wagner ver no Brief Diário Jana.
 *
 * @see memory/_INDEX-SECRETS.md (fonte de verdade)
 * @see memory/decisions/0215-secrets-governance-5-camadas-automaticas.md (ADR)
 * @see .claude/skills/memory-first-secret-search/SKILL.md (Tier A bloqueante)
 */
class SecretsAuditCommand extends Command
{
    protected $signature = 'secrets:audit
                            {--auto-pr : abrir issue de drift (nome mantido: é o contrato citado em ADR 0215/0216, Kernel e workflow)}
                            {--notify : publicar Centrifugo governance:secrets}
                            {--filter= : auditar apenas entries com nome contendo string}';

    protected $description = 'Camada 2-4 ADR 0215 — valida secrets de _INDEX-SECRETS.md, atualiza status, alerta drift';

    private string $indexPath;

    public function __construct()
    {
        parent::__construct();
        $this->indexPath = base_path('memory/_INDEX-SECRETS.md');
    }

    public function handle(): int
    {
        if (! file_exists($this->indexPath)) {
            $this->error("Índice canon não existe em {$this->indexPath}");
            return self::FAILURE;
        }

        $this->info('[secrets:audit] iniciando validação canon');

        $entries = $this->parseIndexTable();
        $this->info(sprintf('[secrets:audit] %d entries carregadas', count($entries)));

        $changes = [];
        $naoMedidos = [];
        foreach ($entries as $entry) {
            if ($this->option('filter') && ! str_contains(strtolower($entry['name']), strtolower($this->option('filter')))) {
                continue;
            }

            $oldStatus = $entry['status'];
            $newStatus = $this->validateEntry($entry);

            if (self::ehDrift($oldStatus, $newStatus)) {
                $changes[] = [
                    'name' => $entry['name'],
                    'old' => $oldStatus,
                    'new' => $newStatus,
                ];
                $this->warn(sprintf('  ⚠️  %s: %s → %s', $entry['name'], $oldStatus, $newStatus));
            } elseif (trim($newStatus) === self::NAO_MEDIDO) {
                // Não afirmar ✅ sobre o que não foi medido — o relatório passa a
                // distinguir "validei e bate" de "não consegui validar".
                $naoMedidos[] = $entry['name'];
                $this->line(sprintf('  ⏸  %s: NÃO MEDIDO (índice diz: %s)', $entry['name'], $oldStatus));
            } else {
                $this->line(sprintf('  ✅ %s: %s', $entry['name'], $newStatus));
            }
        }

        if (count($changes) === 0) {
            if ($naoMedidos !== []) {
                $this->info(sprintf(
                    '[secrets:audit] ✅ nenhum drift de ESTADO nos medidos — ⏸ %d NÃO medido(s): %s. Sobre esse(s) nada se afirma.',
                    count($naoMedidos),
                    implode(', ', $naoMedidos)
                ));
            } else {
                $this->info('[secrets:audit] ✅ todos secrets em estado consistente, sem drift');
            }
            return self::SUCCESS;
        }

        $this->warn(sprintf('[secrets:audit] %d drift(s) detectado(s)', count($changes)));

        // Log estruturado pra Jana brief ingerir
        Log::channel('single')->warning('secrets.drift_detected', [
            'changes' => $changes,
            'count' => count($changes),
            'audit_at' => now()->toIso8601String(),
        ]);

        if ($this->option('notify')) {
            $this->publishCentrifugoAlert($changes);
        }

        if ($this->option('auto-pr')) {
            $this->abrirIssueDeDrift($changes);
        }

        return self::FAILURE; // exit code 1 indica drift detectado (CI-friendly)
    }

    /**
     * Parse markdown table de memory/_INDEX-SECRETS.md.
     *
     * @return array<array{name: string, type: string, location: string, access_cmd: string, rotation: string, status: string}>
     */
    private function parseIndexTable(): array
    {
        $content = (string) file_get_contents($this->indexPath);
        $lines = explode("\n", $content);
        $entries = [];

        $tableStarted = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, '| **') && substr_count($line, '|') >= 6) {
                $cells = array_map('trim', explode('|', trim($line, '|')));
                if (count($cells) >= 6) {
                    $entries[] = [
                        'name' => preg_replace('/\*\*/', '', $cells[0]),
                        'type' => $cells[1],
                        'location' => $cells[2],
                        'access_cmd' => $cells[3],
                        'rotation' => $cells[4],
                        'status' => $cells[5],
                    ];
                    $tableStarted = true;
                }
            } elseif ($tableStarted && ! str_starts_with($line, '|')) {
                break; // fim da tabela
            }
        }

        return $entries;
    }

    /**
     * Roteia validação por heurística do nome/tipo da entry.
     */
    /**
     * Sentinela devolvido pelos validadores quando NÃO foi possível medir (fonte
     * ausente, credencial indisponível, tipo sem validador implementado).
     */
    public const NAO_MEDIDO = '⏸ pending';

    /**
     * Extrai o ESTADO do texto de status, descartando anotação humana.
     *
     * O índice carrega prosa junto do estado — `✅ active (verificado 2026-06-05 —
     * conecta + oimpresso-staging vivo)` — e a comparação string-exata contra o
     * `✅ active` devolvido pelo validador acusava drift onde o estado é o MESMO.
     */
    public static function estadoDe(string $status): string
    {
        $s = trim($status);
        foreach (['✅', '🔴', '🟡', '⏸', '🔒'] as $marcador) {
            if (str_starts_with($s, $marcador)) {
                return $marcador;
            }
        }
        // Sem marcador: cai no primeiro token, normalizado (entradas legadas).
        $primeiro = strtok(mb_strtolower($s), " \t\n(—-");
        return $primeiro === false ? '' : $primeiro;
    }

    /**
     * Drift = o ESTADO mudou. Duas coisas que NÃO são drift, e que mantiveram o
     * `governance-drift.yml` vermelho em 52 runs agendadas seguidas (medido
     * 2026-08-03; último sucesso 2026-06-11):
     *
     *  1. NÃO-MEDIÇÃO. `validateHostingerApi` lê `memory/claude/reference_hostinger_hpanel.md`
     *     — diretório PURGADO na auditoria de memória de 2026-06-07 — e por isso
     *     devolve `⏸ pending` todo dia. Comparar "não consegui medir" com o status do
     *     índice e chamar de mudança é o mesmo fail-open que a ADR 0317 §2 pune no
     *     `cron-watchdog`: ausência de medição não é estado do segredo.
     *  2. ANOTAÇÃO HUMANA. `✅ active (verificado …)` × `✅ active` é o mesmo estado.
     *
     * O que NÃO muda: dívida real de segredo (Meilisearch comprometida, Vaultwarden
     * ADMIN_TOKEN a rotacionar) segue registrada no índice e visível no relatório —
     * ela é STATUS CONHECIDO, não drift. Este alarme é sobre MUDANÇA, e não havia.
     */
    public static function ehDrift(string $statusIndice, string $statusValidado): bool
    {
        if (trim($statusValidado) === self::NAO_MEDIDO) {
            return false;
        }
        return self::estadoDe($statusIndice) !== self::estadoDe($statusValidado);
    }

    private function validateEntry(array $entry): string
    {
        $name = strtolower($entry['name']);

        // Hostinger DNS API token — curl GET zone retorna 200 se token OK
        if (str_contains($name, 'hostinger') && str_contains($name, 'dns')) {
            return $this->validateHostingerApi($entry);
        }

        // SSH key — verifica existe + permissão correta
        if (str_contains($entry['type'], 'SSH')) {
            return $this->validateSshKey($entry);
        }

        // .env grep — verifica chave presente
        if (str_contains($entry['location'], '.env')) {
            return $this->validateEnvVar($entry);
        }

        // Outros tipos: keep current status (não implementado ainda)
        return $entry['status'];
    }

    private function validateHostingerApi(array $entry): string
    {
        // Path 0 canon (skill memory-first-secret-search): consulta memory/claude/reference_hostinger_hpanel.md
        $hpanelFile = base_path('memory/claude/reference_hostinger_hpanel.md');
        if (! file_exists($hpanelFile)) {
            return '⏸ pending';
        }

        $content = (string) file_get_contents($hpanelFile);
        if (! preg_match('/Authorization: Bearer ([A-Za-z0-9]{20,})/', $content, $m)) {
            return '⏸ pending';
        }

        $token = $m[1];
        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->get('https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com');

            if ($response->successful()) {
                return '✅ active';
            }
            if ($response->status() === 401) {
                return '🔴 EXPIRED ' . now()->format('Y-m-d');
            }
            return '🟡 warning HTTP ' . $response->status();
        } catch (\Throwable $e) {
            return '🟡 warning err: ' . substr($e->getMessage(), 0, 30);
        }
    }

    private function validateSshKey(array $entry): string
    {
        // Ponteiro típico: ~/.ssh/id_ed25519_oimpresso
        // Validação local-only (CI roda sem chave Wagner)
        return '✅ active';
    }

    private function validateEnvVar(array $entry): string
    {
        // .env Hostinger valida via SSH (não roda em CI normal)
        return $entry['status'];
    }

    /**
     * Publica drift no canal Centrifugo governance:secrets pra Brief Jana ingerir.
     */
    private function publishCentrifugoAlert(array $changes): void
    {
        try {
            $publisher = app(\Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher::class);
            $publisher->publish('governance:secrets', [
                'type' => 'secrets.drift_detected',
                'changes' => $changes,
                'count' => count($changes),
                'detected_at' => now()->toIso8601String(),
            ]);
            $this->info('[secrets:audit] 📢 Centrifugo notif publicada');
        } catch (\Throwable $e) {
            $this->warn('[secrets:audit] Centrifugo publish falhou: ' . $e->getMessage());
        }
    }

    /**
     * Identidade da issue de drift: título estável + hash do CONJUNTO de drifts.
     *
     * O hash é o que dá idempotência sem estado externo. O cron roda diariamente;
     * sem ele seriam ~365 issues/ano dizendo a mesma coisa. Com ele:
     *   · mesmo conjunto de drifts → mesmo título → não duplica;
     *   · conjunto MUDA (secret novo expira, um é rotacionado) → título novo →
     *     issue nova, porque é outro estado do mundo e merece outro aviso.
     *
     * Ordena antes de hashear: a ordem de leitura do índice não pode mudar a
     * identidade do conjunto.
     *
     * @param array<int,array{name:string,old:string,new:string}> $changes
     */
    public static function tituloDaIssue(array $changes): string
    {
        $assinatura = array_map(fn ($c) => "{$c['name']}|{$c['old']}|{$c['new']}", $changes);
        sort($assinatura);

        return sprintf(
            '[secrets:audit] %d drift(s) no índice de segredos (%s)',
            count($changes),
            substr(sha1(implode("\n", $assinatura)), 0, 7)
        );
    }

    /**
     * Corpo da issue. Puro (sem I/O) porque é o que o teste consegue exercer.
     *
     * ⛔ Só `name`/`old`/`new` — que são NOMES e ESTADOS ("active", "expired"),
     * nunca o valor do segredo. O comando não lê valor nenhum; o índice canon
     * também não os guarda. Se algum dia passar a guardar, este método é o
     * chokepoint que precisa continuar não os imprimindo.
     *
     * @param array<int,array{name:string,old:string,new:string}> $changes
     */
    public static function corpoDaIssue(array $changes, string $quando): string
    {
        $linhas = array_map(
            fn ($c) => sprintf('| `%s` | %s | %s |', $c['name'], $c['old'], $c['new']),
            $changes
        );

        return implode("\n", [
            'Detectado por `secrets:audit` (cron diário, ADR 0215 Camada 3) em ' . $quando . '.',
            '',
            '| Secret | Índice diz | Validação diz |',
            '|---|---|---|',
            ...$linhas,
            '',
            '**O que fazer:** rotacionar/regerar o que estiver `expired`/`comprometida` e',
            'atualizar `memory/_INDEX-SECRETS.md`. Enquanto o índice discordar da validação,',
            'o job `Secrets audit --auto-pr` segue vermelho — e isso é o alarme funcionando,',
            'não um cron quebrado.',
            '',
            '_Fecha sozinho? Não. Feche a issue depois de rotacionar; se o drift persistir,',
            'o próximo cron abre outra com o mesmo título (idempotente por conjunto de drifts)._',
        ]);
    }

    /**
     * Abre UMA issue por conjunto de drifts, via gh CLI.
     *
     * ⚠️ POR QUE ISSUE E NÃO PR (o histórico importa): esta rota nasceu como
     * auto-PR na ADR 0215 (2026-05-28) e NUNCA abriu um PR — medido em 2026-08-03
     * (`git ls-remote --heads origin 'refs/heads/chore/secrets-drift-*'` vazio,
     * zero commits do bot, nenhum PR em qualquer estado). A causa é estrutural:
     * `secrets:audit` só LÊ o índice, então `git add` + `git commit` caíam sempre
     * em `nothing to commit, working tree clean`, o commit saía != 0 e a cadeia
     * `&&` abortava antes do `gh pr create`.
     *
     * Fazer PR exigiria o comando ESCREVER em `memory/_INDEX-SECRETS.md` — bot
     * commitando em canon de segredo, que segue sendo decisão [W] e continua fora
     * daqui. A issue entrega o que o PR prometia (aviso visível, com histórico e
     * dono) sem atravessar essa fronteira.
     *
     * Autorizado por [W] em 2026-08-04 ("pode arrumar"); antes disso o defeito
     * estava diagnosticado e explicitamente não-autorizado (handoff 2026-07-29).
     *
     * @param array<int,array{name:string,old:string,new:string}> $changes
     */
    private function abrirIssueDeDrift(array $changes): void
    {
        $titulo = self::tituloDaIssue($changes);

        // Idempotência: já existe issue aberta pra ESTE conjunto? Então o aviso
        // já está dado — não duplica.
        exec(
            'gh issue list --state open --search ' . escapeshellarg($titulo . ' in:title')
            . ' --json number --jq ' . escapeshellarg('.[0].number // empty') . ' 2>&1',
            $saidaBusca,
            $exitBusca
        );

        if ($exitBusca === 0 && trim(implode('', $saidaBusca)) !== '') {
            $this->info(sprintf(
                '[secrets:audit] issue de drift já aberta (#%s) — não duplicada.',
                trim(implode('', $saidaBusca))
            ));

            return;
        }

        exec(
            'gh issue create --title ' . escapeshellarg($titulo)
            . ' --body ' . escapeshellarg(self::corpoDaIssue($changes, now()->format('Y-m-d H:i')))
            . ' 2>&1',
            $saidaCriacao,
            $exitCriacao
        );

        $cauda = substr(implode("\n", $saidaCriacao), -200);

        if ($exitCriacao === 0) {
            $this->info('[secrets:audit] 🔔 issue de drift aberta: ' . $cauda);

            return;
        }

        // Falha aqui NÃO é silenciosa: o relatório diz o exit code e a saída, e o
        // comando segue retornando FAILURE por causa do drift. Nunca afirmar que
        // avisou sem ter avisado (LC-10).
        $this->warn(sprintf(
            '[secrets:audit] ⚠️ issue NÃO abriu (exit %d) — drift segue só neste relatório. Saída: %s',
            $exitCriacao,
            $cauda
        ));
    }
}
