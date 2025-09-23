<?php

namespace Drupal\commerce_currency_resolver_smart_ip\Resolver;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\smart_ip\SmartIpLocation;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns the currency by user country.
 */
class CurrencyResolverSmartIp implements CurrencyResolverInterface {

  /**
   * Constructs a new CurrencyResolverSmartIp object.
   */
  public function __construct(protected RequestStack $requestStack, protected SmartIpLocation $geoLocation, protected ConfigFactoryInterface $configFactory, protected CurrencyResolverManagerInterface $currencyResolverManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    $user_ip = $this->requestStack->getCurrentRequest()?->getClientIp();
    if ($user_ip) {
      $country = $this->geoLocation->get('countryCode');
      $matrix = $this->configFactory->get('commerce_currency_resolver_smart_ip.currency_mapping')->get('matrix');

      if (isset($matrix[$country])) {
        return $this->currencyResolverManager->getCurrencyByCode($matrix[$country]);
      }
    }

    return NULL;
  }

}
