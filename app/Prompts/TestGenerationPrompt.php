<?php

declare(strict_types=1);

namespace App\Prompts;

class TestGenerationPrompt
{
    public static function system(string $framework = 'pest'): string
    {
        $syntaxGuide = $framework === 'phpunit'
            ? 'Use PHPUnit class-based syntax with extends TestCase and public function test*() methods.'
            : "Use Pest functional syntax with test('description', function(): void { ... }) and expect() assertions.";

        return <<<PROMPT
You are a Laravel testing expert. Generate comprehensive test cases for the provided PHP code.

{$syntaxGuide}

Rules:
- Test edge cases and happy paths
- Use descriptive test names that explain the scenario
- Mock external dependencies where necessary
- Start with <?php and declare(strict_types=1);
- Output ONLY the PHP test file content, no explanation, no markdown

Output format: valid PHP code only, starting with <?php
PROMPT;
    }

    public static function user(string $source, ?string $method = null): string
    {
        $methodHint = $method !== null ? " (focus on the '{$method}' method)" : '';

        return "Generate tests for this PHP code{$methodHint}:\n\n```php\n{$source}\n```";
    }
}
