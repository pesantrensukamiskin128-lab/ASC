<?php

namespace Tests\Unit;

use App\Services\SqlDumpParser;
use PHPUnit\Framework\TestCase;

class SqlDumpParserTest extends TestCase
{
    private string $sqlPath;

    protected function tearDown(): void
    {
        if (isset($this->sqlPath) && file_exists($this->sqlPath)) {
            unlink($this->sqlPath);
        }
    }

    public function test_it_parses_multiline_inserts_and_mysql_escaped_values(): void
    {
        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-sql-');
        file_put_contents($this->sqlPath, <<<'SQL'
CREATE TABLE `people` (
  `id` bigint NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `notes` text,
  `optional` varchar(20) DEFAULT NULL
);
INSERT INTO `people` (`id`, `name`, `notes`, `optional`) VALUES
(1, 'O\'Brien, Ana', 'Uses (parentheses)', NULL),
(2, 'Doubled ''quote''', 'line\nnext', '');
SQL);

        $rows = (new SqlDumpParser)->parseTable($this->sqlPath, 'people');

        $this->assertCount(2, $rows);
        $this->assertSame("O'Brien, Ana", $rows[0]['name']);
        $this->assertSame('Uses (parentheses)', $rows[0]['notes']);
        $this->assertNull($rows[0]['optional']);
        $this->assertSame("Doubled 'quote'", $rows[1]['name']);
        $this->assertSame("line\nnext", $rows[1]['notes']);
        $this->assertSame('', $rows[1]['optional']);
    }

    public function test_it_uses_create_table_columns_when_insert_omits_column_list(): void
    {
        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-sql-');
        file_put_contents($this->sqlPath, <<<'SQL'
CREATE TABLE `settings` (
  `key` varchar(50) NOT NULL,
  `value` varchar(100) DEFAULT NULL
);
INSERT INTO `settings` VALUES
('site_name', 'ASC');
SQL);

        $rows = (new SqlDumpParser)->parseTable($this->sqlPath, 'settings');

        $this->assertSame([
            ['key' => 'site_name', 'value' => 'ASC'],
        ], $rows);
    }
}
