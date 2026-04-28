<?php

use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\get;

test('api reports require token and return data when authorized', function(){
    Artisan::call('migrate', ['--force' => true]);
    config()->set('app.api_token', 'secret');
    $unit = \App\Models\OrganizationUnit::factory()->create();

    $unauth = get('/api/reports/growth');
    $unauth->assertStatus(401);

    $auth = get('/api/reports/growth?unit_id='.$unit->id, ['X-API-Token' => 'secret']);
    $auth->assertStatus(200);
    $json = $auth->json();
    expect($json)->toHaveKey('series');
});
