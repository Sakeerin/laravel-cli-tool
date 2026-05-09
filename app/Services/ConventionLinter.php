<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\LxConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ConventionLinter
{
    public function __construct(
        private readonly CommandExecutor $commandExecutor,
    ) {}

    /**
     * @param  list<string>  $targets
     * @param  list<string>  $ignorePatterns
     * @return list<string>
     */
    public function discoverPhpFiles(array $targets, string $projectRoot, array $ignorePatterns = []): array
    {
        $files = [];

        foreach ($targets as $target) {
            $target = trim($target);

            if ($target === '') {
                continue;
            }

            $absolutePath = $this->toAbsolutePath($target, $projectRoot);

            if (is_file($absolutePath) && str_ends_with($absolutePath, '.php')) {
                $relativePath = $this->relativePath($absolutePath, $projectRoot);

                if (! $this->isIgnored($relativePath, $ignorePatterns)) {
                    $files[] = $relativePath;
                }

                continue;
            }

            if (! is_dir($absolutePath)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS)
                ),
                '/^.+\.php$/i'
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $relativePath = $this->relativePath($file->getPathname(), $projectRoot);

                if ($this->isIgnored($relativePath, $ignorePatterns)) {
                    continue;
                }

                $files[] = $relativePath;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @param  list<string>  $files
     * @return array{
     *     files:list<string>,
     *     issues:list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>,
     *     summary:array{errors:int,warnings:int,fixable:int}
     * }
     */
    public function run(
        array $files,
        string $projectRoot,
        LxConfig $config,
        bool $strict = false,
        ?callable $progressCallback = null,
    ): array {
        $issues = [
            ...$this->runPhpCodeSniffer($files, $projectRoot),
            ...$this->runCustomRules($files, $projectRoot, $config, $strict, $progressCallback),
        ];

        usort(
            $issues,
            static fn (array $left, array $right): int => [$left['file'], $left['line'], $left['column']]
                <=> [$right['file'], $right['line'], $right['column']]
        );

        return [
            'files' => $files,
            'issues' => $issues,
            'summary' => [
                'errors' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'error')),
                'warnings' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'warning')),
                'fixable' => count(array_filter($issues, static fn (array $issue): bool => $issue['fixable'])),
            ],
        ];
    }

    /**
     * @param  list<string>  $files
     * @return array{exit_code:int, output:string, error_output:string}
     */
    public function fix(array $files, string $projectRoot): array
    {
        if ($files === []) {
            return [
                'exit_code' => 0,
                'output' => '',
                'error_output' => '',
            ];
        }

        return $this->commandExecutor->execute([
            PHP_BINARY,
            base_path('vendor/bin/phpcbf'),
            '--standard=PSR12',
            ...$files,
        ], $projectRoot);
    }

    /**
     * @param  list<string>  $files
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function runPhpCodeSniffer(array $files, string $projectRoot): array
    {
        if ($files === []) {
            return [];
        }

        $result = $this->commandExecutor->execute([
            PHP_BINARY,
            base_path('vendor/bin/phpcs'),
            '--standard=PSR12',
            '--report=json',
            ...$files,
        ], $projectRoot);

        if (trim($result['output']) === '') {
            return [];
        }

        $decoded = json_decode($result['output'], true);

        if (! is_array($decoded) || ! isset($decoded['files']) || ! is_array($decoded['files'])) {
            return [];
        }

        $issues = [];

        foreach ($decoded['files'] as $file => $fileData) {
            if (! isset($fileData['messages']) || ! is_array($fileData['messages'])) {
                continue;
            }

            foreach ($fileData['messages'] as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $issues[] = [
                    'file' => $this->relativePath($file, $projectRoot),
                    'line' => (int) ($message['line'] ?? 1),
                    'column' => (int) ($message['column'] ?? 1),
                    'message' => (string) ($message['message'] ?? 'Unknown PHP_CodeSniffer issue.'),
                    'source' => (string) ($message['source'] ?? 'phpcs'),
                    'severity' => strtolower((string) ($message['type'] ?? 'warning')) === 'error' ? 'error' : 'warning',
                    'fixable' => (bool) ($message['fixable'] ?? false),
                    'type' => strtolower((string) ($message['type'] ?? 'warning')),
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  list<string>  $files
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function runCustomRules(
        array $files,
        string $projectRoot,
        LxConfig $config,
        bool $strict,
        ?callable $progressCallback,
    ): array {
        $issues = [];

        foreach ($files as $file) {
            $contents = file_get_contents($projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file));

            if ($contents === false) {
                if ($progressCallback !== null) {
                    $progressCallback($file);
                }

                continue;
            }

            $issues = [
                ...$issues,
                ...$this->checkStrictTypes($file, $contents, $config, $strict),
                ...$this->checkNamingConvention($file, $contents, $config, $strict),
                ...$this->checkReturnTypes($file, $contents, $config, $strict),
                ...$this->checkMethodLength($file, $contents, $config, $strict),
            ];

            if ($progressCallback !== null) {
                $progressCallback($file);
            }
        }

        return $issues;
    }

    /**
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function checkStrictTypes(string $file, string $contents, LxConfig $config, bool $strict): array
    {
        if (! (bool) $config->get('lint.rules.require_strict_types', false)) {
            return [];
        }

        if (preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $contents) === 1) {
            return [];
        }

        return [[
            'file' => $file,
            'line' => 1,
            'column' => 1,
            'message' => 'Missing declare(strict_types=1).',
            'source' => 'lx.strict_types',
            'severity' => $strict ? 'error' : 'warning',
            'fixable' => false,
            'type' => $strict ? 'error' : 'warning',
        ]];
    }

    /**
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function checkNamingConvention(string $file, string $contents, LxConfig $config, bool $strict): array
    {
        $rules = [
            [$config->servicePath(), (string) $config->get('lint.rules.naming.service_suffix', 'Service')],
            [$config->repositoryPath(), (string) $config->get('lint.rules.naming.repository_suffix', 'Repository')],
            [$config->dtoPath(), (string) $config->get('lint.rules.naming.dto_suffix', 'Data')],
        ];

        foreach ($rules as [$directory, $suffix]) {
            if ($directory === '' || $suffix === '' || ! str_starts_with($file, trim($directory, '/').'/')) {
                continue;
            }

            $className = pathinfo($file, PATHINFO_FILENAME);

            if (str_ends_with($className, $suffix)) {
                return [];
            }

            return [[
                'file' => $file,
                'line' => $this->findClassLine($contents),
                'column' => 1,
                'message' => "Class [{$className}] must end with [{$suffix}].",
                'source' => 'lx.naming',
                'severity' => $strict ? 'error' : 'warning',
                'fixable' => false,
                'type' => $strict ? 'error' : 'warning',
            ]];
        }

        return [];
    }

    /**
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function checkReturnTypes(string $file, string $contents, LxConfig $config, bool $strict): array
    {
        if (! (bool) $config->get('lint.rules.require_return_types', false)) {
            return [];
        }

        $tokens = token_get_all($contents);
        $issues = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $nameToken = $this->findNextNamedFunctionToken($tokens, $index + 1);

            if ($nameToken === null) {
                continue;
            }

            $functionName = strtolower($nameToken['content']);

            if (in_array($functionName, ['__construct', '__destruct'], true)) {
                continue;
            }

            $closingParenthesisIndex = $this->findMatchingParenthesis($tokens, $nameToken['index']);

            if ($closingParenthesisIndex === null) {
                continue;
            }

            $nextMeaningful = $this->findNextMeaningfulToken($tokens, $closingParenthesisIndex + 1);

            if ($nextMeaningful !== null && $nextMeaningful['content'] === ':') {
                continue;
            }

            $issues[] = [
                'file' => $file,
                'line' => $nameToken['line'],
                'column' => 1,
                'message' => "Method [{$nameToken['content']}] is missing a return type.",
                'source' => 'lx.return_types',
                'severity' => $strict ? 'error' : 'warning',
                'fixable' => false,
                'type' => $strict ? 'error' : 'warning',
            ];
        }

        return $issues;
    }

    /**
     * @return list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>
     */
    private function checkMethodLength(string $file, string $contents, LxConfig $config, bool $strict): array
    {
        $maxLength = (int) $config->get('lint.rules.max_method_length', 0);

        if ($maxLength <= 0) {
            return [];
        }

        $tokens = token_get_all($contents);
        $issues = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $nameToken = $this->findNextNamedFunctionToken($tokens, $index + 1);

            if ($nameToken === null) {
                continue;
            }

            $openingBraceIndex = $this->findNextBrace($tokens, $nameToken['index']);

            if ($openingBraceIndex === null) {
                continue;
            }

            $closingBraceIndex = $this->findMatchingBrace($tokens, $openingBraceIndex);

            if ($closingBraceIndex === null) {
                continue;
            }

            $startLine = $this->tokenLine($tokens, $openingBraceIndex);
            $endLine = $this->tokenLine($tokens, $closingBraceIndex);
            $methodLength = ($endLine - $startLine) + 1;

            if ($methodLength <= $maxLength) {
                continue;
            }

            $issues[] = [
                'file' => $file,
                'line' => $nameToken['line'],
                'column' => 1,
                'message' => "Method [{$nameToken['content']}] exceeds the maximum length of {$maxLength} lines.",
                'source' => 'lx.method_length',
                'severity' => $strict ? 'error' : 'warning',
                'fixable' => false,
                'type' => $strict ? 'error' : 'warning',
            ];
        }

        return $issues;
    }

    private function toAbsolutePath(string $target, string $projectRoot): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $target) === 1 || str_starts_with($target, '/')) {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);
        }

        return rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);
    }

    private function relativePath(string $path, string $projectRoot): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

        if (str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            return substr($normalizedPath, strlen($normalizedRoot) + 1);
        }

        return ltrim($normalizedPath, '/');
    }

    /**
     * @param  list<string>  $ignorePatterns
     */
    private function isIgnored(string $relativePath, array $ignorePatterns): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        foreach ($ignorePatterns as $pattern) {
            $pattern = trim(str_replace('\\', '/', $pattern), '/');

            if ($pattern === '') {
                continue;
            }

            if ($relativePath === $pattern || str_starts_with($relativePath, $pattern.'/')) {
                return true;
            }

            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    private function findClassLine(string $contents): int
    {
        preg_match('/^.*\b(class|interface|trait)\b.*$/m', $contents, $matches, PREG_OFFSET_CAPTURE);

        if (! isset($matches[0][1])) {
            return 1;
        }

        return substr_count(substr($contents, 0, $matches[0][1]), "\n") + 1;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     * @return array{index:int,content:string,line:int}|null
     */
    private function findNextNamedFunctionToken(array $tokens, int $startIndex): ?array
    {
        $count = count($tokens);

        for ($index = $startIndex; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_STRING) {
                return [
                    'index' => $index,
                    'content' => $token[1],
                    'line' => $token[2],
                ];
            }

            if ($token === '(') {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     */
    private function findMatchingParenthesis(array $tokens, int $startIndex): ?int
    {
        $opened = 0;
        $count = count($tokens);

        for ($index = $startIndex; $index < $count; $index++) {
            $token = $tokens[$index];
            $content = is_array($token) ? $token[1] : $token;

            if ($content === '(') {
                $opened++;
            }

            if ($content === ')') {
                $opened--;

                if ($opened === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     * @return array{index:int,content:string}|null
     */
    private function findNextMeaningfulToken(array $tokens, int $startIndex): ?array
    {
        $count = count($tokens);

        for ($index = $startIndex; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return [
                'index' => $index,
                'content' => is_array($token) ? $token[1] : $token,
            ];
        }

        return null;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     */
    private function findNextBrace(array $tokens, int $startIndex): ?int
    {
        $count = count($tokens);

        for ($index = $startIndex; $index < $count; $index++) {
            $token = $tokens[$index];

            if ((is_array($token) ? $token[1] : $token) === '{') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     */
    private function findMatchingBrace(array $tokens, int $openingBraceIndex): ?int
    {
        $depth = 0;
        $count = count($tokens);

        for ($index = $openingBraceIndex; $index < $count; $index++) {
            $token = $tokens[$index];
            $content = is_array($token) ? $token[1] : $token;

            if ($content === '{') {
                $depth++;
            }

            if ($content === '}') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int|string|array{int,string,int}>  $tokens
     */
    private function tokenLine(array $tokens, int $index): int
    {
        for ($cursor = $index; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token)) {
                return $token[2] + substr_count($token[1], "\n");
            }
        }

        return 1;
    }
}
