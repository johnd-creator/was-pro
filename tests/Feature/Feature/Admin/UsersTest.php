<?php

test('example', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
