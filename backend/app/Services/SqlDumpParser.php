<?php

namespace App\Services;

use RuntimeException;

class SqlDumpParser
{
    /**
     * Parse all INSERT rows for a table without loading the entire dump into memory.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseTable(string $sqlPath, string $tableName): array
    {
        $handle = fopen($sqlPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Tidak dapat membuka SQL dump: {$sqlPath}");
        }

        $rows = [];
        $createColumns = [];
        $readingCreate = false;
        $statement = '';
        $readingInsert = false;
        $insertQuoted = false;
        $insertEscaped = false;

        try {
            while (($line = fgets($handle)) !== false) {
                if (! $readingCreate && preg_match(
                    '/^CREATE TABLE `?'.preg_quote($tableName, '/').'`?\s*\(/i',
                    ltrim($line)
                )) {
                    $readingCreate = true;

                    continue;
                }

                if ($readingCreate) {
                    if (preg_match('/^\s*`([^`]+)`\s+/', $line, $match)) {
                        $createColumns[] = $match[1];
                    }

                    if (preg_match('/^\s*\)\s*(?:ENGINE|;)/i', $line)) {
                        $readingCreate = false;
                    }

                    continue;
                }

                if (! $readingInsert && preg_match(
                    '/^INSERT\s+INTO\s+`?'.preg_quote($tableName, '/').'`?(?:\s|\()/i',
                    ltrim($line)
                )) {
                    $readingInsert = true;
                    $statement = $line;
                    $insertQuoted = false;
                    $insertEscaped = false;
                } elseif ($readingInsert) {
                    $statement .= $line;
                }

                if ($readingInsert && $this->chunkCompletesStatement($line, $insertQuoted, $insertEscaped)) {
                    foreach ($this->parseInsertStatement($statement, $tableName, $createColumns) as $row) {
                        $rows[] = $row;
                    }

                    $statement = '';
                    $readingInsert = false;
                }
            }

            if ($readingInsert && trim($statement) !== '') {
                throw new RuntimeException("Statement INSERT tabel '{$tableName}' tidak selesai.");
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Memeriksa hanya potongan yang baru dibaca sambil mempertahankan status
     * quote. Ini menghindari pemindaian ulang statement besar pada setiap baris.
     */
    private function chunkCompletesStatement(string $chunk, bool &$quoted, bool &$escaped): bool
    {
        for ($i = 0, $length = strlen($chunk); $i < $length; $i++) {
            $char = $chunk[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($quoted && $char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === "'") {
                if ($quoted && ($chunk[$i + 1] ?? null) === "'") {
                    $i++;

                    continue;
                }

                $quoted = ! $quoted;

                continue;
            }

            if (! $quoted && $char === ';') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $fallbackColumns
     * @return array<int, array<string, mixed>>
     */
    private function parseInsertStatement(string $statement, string $tableName, array $fallbackColumns): array
    {
        if (! preg_match(
            '/^\s*INSERT\s+INTO\s+`?'.preg_quote($tableName, '/').'`?\s*(?:\((.*?)\))?\s*VALUES\s*(.*);\s*$/is',
            $statement,
            $match
        )) {
            throw new RuntimeException("Format INSERT tabel '{$tableName}' tidak dikenali.");
        }

        $columns = trim($match[1] ?? '') !== ''
            ? array_map(
                static fn (string $column): string => trim(trim($column), "` \t\r\n"),
                explode(',', $match[1])
            )
            : $fallbackColumns;

        if ($columns === []) {
            throw new RuntimeException("Kolom tabel '{$tableName}' tidak ditemukan.");
        }

        $rows = [];

        foreach ($this->extractTuples($match[2]) as $tupleNumber => $values) {
            if (count($values) !== count($columns)) {
                throw new RuntimeException(sprintf(
                    "Jumlah nilai tabel '%s' pada tuple %d tidak cocok: %d nilai untuk %d kolom.",
                    $tableName,
                    $tupleNumber + 1,
                    count($values),
                    count($columns)
                ));
            }

            $rows[] = array_combine($columns, $values);
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function extractTuples(string $valuesSql): array
    {
        $tuples = [];
        $values = [];
        $token = '';
        $depth = 0;
        $quoted = false;
        $escaped = false;

        for ($i = 0, $length = strlen($valuesSql); $i < $length; $i++) {
            $char = $valuesSql[$i];

            if ($depth === 0) {
                if ($char === '(') {
                    $depth = 1;
                    $values = [];
                    $token = '';
                }

                continue;
            }

            if ($escaped) {
                $token .= '\\'.$char;
                $escaped = false;

                continue;
            }

            if ($quoted && $char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === "'") {
                $token .= $char;

                if ($quoted && ($valuesSql[$i + 1] ?? null) === "'") {
                    $token .= "'";
                    $i++;

                    continue;
                }

                $quoted = ! $quoted;

                continue;
            }

            if (! $quoted) {
                if ($char === '(') {
                    $depth++;
                    $token .= $char;

                    continue;
                }

                if ($char === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $values[] = $this->decodeValue($token);
                        $tuples[] = $values;
                        $token = '';

                        continue;
                    }

                    $token .= $char;

                    continue;
                }

                if ($char === ',' && $depth === 1) {
                    $values[] = $this->decodeValue($token);
                    $token = '';

                    continue;
                }
            }

            $token .= $char;
        }

        if ($quoted || $depth !== 0) {
            throw new RuntimeException('Tuple SQL tidak lengkap atau string tidak ditutup.');
        }

        return $tuples;
    }

    private function decodeValue(string $rawValue): mixed
    {
        $value = trim($rawValue);

        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            $value = substr($value, 1, -1);
            $value = str_replace("''", "'", $value);

            return preg_replace_callback('/\\\\(.)/s', static function (array $match): string {
                return match ($match[1]) {
                    '0' => "\0",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'Z' => chr(26),
                    default => $match[1],
                };
            }, $value);
        }

        return $value;
    }
}
