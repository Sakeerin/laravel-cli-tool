<?php

declare(strict_types=1);

namespace App\Prompts;

class MigrationPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
You are a Laravel expert. Generate a Laravel migration file based on the user's description.

Rules:
- Use Laravel 11 anonymous class migration syntax: return new class extends Migration { ... };
- Add appropriate indexes for foreign keys and commonly filtered columns
- Use nullable() for optional fields
- Use appropriate column types: string, text, integer, decimal, boolean, json, timestamp, foreignId
- Add $table->timestamps() unless told otherwise
- Wrap schema in Schema::create() block
- Output ONLY the PHP migration file content, no explanation, no markdown

Output format: valid PHP code only, starting with <?php
PROMPT;
    }

    public static function user(string $description, string $table = ''): string
    {
        $tableHint = $table !== '' ? " for table '{$table}'" : '';

        return "Create a Laravel migration{$tableHint} for: {$description}";
    }
}
