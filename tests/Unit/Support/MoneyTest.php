<?php

use App\Support\Money;

test('formats with Bangladeshi digit grouping and the taka symbol', function () {
    expect(Money::format('1234567'))->toBe('৳ 12,34,567.00')
        ->and(Money::format('123'))->toBe('৳ 123.00')
        ->and(Money::format('12345678.5'))->toBe('৳ 1,23,45,678.50');
});

test('formats negatives with a leading minus', function () {
    expect(Money::format('-1000'))->toBe('-৳ 1,000.00');
});

test('can omit the symbol', function () {
    expect(Money::format('100', false))->toBe('100.00');
});

test('rounds half up to two decimals', function () {
    expect((string) Money::of('10.005')->getAmount())->toBe('10.01')
        ->and((string) Money::of('10.004')->getAmount())->toBe('10.00');
});

test('uses the currency symbol of other currencies', function () {
    expect(Money::format(Money::of('1500', 'USD')))->toBe('$ 1,500.00');
});

test('rates keep up to four decimals with BD grouping', function () {
    expect(Money::formatRate('1250.5000'))->toBe('1,250.50')
        ->and(Money::formatRate('1234567.1250'))->toBe('12,34,567.125')
        ->and(Money::formatRate('12.3756'))->toBe('12.3756')
        ->and(Money::formatRate('0'))->toBe('0.00')
        ->and(Money::formatRate(null))->toBe('—');
});
