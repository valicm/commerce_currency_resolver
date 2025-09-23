CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Submodules
* Configuration
* Price setup
* Setup examples
* Exchange rates
* Maintainers


INTRODUCTION
------------

Enhancement for handling multi-currency in Drupal 8 for Drupal Commerce.

Drupal Commerce 3 supports multiple currencies out of the box.

Commerce currency resolver tries to solve resolving prices per currency,
calculating those prices and exchange rates between different currencies.


REQUIREMENTS
------------

This module requires Drupal Commerce 3, and it's submodule price.
The additionally submodules have different requirements.


INSTALLATION
------------

Install the Commerce Currency Resolver module as you would normally install
any Drupal contrib module.
Visit https://www.drupal.org/node/1897420 for further information.


SUBMODULES
------------

The module comes with six different submodules.

  | Name      | Description                                                                                             |
  |-----------|---------------------------------------------------------------------------------------------------------|
  | language  | Resolving currency per user language, and UI to map languages to specify currency.                      |
  | geoip     | Resolving currency per use geo country via GeoIP module, and UI to map language to specify currency.    |
  | smart_ip  | Resolving currency per use geo country via Smart IP module, and UI to map language to specify currency. |
  | cookie    | Resolving currency via cookie, providing a block for selecting desired currency.                        |
  | exchanger | Integration with Commerce Exchanger, adds feature to auto-calculate all prices based of exchange rate   |
  | shipping  | Integration with Commerce Shipping module for use with Commerce exchanger                               |

CONFIGURATION
--------------

    1. Navigate to Administration > Extend and enable the Commerce Currency
       Resolver module.
    2. Navigate to Home > Administration > Commerce > Configuration
                   > Currency resolver.
    3. Enable one of the provided submodules if needed.

The main module by default follows Commerce Core 3 logic where currency is resolved via
current resolved Store.

The submodules listed above provide different options to resolve price and calculate or convert prices
more dynamically for products, orders, promotions, fees, taxes, custom adjustments.

PRICE SETUP
--------------
    1. Price per currency - you can specify now in module settings field prefixes for each currency field
    2. Combo mode - once the exchanger module is enable, this is default way of working.
    3. Automatic - once the exchanger module is enabled you can do everything automatic.

Currently, the module does not support price list module.

SETUP EXAMPLES
--------------
Here are few examples of possible configuration and examples.
If you are resolving currencies per current store, and you did set up everything per currency,
you really don't need than this module.

| Resolve type | Handling currency                                                           | Do I need this module?            |
|--------------|-----------------------------------------------------------------------------|-----------------------------------|
| store        | Products have specific prices. Promotion and shipments are set per currency | NO                                |
| language     | Same as previous example.                                                   | Language submodule                |
| language     | Same as previous example plus custom adjustments                            | Language and exchanger submodule  |
| any          | Want to not care about specific setup of promotions, fees, shipping methods | Exchanger submodule for auto mode |
| any          | Using commerce shipping and run automatic conversion                        | Shipping and exchanger submodule  |


CACHING
--------------
Currency resolver module works with "**Internal Dynamic Page Cache**" only.

**Page Cache** module needs to be disabled.

The Drupal core Page Cache module does not work with personalized "content".
Dynamic Page Cache is built for that purpose.

Explanation - https://www.drupal.org/project/commerce_currency_resolver/issues/3042307#comment-13046326


EXCHANGE RATES
--------------

Handle through Commerce Exchanger module
https://www.drupal.org/project/commerce_exchanger

COMMERCE SHIPPING
--------------

If you are using commerce shipping, there are two options for how shipping methods
can be set to work with currency resolver.

* If you are using condition _Order currency_ and the selected currency matches to the price currency selected
  under _Rate amount_ then you don't need to do anything.
* If you need to auto-calculate price or have multiple prices per shipping method,
* you need to enable submodule _commerce_currency_resolver_shipping_.


EXAMPLES
-----------

### Adding order item programmatically
If you are adding order items programmatically in your code,
you need to take in an account possible conflicts with prices. To avoid that
is best that you're using resolver to resolve prices for certain items in the cart.

The example below shows an entire process custom "add to cart" process, where we add
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
Swedish multinational beauty brand.

 * Foreo - https://www.foreo.com/
