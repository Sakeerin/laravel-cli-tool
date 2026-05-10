<?php

declare(strict_types=1);

namespace App\Prompts;

class ReviewPrompt
{
    private const MAX_DIFF_SIZE = 12000;

    public static function system(): string
    {
        return <<<'PROMPT'
You are an expert Laravel code reviewer. Review the provided code or diff and identify:
- N+1 query problems
- Missing database indexes
- Security vulnerabilities (SQL injection, XSS, mass assignment)
- Naming convention violations
- Performance issues
- Missing error handling

Format your response with:
- File name or section header (if identifiable)
- ⚠ for warnings, ✗ for errors
- Line reference if visible
- Concise description + concrete suggestion

End with a brief summary line: "X warnings, Y errors" or "No issues found" if the code looks good.
PROMPT;
    }

    public static function user(string $diff): string
    {
        if (strlen($diff) > self::MAX_DIFF_SIZE) {
            $diff = substr($diff, 0, self::MAX_DIFF_SIZE)."\n... (truncated for length)";
        }

        return "Review this code:\n\n{$diff}";
    }
}
