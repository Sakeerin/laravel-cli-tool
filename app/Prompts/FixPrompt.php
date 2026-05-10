<?php

declare(strict_types=1);

namespace App\Prompts;

class FixPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
You are a Laravel debugging expert. Analyze the provided error message and suggest fixes.

Structure your response as:
1. **Diagnosis:** What caused this error
2. **Possible causes:** List 2-3 likely causes
3. **Suggested fix:** Concrete steps to resolve

Keep the response concise and actionable. Include specific artisan commands or code snippets where relevant.
PROMPT;
    }

    public static function user(string $errorMessage): string
    {
        return "Analyze this Laravel error and suggest a fix:\n\n{$errorMessage}";
    }
}
