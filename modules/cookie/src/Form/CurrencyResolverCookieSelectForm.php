<?php

namespace Drupal\commerce_currency_resolver_cookie\Form;

use Drupal\commerce_currency_resolver\CurrencyResolverManagerInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Select form.
 */
class CurrencyResolverCookieSelectForm extends FormBase {

  public function __construct(protected $requestStack, protected TimeInterface $time, protected CurrentStoreInterface $currentStore, protected CurrencyResolverManagerInterface $currencyResolverManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_currency_resolver.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_currency_resolver_cookie_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->requestStack->getCurrentRequest();

    // Get all active currencies.
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $active_currencies */
    $active_currencies = $this->currencyResolverManager->getCurrencies();

    // Get cookies.
    $cookie_name = $this->currencyResolverManager->getCookieName();
    $cookie = $request->cookies->get($cookie_name);
    $selected_currency = $this->currentStore->getStore()->getDefaultCurrency()->getCurrencyCode();

    $options = [];
    foreach ($active_currencies as $currency) {
      $options[$currency->getCurrencyCode()] = $currency->label();
    }

    if ($cookie && isset($options[$cookie_name])) {
      $selected_currency = $options[$cookie_name];
    }

    $form['currency'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $selected_currency,
      '#attributes' => [
        'onChange' => ['this.form.submit()'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Add currency cache context.
    $form['#cache']['contexts'][] = 'currency_resolver';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get form value.
    $selected_currency = $form_state->getValue('currency');

    // Set cookie for one day.
    setrawcookie($this->currencyResolverManager->getCookieName(), rawurlencode($selected_currency), $this->time->getRequestTime() + 86400, '/');
  }

}
