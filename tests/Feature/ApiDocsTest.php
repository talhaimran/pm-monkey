<?php

test('openapi spec is served at /docs/api.json', function () {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonStructure(['openapi', 'info', 'paths']);
});

test('api docs ui is served at /docs/api', function () {
    $this->get('/docs/api')->assertOk();
});
