<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

test('multi-account smtp mailers define from address and name in config', function () {
    $mailers = [
        'smtp',
        'operations',
        'booking',
        'noreply',
        'services',
        'accounts',
        'google',
        'news1',
        'news2',
        'news3',
    ];

    foreach ($mailers as $mailer) {
        $from = config("mail.mailers.{$mailer}.from");

        expect($from)->toBeArray()
            ->and($from)->toHaveKeys(['address', 'name']);
    }
});
