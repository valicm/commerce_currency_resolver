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

Implementing your service can be done with creating EventSubscriber according
to existing ones.
@see \Drupal\commerce_currency_resolver\ExchangeRateDataSourceInterface
@see \Drupal\commerce_currency_resolver\EventSubscriber\ExchangeRateFixer


MAINTAINERS
-----------

The 8.x-1.x branch was created by:

 * Valentino Medimorec (valic) - https://www.drupal.org/u/valic

This module was created and sponsored by Foreo,
Swedish multi-national beauty brand.

 * Foreo - https://www.foreo.com/
