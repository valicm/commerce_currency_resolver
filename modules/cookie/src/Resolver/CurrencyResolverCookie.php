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
    if ($request?->cookies->has($cookie_name)) {
      $cookie = $request->cookies->get($cookie_name);
      return $this->currencyResolverManager->getCurrencyByCode($cookie);
    }

    return NULL;
  }

}
