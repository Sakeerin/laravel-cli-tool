<?php

it('inspires artisans', function () {
    $this->artisan('inspire')
        ->expectsOutputToContain('lx')
        ->assertExitCode(0);
});
