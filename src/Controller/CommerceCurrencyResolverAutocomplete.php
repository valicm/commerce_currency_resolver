<?php

namespace Drupal\commerce_currency_resolver\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Locale\CountryManagerInterface;

/**
 * Class CommerceCurrencyResolverAutocomplete.
 *
 * @package Drupal\commerce_currency_resolver\Controller
 */
class CommerceCurrencyResolverAutocomplete extends ControllerBase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Constructs a CommerceCurrencyResolverAutocomplete object.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct(CountryManagerInterface $country_manager) {
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager')
    );
  }

  /**
   * Retrieves group suggestions for a context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with groups matching the query.
   */
  public function countriesAutocomplete(Request $request) {
    $query = $request->query->get('q');

    $matches = [];

    foreach ($this->countryManager->getList() as $key => $value) {
      if (stripos($value, $query) === 0) {
        $matches[$key] = $value;
      }

      if (stripos($key, $query) === 0) {
        $matches[$key] = $value;
      }
    }

    $response = [];

    // Format the unique matches to be used with the autocomplete field.
    foreach (array_unique($matches) as $key => $match) {
      $response[] = [
        'value' => $key,
        'label' => Html::escape($match),
      ];
    }

    return new JsonResponse($response);
  }

}
