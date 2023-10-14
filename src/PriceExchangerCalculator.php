<?php

namespace Drupal\commerce_currency_resolver;

use Drupal\commerce_exchanger\AbstractExchangerCalculator;
use Drupal\commerce_exchanger\ExchangerManagerInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Default exchange calculator for resolver.
 */
class PriceExchangerCalculator extends AbstractExchangerCalculator {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExchangerManagerInterface $exchanger_manager, RounderInterface $rounder, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type_manager, $exchanger_manager, $rounder);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getExchangerId() {
    $resolver_exchanger_id = $this->configFactory->get('commerce_currency_resolver.settings')->get('currency_exchange_rates');
    if (isset($this->providers[$resolver_exchanger_id]) && $this->providers[$resolver_exchanger_id]->status()) {
      return $this->providers[$resolver_exchanger_id]->id();
    }

    return NULL;
  }

}
