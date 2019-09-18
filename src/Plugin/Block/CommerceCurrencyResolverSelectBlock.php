<?php

namespace Drupal\commerce_currency_resolver\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\commerce_currency_resolver\Form\CommerceCurrencyResolverSelectForm;

/**
 * Provides Commerce currency block.
 *
 * @Block(
 *   id = "commerce_currency_resolver",
 *   admin_label = @Translation("Currency block"),
 *   category = @Translation("Blocks")
 * )
 */
class CommerceCurrencyResolverSelectBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $markup = [];

    $form = \Drupal::formBuilder()->getForm(CommerceCurrencyResolverSelectForm::class);

    $markup['form'] = $form;

    return $markup;
  }

}
