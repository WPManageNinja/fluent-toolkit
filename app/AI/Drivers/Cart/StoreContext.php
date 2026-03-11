<?php

namespace FluentToolkit\AI\Drivers\Cart;

class StoreContext
{
    public function getCurrencyContext(): array
    {
        $settings = [];

        if (class_exists('\FluentCart\Api\CurrencySettings')) {
            $resolved = \FluentCart\Api\CurrencySettings::get();
            if (is_array($resolved)) {
                $settings = $resolved;
            }
        }

        if (!$settings) {
            $storeSettings = get_option('fluent_cart_store_settings', []);
            if (is_array($storeSettings)) {
                $settings = $storeSettings;
            }
        }

        $currency = strtoupper(trim((string) ($settings['currency'] ?? 'USD')));
        $currencySign = trim((string) ($settings['currency_sign'] ?? ''));
        $currencyPosition = trim((string) ($settings['currency_position'] ?? 'before'));
        $locale = trim((string) ($settings['locale'] ?? 'auto'));
        $decimalSeparatorSetting = trim((string) ($settings['decimal_separator'] ?? 'dot'));
        $decimalSeparator = $decimalSeparatorSetting === 'comma' ? ',' : '.';
        $decimalPoints = isset($settings['decimal_points']) ? absint($settings['decimal_points']) : 2;
        $isZeroDecimal = !empty($settings['is_zero_decimal']);

        if ($currency === '') {
            $currency = 'USD';
        }

        if ($currencySign === '') {
            $currencySign = $currency;
        }

        if ($isZeroDecimal) {
            $decimalPoints = 0;
        } elseif ($decimalPoints === 0) {
            $decimalPoints = 2;
        }

        return [
            'currency' => $currency,
            'currency_sign' => $currencySign,
            'currency_position' => $currencyPosition ?: 'before',
            'locale' => $locale ?: 'auto',
            'decimal_separator' => $decimalSeparator,
            'decimal_points' => $decimalPoints,
            'is_zero_decimal' => $isZeroDecimal,
            'format_example' => $this->formatCurrencyExample(
                $currency,
                $currencySign,
                $currencyPosition ?: 'before',
                $decimalSeparator,
                $decimalPoints
            ),
        ];
    }

    private function formatCurrencyExample(
        string $currency,
        string $currencySign,
        string $currencyPosition,
        string $decimalSeparator,
        int $decimalPoints
    ): string {
        $amount = '123';
        if ($decimalPoints > 0) {
            $fraction = str_pad('45', $decimalPoints, '0');
            $amount .= $decimalSeparator . substr($fraction, 0, $decimalPoints);
        }

        return match ($currencyPosition) {
            'after' => $amount . $currencySign,
            'iso_before' => $currency . ' ' . $amount,
            'iso_after' => $amount . ' ' . $currency,
            'symbool_before_iso' => $currencySign . $amount . ' ' . $currency,
            'symbool_after_iso' => $currency . ' ' . $amount . $currencySign,
            'symbool_and_iso' => $currency . ' ' . $currencySign . $amount,
            default => $currencySign . $amount,
        };
    }
}
