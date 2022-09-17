CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Exchange rates
* Maintainers


INTRODUCTION
------------

Enhancement for handling multi-currency in Drupal 8 for Drupal Commerce.

Drupal Commerce 2 supports multiple currencies out of the box.
But only for adding prices, not resolving multiple currency prices/orders
based on some criteria.

Commerce currency resolver tries to solve resolving prices per currency,
calculating those prices and exchange rates between currencies.


REQUIREMENTS
------------

This module requires Commerce Exchanger, Drupal Commerce 2
and it's submodule price.


INSTALLATION
------------

Install the Commerce Currency Resolver module as you would normally install
any Drupal contrib module.
Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
--------------

    1. Navigate to Administration > Extend and enable the Commerce Currency
       Resolver module.
    2. Navigate to Home > Administration > Commerce > Configuration
                   > Currency resolver.
    3. Choose from available options settings to configure how currency should
       be resolved, how prices are calculated and default currency.
    4. Navigate to the "Conversion" tab for configuration related
       to Exchange rates.
    5. Navigate to the "Mapping" if available and selected which currency
       should be used per language or country.

CACHING
--------------
Currency resolver module works with "**Internal Dynamic Page Cache**" only.

**Page Cache** module needs to be disabled.

The Drupal core Page Cache module does not work with personalized "content".
Dynamic Page Cache is built for that purpose.

Explanation - https://www.drupal.org/project/commerce_currency_resolver/issues/3042307#comment-13046326


EXCHANGE RATES
--------------

Handled trough Commerce Exchanger module
https://www.drupal.org/project/commerce_exchanger

COMMERCE SHIPPING
--------------

If you are using commerce shipping there are two options how shipping methods
can be set to work with currency resolver.

* If you are using condition _Order currency_ and the selected currency matches to the price currency selected
  under _Rate amount_ then you don't need to do anything.
* If you need to auto-calculate price or have multiple prices per shipping method
  you need enable submodule _commerce_currency_resolver_shipping_.


EXAMPLES
-----------

### Adding order item programmatically
If you are adding order items programmatically in your code,
you need take in account possible conflicts with prices. To avoid that
is best that you using resolver to resolve prices for certain item to the cart.

Example below shows entire process in custom add to cart process, where we add
item to the cart.

```
**
@var \Drupal\commerce_cart\CartManagerInterface $cart_manager */
$cart_manager = \Drupal::service('commerce_cart.cart_manager');

/** @var \Drupal\commerce_order\Resolver\OrderTypeResolverInterface $order_type_resolver */
$order_type_resolver = \Drupal::service('commerce_order.chain_order_type_resolver');

/** @var \Drupal\commerce_store\CurrentStoreInterface $current_store */
$current_store = \Drupal::service('commerce_store.current_store');

/** @var \Drupal\commerce_cart\CartProviderInterface $cart_provider */
$cart_provider = \Drupal::service('commerce_cart.cart_provider');

/** @var \Drupal\commerce_order\OrderItemStorage $order_item_storage */
$order_item_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_order_item');
$store = $current_store->getStore();

$context = $context = new Context(\Drupal::currentUser(), $store);
$resolved_price = \Drupal::service('commerce_currency_resolver.price_resolver')->resolve($product, 1, $context);
$order_item = $order_item_storage->createFromPurchasableEntity($product, ['unit_price' => $resolved_price]);

$order_type_id = $order_type_resolver->resolve($order_item);

$cart = $cart_provider->getCart($order_type_id, $store);
if (!$cart) {
  $cart = $cart_provider->createCart($order_type_id, $store);
}

$cart_manager->addOrderItem($cart, $order_item);
```

MAINTAINERS
-----------

The 8.x-1.x branch was created by:

 * Valentino Medimorec (valic) - https://www.drupal.org/u/valic

This module was created and sponsored by Foreo,
Swedish multi-national beauty brand.

 * Foreo - https://www.foreo.com/
