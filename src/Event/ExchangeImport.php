<?php

namespace Drupal\commerce_currency_resolver\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class ExchangeImport.
 *
 * @package Drupal\commerce_currency_resolver\Event
 */
class ExchangeImport extends Event {

  /**
   * {@inheritdoc}
   */
  protected $service;

  /**
   * Constructs a new object.
   */
  public function __construct($service) {
    $this->service = $service;
  }

  /**
   * Get machine name of the service.
   *
   * @return mixed
   *   Return string.
   */
  public function getLabel() {
    return $this->service;
  }

}
