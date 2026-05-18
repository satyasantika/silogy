<?php

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('ping')->andReturn(true);
    Redis::shouldReceive('connection')->andReturn($connection);
});

it('returns 200 with json envelope when infrastructure is healthy', function () {
    $response = $this->getJson('/health');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.db', 'ok')
        ->assertJsonPath('data.redis', 'ok')
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure([
            'success',
            'data' => ['db', 'redis', 'disk_free'],
            'meta' => ['request_id'],
            'message',
        ]);

    expect($response->json('data.disk_free'))->toMatch('/^\d+\.\d{2}GB$/');
});

it('returns 503 when database is unavailable', function () {
    $koneksi = Mockery::mock();
    $koneksi->shouldReceive('getPdo')->andThrow(new RuntimeException('DB tidak tersedia'));

    DB::shouldReceive('connection')
        ->once()
        ->andReturn($koneksi);

    $response = $this->getJson('/health');

    $response->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'HEALTH_CHECK_FAILED')
        ->assertJsonPath('error.details.db', 'error');
});

it('is rate limited to 60 requests per minute', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/health')->assertOk();
    }

    $this->getJson('/health')->assertStatus(429);
});
