<?php

namespace Drupal\commerce_currency_resolver_cookie\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns the currency by user cookie.
 */
class CurrencyResolverCookie implements CurrencyResolverInterface {

  /**
   * Constructs a new CurrencyResolverCookie object.
   */
  public function __construct(protected RequestStack $requestStack, protected CurrencyResolverManagerInterface $currencyResolverManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    // Cookie name can be configurable.
    $cookie_name = $this->currencyResolverManager->getCookieName();
    $request = $this->requestStack->getCurrentRequest();
    // Cookie is visible via request as x-commerce-currency,
    // where with $_SERVER is visible as http-x-commerce-currency.
    $header_name = sprintf('X_%s', strtoupper($cookie_name));
    // Reverse proxy case where we have a header based of cookie.
    // Check the header first, and then cookie itself.
    if ($request?->headers->has($header_name)) {
      $currency_code = $request->headers->get($header_name);
      return $this->currencyResolverManager->getCurrencyByCode($currency_code);
    }
    if ($request?->cookies->has($cookie_name)) {
      $currency_code = $request->cookies->get($cookie_name);
      return $this->currencyResolverManager->getCurrencyByCode($currency_code);
    }

    return NULL;
  }

}
