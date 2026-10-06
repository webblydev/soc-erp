<?php

use App\Models\User;
use App\Support\Phone;

test('numbers are normalised to the local form', function (?string $input, ?string $expected) {
    expect(Phone::normalise($input))->toBe($expected);
})->with([
    ['+880 1711-000000', '01711000000'],
    ['8801711000000', '01711000000'],
    ['(01711) 000 000', '01711000000'],
    ['02-9123456', '029123456'],
    ['', null],
    [null, null],
]);

test('mobile and landline numbers are valid, others are not', function (string $input, bool $valid) {
    expect(Phone::isValid($input))->toBe($valid);
})->with([
    ['01711000000', true],
    ['+8801911000000', true],
    ['01211000000', false],
    ['0171100000', false],
    ['029123456', true],
    ['0312345678', true],
    ['12345', false],
    ['01711-ABC-000', false],
]);

test('the user model normalises through the same helper', function () {
    expect(User::normalisePhone('+880 1711 000000'))->toBe(Phone::normalise('+880 1711 000000'));
});
