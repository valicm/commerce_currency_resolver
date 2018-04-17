<?php

namespace Drupal\commerce_currency_resolver\Event;

/**
 * Contains all events for commerce_currency_resolver.
 *
 * @package Drupal\commerce_currency_resolver
 */
final class CommerceCurrencyResolverEvents {

  /**
   * Importing currency exchange rates.
   *
   * @Event
   *
   * @var string
   */
  const IMPORT = 'commerce_currency_resolver.exchange_import';

}
