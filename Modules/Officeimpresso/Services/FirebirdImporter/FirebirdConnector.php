<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Services\FirebirdImporter;

use PDO;
use PDOException;
use RuntimeException;

/**
 * FirebirdConnector — wrapper PDO Firebird pra leitura ONE-WAY do banco
 * Delphi legado WR Comercial (`.fdb`) durante migração Firebird → oimpresso.
 *
 * Wave 28-4 (G1 estado-da-arte vertical bucket, 2026-05-17) — destrava 6-7
 * prospects ComVis legacy (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart)
 * com ARR estimado R$25-72k/ano.
 *
 * ## Drivers suportados (auto-detect):
 *   1. PDO `firebird:` (ext `pdo_firebird`) — preferido, padrão PHP nativo
 *   2. mock mode (`OFFICEIMPRESSO_FB_FORCE_MOCK=true` env) — Pest CI sem ext
 *
 * ## Filosofia (Tier 0 IRREVOGÁVEL):
 *   - **Read-only**: SELECT apenas. NUNCA INSERT/UPDATE/DELETE no Firebird
 *     (one-way per ADR 0019 + lei software 9.609/98 retention legado)
 *   - **Connection pool**: por path .fdb (clientes diferentes = pools diferentes)
 *   - **Health check**: `SELECT 1 FROM RDB$DATABASE` (canônico Firebird ping)
 *   - **Timeout**: configurável via constructor, default 5s
 *   - **Encoding**: ISO-8859-1 (Windows-1252) → UTF-8 conversion automática
 *     (Delphi WR Comercial historicamente usa ISO-8859-1)
 *
 * @see Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService
 * @see memory/decisions/0019-officeimpresso-delphi-nao-autentica.md
 * @see memory/decisions/0021-officeimpresso-contrato-api-delphi.md
 */
class FirebirdConnector
{
    /** @var array<string, PDO> Pool de conexões PDO por path .fdb */
    private static array $pool = [];

    private string $fdbPath;

    private string $username;

    private string $password;

    private int $timeoutSeconds;

    private bool $forceMock;

    public function __construct(
        string $fdbPath,
        string $username = 'SYSDBA',
        string $password = 'masterkey',
        int $timeoutSeconds = 5,
        ?bool $forceMock = null,
    ) {
        $this->fdbPath = $fdbPath;
        $this->username = $username;
        $this->password = $password;
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->forceMock = $forceMock ?? (bool) (getenv('OFFICEIMPRESSO_FB_FORCE_MOCK') ?: false);
    }

    /**
     * Verifica se o driver PDO Firebird está disponível neste ambiente PHP.
     */
    public static function driverAvailable(): bool
    {
        return in_array('firebird', PDO::getAvailableDrivers(), true);
    }

    /**
     * Retorna se o connector está em mock mode (Pest sem ext firebird ou env forçada).
     */
    public function isMock(): bool
    {
        return $this->forceMock || ! self::driverAvailable();
    }

    /**
     * Health check Firebird canônico — `SELECT 1 FROM RDB$DATABASE`.
     *
     * @return array{ok: bool, mode: string, fdb_path: string, error?: string}
     */
    public function healthCheck(): array
    {
        if ($this->isMock()) {
            return [
                'ok' => true,
                'mode' => 'mock',
                'fdb_path' => $this->fdbPath,
            ];
        }

        try {
            $pdo = $this->connect();
            $stmt = $pdo->query('SELECT 1 AS ping FROM RDB$DATABASE');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            return [
                'ok' => $row !== null && (int) ($row['PING'] ?? $row['ping'] ?? 0) === 1,
                'mode' => 'live',
                'fdb_path' => $this->fdbPath,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'mode' => 'live',
                'fdb_path' => $this->fdbPath,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Conecta ao Firebird (PDO) com connection pool por path.
     *
     * @throws RuntimeException quando mock mode (use isMock() antes)
     */
    public function connect(): PDO
    {
        if ($this->isMock()) {
            throw new RuntimeException(
                'FirebirdConnector em mock mode — chame isMock() antes ou rode com ext pdo_firebird.'
            );
        }

        $key = $this->fdbPath . '|' . $this->username;
        if (isset(self::$pool[$key])) {
            return self::$pool[$key];
        }

        $dsn = sprintf('firebird:dbname=%s;charset=ISO8859_1', $this->fdbPath);
        $pdo = new PDO($dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $this->timeoutSeconds,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pool[$key] = $pdo;

        return $pdo;
    }

    /**
     * Executa SELECT read-only e retorna array de rows com encoding convertido
     * ISO-8859-1 → UTF-8.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     *
     * @throws RuntimeException se chamado em mock mode (callers checam antes)
     */
    public function selectAll(string $sql, array $params = []): array
    {
        $this->assertReadOnly($sql);

        $pdo = $this->connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn ($r) => $this->convertEncoding($r), $rows);
    }

    /**
     * Limpa o connection pool (testes, troca de tenant).
     */
    public static function flushPool(): void
    {
        self::$pool = [];
    }

    /**
     * Converte ISO-8859-1 (Delphi padrão) → UTF-8 em todos os campos string da row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function convertEncoding(array $row): array
    {
        foreach ($row as $k => $v) {
            if (! is_string($v)) {
                continue;
            }
            // Skip se já é UTF-8 valido (caso raro: clientes recentes)
            if (mb_check_encoding($v, 'UTF-8') && $v === mb_convert_encoding($v, 'UTF-8', 'UTF-8')) {
                // Heurística: se contém byte ≥0x80 mas é UTF-8 valido, manter
                continue;
            }
            $row[$k] = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        }

        return $row;
    }

    /**
     * Tier 0: garante que SQL é SELECT puro. Bloqueia DML/DDL no Firebird legacy.
     *
     * @throws RuntimeException se SQL não for read-only
     */
    private function assertReadOnly(string $sql): void
    {
        $normalized = strtoupper(ltrim($sql));
        $allowed = ['SELECT', 'WITH']; // CTE também é read-only quando começa com WITH

        foreach ($allowed as $kw) {
            if (str_starts_with($normalized, $kw)) {
                return;
            }
        }

        throw new RuntimeException(
            'FirebirdConnector é READ-ONLY (ADR 0019 one-way bridge). SQL bloqueado: '
            . substr($sql, 0, 50) . '...'
        );
    }
}
