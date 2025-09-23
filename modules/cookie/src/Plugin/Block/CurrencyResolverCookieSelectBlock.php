<?php

namespace Drupal\commerce_currency_resolver_cookie\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_currency_resolver_cookie\Form\CurrencyResolverCookieSelectForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: "commerce_currency_resolver_cookie",
  admin_label: new TranslatableMarkup('Currency cookie block selector'),
  category: new TranslatableMarkup('Commerce'),
)]
class CurrencyResolverCookieSelectBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
  public function build(): array {
    $markup['form'] = $this->formBuilder->getForm(CurrencyResolverCookieSelectForm::class);
    return $markup;
  }

}
