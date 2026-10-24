<?php

namespace Drupal\commerce_currency_resolver_cookie\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for commerce_currency_resolver_cookie.
 */
class CookieHooks {

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, $view_mode): void {
    // Add our cache context.
    if (isset($build['#commerce_product']) || isset($build['#commerce_product_variation'])) {
      $build['#cache']['contexts'][] = 'currency_resolver';
    }
  }

}
