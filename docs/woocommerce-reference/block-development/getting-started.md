# Extensibility in blocks

These documents are all dealing with extensibility in the various WooCommerce Blocks.

## Imports and dependency extraction

The documentation in this section will use window globals in code examples, for example:

```js
const { registerCheckoutFilters } = window.wc.blocksCheckout;
```

However, if you're using `@woocommerce/dependency-extraction-webpack-plugin` for enhanced dependency
management you can instead use ES module syntax:

```js
import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';
```

See [@woocommerce/dependency-extraction-webpack-plugin](https://www.npmjs.com/package/@woocommerce/dependency-extraction-webpack-plugin)
for more information.

## Hooks (actions and filters)

Document

Description

[Actions](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/hooks/actions.md)

Documentation covering action hooks on the server side.

[Filters](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/hooks/filters.md)

Documentation covering filter hooks on the server side.

## REST API

Document

Description

[Exposing your data in the Store API.](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-add-data.md)

Explains how you can add additional data to Store API endpoints.

[Available endpoints to extend with ExtendSchema](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/rest-api/available-endpoints-to-extend.md)

A list of all available endpoints to extend.

[Available Formatters](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-formatters.md)

Available `Formatters` to format data for use in the Store API.

[Updating the cart with the Store API](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-update-cart.md)

Update the server-side cart following an action from the front-end.

## Checkout Payment Methods

Document

Description

[Checkout Flow and Events](block-checkout/payment-methods/checkout-flow-and-events.md)

All about the checkout flow in the checkout block and the various emitted events that can be
subscribed to.

[Payment Method Integration](block-checkout/payment-methods/payment-method-integration.md)

Information about implementing payment methods.

[Filtering Payment Methods](block-checkout/payment-methods/filtering-payment-methods.md)

Information about filtering the payment methods available in the Checkout Block.

## Checkout Block

In addition to the reference material below, [please see the
`block-checkout` package documentation](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/README.md)
which is used to extend checkout with Filters, Slot Fills, and Inner Blocks.

Document

Description

[How the Checkout Block processes an order](block-checkout/processing-an-order.md)

The detailed inner workings of the Checkout Flow.

[Available Filters](block-checkout/hooks-and-filters/index.md)

All about the filters that you may use to change values of certain elements of WooCommerce Blocks.

[Available Slot Fills](block-checkout/available-slots.md)

Available Slots that you can use and their positions in Cart and Checkout.

[DOM Events](block-checkout/dom-events.md)

A list of DOM Events used by some blocks to communicate between them and with other parts of
WooCommerce.

[Filter Registry](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/filter-registry/README.md)

The filter registry allows callbacks to be registered to manipulate certain values.

[Additional Checkout Fields](block-checkout/additional-checkout-fields.md)

The filter registry allows callbacks to be registered to manipulate certain values.
