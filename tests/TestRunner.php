<?php

/**
 * Test Runner Class - Minimal Implementation for Standalone Tests
 *
 * Provides basic test assertions without PHPUnit dependency.
 * Shared by all CLI-related tests.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

use RuntimeException;
use Throwable;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string, string> */
    private array $failures = [];

    public function test(string $name, callable $testFn): void
    {
        try {
            $testFn();
            $this->passed++;
            echo "  PASS: {$name}\n";
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[$name] = $e->getMessage();
            echo "  FAIL: {$name}\n";
            echo "        " . $e->getMessage() . "\n";
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new RuntimeException(
                $message ?: "Expected {$expectedStr}, got {$actualStr}"
            );
        }
    }

    public function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new RuntimeException($message ?: "Expected true, got false");
        }
    }

    public function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new RuntimeException($message ?: "Expected false, got true");
        }
    }

    public function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than {$expected}, got {$actual}"
            );
        }
    }

    public function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than or equal to {$expected}, got {$actual}"
            );
        }
    }

    public function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value less than {$expected}, got {$actual}"
            );
        }
    }

    public function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual > $expected) {
            throw new RuntimeException(
                $message ?: "Expected value less than or equal to {$expected}, got {$actual}"
            );
        }
    }

    public function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            $preview = strlen($haystack) > 100 ? substr($haystack, 0, 100) . '...' : $haystack;
            throw new RuntimeException(
                $message ?: "Expected string to contain '{$needle}' in: {$preview}"
            );
        }
    }

    public function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            throw new RuntimeException(
                $message ?: "Expected string NOT to contain '{$needle}'"
            );
        }
    }

    public function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new RuntimeException($message ?: "Expected non-null value");
        }
    }

    public function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            throw new RuntimeException($message ?: "Expected null value, got " . var_export($value, true));
        }
    }

    public function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new RuntimeException(
                $message ?: "Expected array to have key '{$key}'"
            );
        }
    }

    public function assertFileExists(string $path, string $message = ''): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException(
                $message ?: "Expected file to exist: {$path}"
            );
        }
    }

    public function assertFileNotExists(string $path, string $message = ''): void
    {
        if (file_exists($path)) {
            throw new RuntimeException(
                $message ?: "Expected file NOT to exist: {$path}"
            );
        }
    }

    public function assertEmpty(mixed $value, string $message = ''): void
    {
        if (!empty($value)) {
            throw new RuntimeException(
                $message ?: "Expected empty value, got " . var_export($value, true)
            );
        }
    }

    public function assertNotEmpty(mixed $value, string $message = ''): void
    {
        if (empty($value)) {
            throw new RuntimeException($message ?: "Expected non-empty value");
        }
    }

    public function assertStartsWith(string $prefix, string $string, string $message = ''): void
    {
        if (!str_starts_with($string, $prefix)) {
            throw new RuntimeException(
                $message ?: "Expected string to start with '{$prefix}'"
            );
        }
    }

    public function assertEndsWith(string $suffix, string $string, string $message = ''): void
    {
        if (!str_ends_with($string, $suffix)) {
            throw new RuntimeException(
                $message ?: "Expected string to end with '{$suffix}'"
            );
        }
    }

    public function assertMatchesRegex(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            throw new RuntimeException(
                $message ?: "Expected string to match pattern '{$pattern}'"
            );
        }
    }

    public function summary(): int
    {
        echo "\n";
        echo "==========================================\n";
        echo "Tests: " . ($this->passed + $this->failed) . ", ";
        echo "Passed: {$this->passed}, ";
        echo "Failed: {$this->failed}\n";
        echo "==========================================\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $name => $message) {
                echo "  - {$name}: {$message}\n";
            }
        }

        return $this->failed > 0 ? 1 : 0;
    }

    public function getPassed(): int
    {
        return $this->passed;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }
}
