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

Enhancement for handling multicurrency in Drupal 8 for Drupal Commerce.

Drupal Commerce 2 supports multiple currencies out of the box.
But only for adding prices, not resolving multiple currency prices/orders
based on some criteria.

Commerce currency resolver tries to solve resolving prices per currency,
calculating those prices and exchange rates between currencies.


REQUIREMENTS
------------

This module requires Drupal Commerce 2 and it's submodule price.


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


EXCHANGE RATES
--------------

If you want to implement 3rd party service by your choice for use in currency
exchange rate you can do it with few lines of the code.
Each Exchange rate service is Event Subscriber.

First in .services.yml file inside your custom module add definition
for your Event Subscriber:

your_custom_module_name.exchange_rate_YOURSERVICE:
  class: Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateName
  tags:
    - { name: event_subscriber }

Important is that you prefix your event subscriber with exhange_rate_,
so that is automatically added on admin settings page.
@see \Drupal\commerce_currency_resolver\CurrencyHelper::getExchangeServices

You need to declare following functions:
apiUrl() - external API URL
sourceId()
getExternalData() - fetching external data
processCurrencies() - function which is triggered for importing.

See below difference between usage with one currency or multiple on import
and cross sync setting according to this.

See also note about cross sync:
https://www.drupal.org/project/commerce_currency_resolver/issues/2984828

Implementing your service can be done with creating EventSubscriber according
to existing ones.
@see \Drupal\commerce_currency_resolver\ExchangeRateDataSourceInterface
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateFixer
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateFixerPaid

All calculation is done with function processCurrencies() from which
you call existing crossSyncCalculate() or implement your own.

To use crossSyncCalculate data, you need to send array as
$data = ['EUR' => '1.05', 'USD' => '1.5']
and your base currency as string (etc.USD).

If you have only one currency source, you can make easy implementation
regardless of cross sync settings while it's same then
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateFixer
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateECB

If you have source for each currency, you can switch between cross sync
settings and implement your own per currency data.
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateFixerPaid


MAINTAINERS
-----------

The 8.x-1.x branch was created by:

 * Valentino Medimorec (valic) - https://www.drupal.org/u/valic

This module was created and sponsored by Foreo,
Swedish multi-national beauty brand.

 * Foreo - https://www.foreo.com/

Codacy Badge
