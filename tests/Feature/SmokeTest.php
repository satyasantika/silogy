<?php

it('halaman beranda dapat diakses', function () {
    $this->get('/')->assertOk();
});
