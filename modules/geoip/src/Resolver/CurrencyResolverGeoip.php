<?php

namespace Drupal\commerce_currency_resolver_geoip\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\geoip\GeoLocation;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns the currency by user country.
 */
class CurrencyResolverGeoip implements CurrencyResolverInterface {

  /**
   * Constructs a new CurrencyResolverGeoip object.
   */
  public function __construct(protected RequestStack $requestStack, protected GeoLocation $geoLocation, protected ConfigFactoryInterface $configFactory, protected CurrencyResolverManagerInterface $currencyResolverManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    $user_ip = $this->requestStack->getCurrentRequest()?->getClientIp();
    if ($user_ip) {
      $country = $this->geoLocation->geolocate($user_ip);
      $matrix = $this->configFactory->get('commerce_currency_resolver_language.currency_mapping')->get('matrix');

      if (isset($matrix[$country])) {
        return $this->currencyResolverManager->getCurrencyByCode($matrix[$country]);
      }
    }

    return NULL;
  }

}
