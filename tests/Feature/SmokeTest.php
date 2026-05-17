<?php

it('halaman beranda dapat diakses', function () {
    $this->get('/')->assertOk();
});

it('halaman login admin filament dapat diakses', function () {
    $this->get('/admin/login')->assertOk();
});
