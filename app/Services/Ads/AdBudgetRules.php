<?php

namespace App\Services\Ads;

/**
 * Budget amounts are sent to Zernio/Meta in the ad account's billing currency (whole units).
 */
final class AdBudgetRules
{
  /** @var array<string, float> */
  private const DEFAULT_MINIMUMS = [
    'USD' => 2.0,
    'NGN' => 1762.0,
    'EUR' => 2.0,
    'GBP' => 2.0,
    'CAD' => 2.0,
    'AUD' => 2.0,
    'INR' => 100.0,
    'ZAR' => 50.0,
  ];

  /** @var array<string, string> */
  private const SYMBOLS = [
    'USD' => '$',
    'NGN' => '₦',
    'EUR' => '€',
    'GBP' => '£',
    'CAD' => 'CA$',
    'AUD' => 'A$',
    'INR' => '₹',
    'ZAR' => 'R',
  ];

  public static function normalizeCurrency(?string $currency): string
  {
    $code = strtoupper(trim((string) $currency));

    return $code !== '' ? $code : (string) config('promotion.ads.default_budget_currency', 'USD');
  }

  public static function minAmount(?string $currency): float
  {
    $code = self::normalizeCurrency($currency);
    $configured = config('promotion.ads.min_budget_by_currency', []);

    if (is_array($configured) && isset($configured[$code])) {
      return (float) $configured[$code];
    }

    return self::DEFAULT_MINIMUMS[$code] ?? (float) config('promotion.ads.min_budget_amount', 2);
  }

  public static function symbol(?string $currency): string
  {
    $code = self::normalizeCurrency($currency);

    return self::SYMBOLS[$code] ?? $code.' ';
  }

  public static function formatAmount(float $amount, ?string $currency): string
  {
    $code = self::normalizeCurrency($currency);
    $symbol = self::symbol($code);
    $decimals = in_array($code, ['JPY', 'KRW'], true) ? 0 : 2;

    return $symbol.number_format($amount, $decimals);
  }

  public static function isValid(float $amount, ?string $currency): bool
  {
    return $amount >= self::minAmount($currency);
  }

  /**
   * @return list<string>
   */
  public static function supportedCurrencies(): array
  {
    $configured = config('promotion.ads.min_budget_by_currency', []);
    $codes = is_array($configured) ? array_keys($configured) : [];

    return array_values(array_unique(array_merge(
      ['USD', 'NGN', 'EUR', 'GBP', 'CAD', 'AUD'],
      $codes,
    )));
  }
}
