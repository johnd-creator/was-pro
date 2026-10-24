<?php

use Tests\Support\DisposableDatabase;

test('database guard rejects missing explicit permission before connecting', function () {
    DisposableDatabase::assertConfiguration('testing', false, ['driver' => 'pgsql', 'database' => 'disposable_test']);
})->throws(RuntimeException::class);

test('database guard rejects a non testing environment', function () {
    DisposableDatabase::assertConfiguration('production', true, ['driver' => 'pgsql', 'database' => 'disposable_test']);
})->throws(RuntimeException::class);

test('database guard rejects a nonempty string as explicit permission', function () {
    DisposableDatabase::assertConfiguration('testing', 'no', ['driver' => 'pgsql', 'database' => 'disposable_test']);
})->throws(RuntimeException::class);

test('database guard rejects a connection url override', function () {
    DisposableDatabase::assertConfiguration('testing', true, ['driver' => 'pgsql', 'database' => 'disposable_test', 'url' => 'pgsql://elsewhere']);
})->throws(RuntimeException::class);

test('database guard rejects an unmarked database even with a test name', function () {
    DisposableDatabase::assertIdentity('disposable_test', 'disposable_test', null);
})->throws(RuntimeException::class);

test('database guard rejects a different actual database', function () {
    DisposableDatabase::assertIdentity('disposable_test', 'another_test', DisposableDatabase::MARKER);
})->throws(RuntimeException::class);

test('database guard accepts only the intended marked disposable database', function () {
    DisposableDatabase::assertConfiguration('testing', true, ['driver' => 'pgsql', 'database' => 'disposable_test']);
    DisposableDatabase::assertIdentity('disposable_test', 'disposable_test', DisposableDatabase::MARKER);

    expect(true)->toBeTrue();
});
