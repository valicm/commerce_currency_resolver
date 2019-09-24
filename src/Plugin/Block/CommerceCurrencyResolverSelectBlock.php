<?php

namespace Drupal\commerce_currency_resolver\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\commerce_currency_resolver\Form\CommerceCurrencyResolverSelectForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Commerce currency block.
 *
 * @Block(
 *   id = "commerce_currency_resolver",
 *   admin_label = @Translation("Currency block"),
 *   category = @Translation("Blocks")
 * )
 */
class CommerceCurrencyResolverSelectBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * FormBuilderInterface.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $markup['form'] = $this->formBuilder->getForm(CommerceCurrencyResolverSelectForm::class);
    return $markup;
  }

}
