<?php

namespace Drupal\commerce_currency_resolver_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_currency_resolver_exchanger\Plugin\Commerce\ExchangerConditionTrait;
use Drupal\commerce_exchanger\ExchangerCalculatorInterface;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CurrencyResolverShippingTrait {

  use ExchangerConditionTrait;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, protected CurrencyResolverManagerInterface $currencyResolverManager, protected CurrentCurrencyInterface $currentCurrency, protected ExchangerCalculatorInterface $priceExchanger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
      $container->get('commerce_currency_resolver.manager'),
      $container->get('commerce_price.current_currency'),
      $container->get('commerce_currency_resolver_exchanger.calculator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    if ($order = $shipment->getOrder()) {
      $amount = $this->getRatesAmount($order->getTotalPrice()->getCurrencyCode());
      $rates = [];
      $price = Price::fromArray($amount);
      $rates[] = new ShippingRate([
        'shipping_method_id' => $this->parentEntity->id(),
        'service' => $this->services['default'],
        // Account for two different types of plugins.
        'amount' => $this->getPluginId() === 'flat_rate_per_item' ? $price->multiply($shipment->getTotalQuantity()) : $price,
        'description' => $this->configuration['rate_description'],
      ]);

      return $rates;

    }
    return parent::calculateRates($shipment);
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    parent::selectRate($shipment, $rate);
    if ($order = $shipment->getOrder()) {
      $order_currency = $order->getTotalPrice()->getCurrencyCode();
      if ($order_currency && $rate->getAmount()->getCurrencyCode() !== $order_currency) {
        $shipment->setOriginalAmount($this->priceExchanger->priceConversion($rate->getOriginalAmount(), $order_currency));
        $shipment->setAmount($this->priceExchanger->priceConversion($rate->getAmount(), $order_currency));
      }
    }
  }

  /**
   * Overrides commerce core and fee condition protected function.
   */
  protected function getRatesAmount(?string $target_currency): array {
    $amount = $this->configuration['rate_amount'];
    $price = new Price($amount['number'], $amount['currency_code']);
    if (!$target_currency) {
      $target_currency = $this->currentCurrency->getCurrency()->getCurrencyCode();
    }
    if ($target_currency !== $amount['currency_code']) {
      // If we have specified price listed.
      if (isset($this->configuration['fields'][$target_currency])) {
        $priceField = $this->configuration['fields'][$target_currency];

        // Added check if prices is empty
        // (etc. after migration of old discounts).
        if (!empty($priceField['number'])) {
          return (new Price($priceField['number'], $priceField['currency_code']))->toArray();
        }
      }

      // Auto-calculate if we don't have any price in the currency field.
      return $this->priceExchanger->priceConversion($price, $target_currency)->toArray();
    }

    return $amount;
  }

}
