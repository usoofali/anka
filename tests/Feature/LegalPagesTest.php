<?php

test('guest can view terms page', function () {
    $this->get(route('terms'))->assertOk();
});

test('guest can view privacy page', function () {
    $this->get(route('privacy'))->assertOk();
});
