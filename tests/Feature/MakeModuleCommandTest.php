<?php

declare(strict_types=1);

test('it generates all module files with --all flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot, [
            'App\\' => 'app/',
            'Database\\Seeders\\' => 'database/seeders/',
        ]);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:module', ['name' => 'Billing', '--all' => true])
                ->expectsOutputToContain('Created app/Models/Billing.php')
                ->expectsOutputToContain('Created app/Http/Controllers/BillingController.php')
                ->expectsOutputToContain('Created app/Services/BillingService.php')
                ->expectsOutputToContain('Created app/Contracts/BillingServiceInterface.php')
                ->expectsOutputToContain('Created app/Repositories/BillingRepository.php')
                ->expectsOutputToContain('Created app/Contracts/BillingRepositoryInterface.php')
                ->expectsOutputToContain('Created app/Policies/BillingPolicy.php')
                ->expectsOutputToContain('create_billings_table.php')
                ->expectsOutputToContain('Created database/seeders/BillingSeeder.php')
                ->expectsOutputToContain('Created tests/Feature/BillingControllerTest.php')
                ->expectsOutputToContain('Created tests/Unit/Services/BillingServiceTest.php')
                ->expectsOutputToContain('Created routes/billing.php')
                ->assertExitCode(0);
        });

        // Verify key file contents
        $ds = DIRECTORY_SEPARATOR;
        $modelPath = $projectRoot.$ds.'app'.$ds.'Models'.$ds.'Billing.php';
        $controllerPath = $projectRoot.$ds.'app'.$ds.'Http'.$ds.'Controllers'.$ds.'BillingController.php';
        $servicePath = $projectRoot.$ds.'app'.$ds.'Services'.$ds.'BillingService.php';
        $migrationDir = $projectRoot.$ds.'database'.$ds.'migrations';
        $routesPath = $projectRoot.$ds.'routes'.$ds.'billing.php';

        expect(is_file($modelPath))->toBeTrue()
            ->and(file_get_contents($modelPath))->toContain('class Billing extends Model');

        expect(is_file($controllerPath))->toBeTrue()
            ->and(file_get_contents($controllerPath))->toContain('class BillingController')
            ->and(file_get_contents($controllerPath))->toContain('public function index()');

        expect(is_file($servicePath))->toBeTrue()
            ->and(file_get_contents($servicePath))->toContain('class BillingService implements BillingServiceInterface');

        $migrations = glob($migrationDir.$ds.'*_create_billings_table.php') ?: [];
        expect($migrations)->not->toBeEmpty()
            ->and(file_get_contents($migrations[0]))->toContain("Schema::create('billings'");

        expect(is_file($routesPath))->toBeTrue()
            ->and(file_get_contents($routesPath))->toContain("Route::resource('billing', BillingController::class)");
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it generates only selected files with individual flags', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:module', ['name' => 'Invoice', '--model' => true, '--service' => true])
                ->expectsOutputToContain('Created app/Models/Invoice.php')
                ->expectsOutputToContain('Created app/Services/InvoiceService.php')
                ->assertExitCode(0);
        });

        $ds = DIRECTORY_SEPARATOR;
        expect(is_file($projectRoot.$ds.'app'.$ds.'Models'.$ds.'Invoice.php'))->toBeTrue();
        expect(is_file($projectRoot.$ds.'app'.$ds.'Services'.$ds.'InvoiceService.php'))->toBeTrue();
        // Controller should NOT be created
        expect(is_file($projectRoot.$ds.'app'.$ds.'Http'.$ds.'Controllers'.$ds.'InvoiceController.php'))->toBeFalse();
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it warns when no flags are provided', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:module', ['name' => 'Order'])
                ->expectsOutputToContain('No files to generate')
                ->assertExitCode(0);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when a file already exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $modelDir = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models';
        mkdir($modelDir, 0777, true);
        file_put_contents($modelDir.DIRECTORY_SEPARATOR.'Payment.php', '<?php');

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:module', ['name' => 'Payment', '--model' => true])
                ->expectsOutputToContain('File already exists: app/Models/Payment.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it generates a snake_case table name for PascalCase module names', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:module', ['name' => 'UserProfile', '--migration' => true])
                ->expectsOutputToContain('create_user_profiles_table.php')
                ->assertExitCode(0);
        });

        $migrationDir = $projectRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $migrations = glob($migrationDir.DIRECTORY_SEPARATOR.'*_create_user_profiles_table.php') ?: [];

        expect($migrations)->not->toBeEmpty()
            ->and(file_get_contents($migrations[0]))->toContain("Schema::create('user_profiles'");
    } finally {
        deleteDirectory($projectRoot);
    }
});
