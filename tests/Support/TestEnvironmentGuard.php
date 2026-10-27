<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;
use Throwable;

/**
 * Pure, isolated validation logic for test database connection safety.
 * This class contains zero side effects, makes no database calls, and requires
 * no Laravel framework bootstrap to verify configurations.
 */
class TestEnvironmentGuard
{
    /**
     * Inspect and validate the resolved database configuration.
     *
     * @param string $defaultConnection The name of the default connection (e.g. 'pgsql')
     * @param array<string, mixed> $connections Map of all configured connection settings
     * @throws RuntimeException If any connection violates isolation or targets remote/cloud DB.
     */
    public static function assertSafeEnvironment(string $defaultConnection, array $connections): void
    {
        // Rule 1: The default connection must exist in the configuration
        if (!isset($connections[$defaultConnection]) || !is_array($connections[$defaultConnection])) {
            throw new RuntimeException(
                "CRITICAL TEST ISOLATION GUARD: Default connection [{$defaultConnection}] is not configured or invalid."
            );
        }

        // Rule 2: Inspect ALL connections in configuration to prevent backdoor routing
        foreach ($connections as $name => $config) {
            if (!is_array($config)) {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Connection [{$name}] configuration is invalid."
                );
            }

            self::validateConnection((string) $name, $config, $name === $defaultConnection);
        }
    }

    /**
     * Validate an individual connection configuration.
     *
     * @param string $name
     * @param array<string, mixed> $config
     * @param bool $isDefault
     * @throws RuntimeException
     */
    public static function validateConnection(string $name, array $config, bool $isDefault): void
    {
        $rawDriver = strtolower(trim((string) ($config['driver'] ?? '')));
        $rawHost = strtolower(trim((string) ($config['host'] ?? '')));
        $rawDatabase = strtolower(trim((string) ($config['database'] ?? '')));
        $rawUrl = trim((string) ($config['url'] ?? ''));
        $rawUrlLower = strtolower($rawUrl);

        // Check A: Guard against Supabase cloud infrastructure across all raw fields
        self::assertNoSupabaseSignatures($name, $rawHost, $rawUrlLower);

        // Check B: Parse URL if present and evaluate effective configuration
        $effectiveConfig = $config;
        if ($rawUrl !== '') {
            $effectiveConfig = self::resolveUrlConfiguration($name, $rawUrl, $config);
        }

        $driver = strtolower(trim((string) ($effectiveConfig['driver'] ?? '')));
        $host = strtolower(trim((string) ($effectiveConfig['host'] ?? '')));
        $database = strtolower(trim((string) ($effectiveConfig['database'] ?? '')));

        // Check C: Validate driver exists
        if ($driver === '') {
            throw new RuntimeException(
                "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                "Connection [{$name}] has no database driver configured."
            );
        }

        // Check D: Network drivers (pgsql, mysql, mariadb, sqlsrv) MUST NOT target remote hosts or system DBs
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlsrv'], true)) {
            if ($host === '') {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] has no host defined."
                );
            }

            $allowedLocalHosts = ['127.0.0.1', 'localhost', '::1'];

            if (!in_array($host, $allowedLocalHosts, true)) {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] host [{$host}] is not a recognized local test host."
                );
            }

            // Check E: Forbid using default system maintenance databases
            $forbiddenSystemDatabases = ['postgres', 'public', 'template0', 'template1', 'mysql', 'sys', 'information_schema'];
            if (in_array($database, $forbiddenSystemDatabases, true)) {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] targets system/shared database [{$database}]. Use a dedicated test database (e.g. aquatara_test)."
                );
            }

            // Check F: Must have a non-empty database name
            if ($database === '') {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] has an empty database name."
                );
            }
        }
    }

    /**
     * Parse and normalize database URL, mirroring Laravel's ConfigurationUrlParser.
     *
     * @param string $name Connection name
     * @param string $url Connection URL
     * @param array<string, mixed> $config Original connection configuration
     * @return array<string, mixed> Merged effective configuration
     * @throws RuntimeException
     */
    protected static function resolveUrlConfiguration(string $name, string $url, array $config): array
    {
        // 1. Basic structural parsing validation using PHP's native parse_url
        $parts = parse_url($url);
        if ($parts === false || !is_array($parts)) {
            throw new RuntimeException(
                "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                "Connection [{$name}] database URL is malformed."
            );
        }

        // 2. Decode and inspect URL host and path if present
        $urlHost = isset($parts['host']) ? strtolower(rawurldecode($parts['host'])) : '';
        $urlPath = isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : '';

        // If scheme is provided, determine driver
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $driver = strtolower((string) ($config['driver'] ?? ''));
        $resolvedDriver = match ($scheme) {
            'postgres', 'postgresql' => 'pgsql',
            'mysql', 'mysql2' => 'mysql',
            'sqlite', 'sqlite3' => 'sqlite',
            default => $scheme ?: $driver,
        };

        // For network drivers, a URL must contain a valid, non-empty host
        if (in_array($resolvedDriver, ['pgsql', 'mysql', 'mariadb', 'sqlsrv'], true)) {
            if ($urlHost === '') {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] URL has no host component defined."
                );
            }

            // Reject if URL host contains any remote or Supabase target
            self::assertNoSupabaseSignatures($name, $urlHost, strtolower($url));

            $allowedLocalHosts = ['127.0.0.1', 'localhost', '::1'];
            if (!in_array($urlHost, $allowedLocalHosts, true)) {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] URL host [{$urlHost}] is not a recognized local test host."
                );
            }

            // Check if URL specifies a system database
            $forbiddenSystemDatabases = ['postgres', 'public', 'template0', 'template1', 'mysql', 'sys', 'information_schema'];
            if ($urlPath !== '' && in_array(strtolower($urlPath), $forbiddenSystemDatabases, true)) {
                throw new RuntimeException(
                    "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                    "Connection [{$name}] URL targets system/shared database [{$urlPath}]. Use a dedicated test database (e.g. aquatara_test)."
                );
            }
        }

        // 3. Hydrate via Laravel's ConfigurationUrlParser to ensure exact runtime equivalence
        try {
            $urlParser = new ConfigurationUrlParser();
            return $urlParser->parseConfiguration($config);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                "Connection [{$name}] URL parsing failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Check for any Supabase or cloud pooler signatures.
     *
     * @param string $name
     * @param string $host
     * @param string $urlLower
     * @throws RuntimeException
     */
    protected static function assertNoSupabaseSignatures(string $name, string $host, string $urlLower): void
    {
        if (
            str_contains($host, 'supabase.com') ||
            str_contains($urlLower, 'supabase.com') ||
            str_contains($host, 'supabase.co') ||
            str_contains($urlLower, 'supabase.co') ||
            str_contains($host, 'pooler.supabase') ||
            str_contains($urlLower, 'pooler.supabase') ||
            str_contains($host, 'pooler') ||
            str_contains($urlLower, 'pooler')
        ) {
            throw new RuntimeException(
                "CRITICAL TEST ISOLATION GUARD: Refusing to boot test suite! " .
                "Connection [{$name}] host points to Supabase cloud infrastructure."
            );
        }
    }
}
