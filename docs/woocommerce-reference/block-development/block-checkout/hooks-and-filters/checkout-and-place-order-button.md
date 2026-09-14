# Checkout and place order button

The following Checkout and place order button filters are available:

- `proceedToCheckoutButtonLabel`
- `proceedToCheckoutButtonLink`
- `placeOrderButtonLabel`

The following objects are shared between the filters:

- Cart object
- Cart Item object

## `proceedToCheckoutButtonLabel`

### Description

The `proceedToCheckoutButtonLabel` filter allows change the label of the "Proceed to checkout"
button.

### Parameters

- *defaultValue* `string` (default: `Proceed to Checkout`) - The label of the "Proceed to checkout"
  button.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).

### Returns

- `string` - The label of the "Proceed to checkout" button.

### Code examples

#### Basic example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyProceedToCheckoutButtonLabel = (defaultValue, extensions, args) => {
  if (!args?.cart.items) {
    return defaultValue;
  }
  return 'Go to checkout';
};
registerCheckoutFilters('example-extension', {proceedToCheckoutButtonLabel: modifyProceedToCheckoutButtonLabel,});
```

#### Advanced example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyProceedToCheckoutButtonLabel = (defaultValue, extensions, args) => {
  if (!args?.cart.items) {
    return defaultValue;
  }
  const isSunglassesInCart = args?.cart.items.some((item) => item.name === 'Sunglasses');
  if (isSunglassesInCart) {
    return '😎 Proceed to checkout 😎';
  }
  return defaultValue;
};
registerCheckoutFilters('example-extension', {proceedToCheckoutButtonLabel: modifyProceedToCheckoutButtonLabel,});
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Proceed To Checkout Button Label filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/fb0216c1-a091-4d58-b443-f49ccff98ed8)

![After applying the Item Name filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/ef15b6df-fbd7-43e7-a359-b4adfbba961a)

## `proceedToCheckoutButtonLink`

### Description

The `proceedToCheckoutButtonLink` filter allows change the link of the "Proceed to checkout" button.

### Parameters

- *defaultValue* `string` (default: `/checkout`) - The link of the "Proceed to checkout" button.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`,
  see [Cart object](#cart-object).

### Returns

- `string` - The link of the "Proceed to checkout" button.

### Code examples

#### Basic example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyProceedToCheckoutButtonLink = (defaultValue, extensions, args) => {
  if (!args?.cart.items) {
    return defaultValue;
  }
  return '/custom-checkout';
};
registerCheckoutFilters('example-extension', {proceedToCheckoutButtonLink: modifyProceedToCheckoutButtonLink,});
```

#### Advanced example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyProceedToCheckoutButtonLink = (defaultValue, extensions, args) => {
  if (!args?.cart.items) {
    return defaultValue;
  }
  const isSunglassesInCart = args?.cart.items.some((item) => item.name === 'Sunglasses');
  if (isSunglassesInCart) {
    return '/custom-checkout';
  }
  return defaultValue;
};
registerCheckoutFilters('example-extension', {proceedToCheckoutButtonLink: modifyProceedToCheckoutButtonLink,});
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Proceed To Checkout Button Link filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/3f657e0f-4fcc-4746-a554-64221e071b2e)

![After applying the Proceed To Checkout Button Link filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/064df213-439e-4d8f-b29c-55962604cb97)

## `placeOrderButtonLabel`

### Description

The `placeOrderButtonLabel` filter allows change the label of the "Place order" button.

### Parameters

- *defaultValue* (type: `string`, default: `Place order`) - The label of the "Place order" button.
- *extensions* `object` (default: `{}`) - The extensions object.

### Returns

- `string` - The label of the "Place order" button.

### Code example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyPlaceOrderButtonLabel = (defaultValue, extensions) => {
  return '😎 Pay now 😎';
};
registerCheckoutFilters('example-extension', {placeOrderButtonLabel: modifyPlaceOrderButtonLabel,});
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Place Order Button Label filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/aa6d9b65-4d56-45f7-8162-a6bbfe171250)

![After applying the Place Order Button Label filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/a5cc2572-16e7-4781-a5ab-5d6cdced2ff6)

## Cart object

The Cart object of the filters above has the following keys:

- *billingAddress* `object` - The billing address object with the following keys:
- *address\_1* `string` - The first line of the address.
- *address\_2* `string` - The second line of the address.
- *city* `string` - The city of the address.
- *company* `string` - The company of the address.
- *country* `string` - The country of the address.
- *email* `string` - The email of the address.
- *first\_name* `string` - The first name of the address.
- *last\_name* `string` - The last name of the address.
- *phone* `string` - The phone of the address.
- *postcode* `string` - The postcode of the address.
- *state* `string` - The state of the address.
- *coupons* `array` - The coupons array.
- *crossSells* `array` - The cross sell items array.
- *errors* `array` - The errors array.
- *extensions* `object` (default: `{}`) - The extensions object.
- *fees* `array` - The fees array.
- *hasCalculatedShipping* `boolean` - Whether the cart has calculated shipping.
- *items* `array` - The cart items array with cart item objects,
  see [Cart Item object](#cart-item-object).
- *itemsCount* `number` - The number of items in the cart.
- *itemsWeight* `number` - The total weight of the cart items.
- *needsPayment* `boolean` - Whether the cart needs payment.
- *needsShipping* `boolean` - Whether the cart needs shipping.
- *paymentMethods* `array` - The payment methods array.
- *paymentRequirements* `array` - The payment requirements array.
- *shippingAddress* `object` - The shipping address object with the same keys as the billing address
  object.
- *shippingRates* `array` - The shipping rates array.
- *totals* `object` - The totals object with the following keys:
- *currency\_code* `string` - The currency code.
- *currency\_decimal\_separator* `string` - The currency decimal separator.
- *currency\_minor\_unit* `number` - The currency minor unit.
- *currency\_prefix* `string` - The currency prefix.
- *currency\_suffix* `string` - The currency suffix.
- *currency\_symbol* `string` - The currency symbol.
- *currency\_thousand\_separator* `string` - The currency thousand separator.
- *tax\_lines* `array` - The tax lines array of objects with the following keys:
  -   *name* `string` - The tax name.
  -   *price* `string` - The tax price.
  -   *rate* `string` - The tax rate.
- *total\_discount* `string` - The total discount.
- *total\_discount\_tax* `string` - The total discount tax.
- *total\_fee* `string` - The total fee.
- *total\_fee\_tax* `string` - The total fee tax.
- *total\_items* `string` - The total items.
- *total\_items\_tax* `string` - The total items tax.
- *total\_price* `string` - The total price.
- *total\_shipping* `string` - The total shipping.
- *total\_shipping\_tax* `string` - The total shipping tax.
- *total\_tax* `string` - The total tax.

## Cart Item object

The Cart Item object of the filters above has the following keys:

- *backorders\_allowed* `boolean` - Whether backorders are allowed.
- *catalog\_visibility* `string` - The catalog visibility.
- *description* `string` - The cart item description.
- *extensions* `object` (default: `{}`) - The extensions object.
- *id* `number` - The item ID.
- *images* `array` - The item images array.
- *item\_data* `array` - The item data array.
- *key* `string` - The item key.
- *low\_stock\_remaining* `number` - The low stock remaining.
- *name* `string` - The item name.
- *permalink* `string` - The item permalink.
- *prices* `object` - The item prices object with the following keys:
- *currency\_code* `string` - The currency code.
- *currency\_decimal\_separator* `string` - The currency decimal separator.
- *currency\_minor\_unit* `number` - The currency minor unit.
- *currency\_prefix* `string` - The currency prefix.
- *currency\_suffix* `string` - The currency suffix.
- *currency\_symbol* `string` - The currency symbol.
- *currency\_thousand\_separator* `string` - The currency thousand separator.
- *price* `string` - The price.
- *price\_range* `string` - The price range.
- *raw\_prices* `object` - The raw prices object with the following keys:
  -   *precision* `number` - The precision.
  -   *price* `number` - The price.
  -   *regular\_price* `number` - The regular price.
  -   *sale\_price* `number` - The sale price.
- *regular\_price* `string` - The regular price.
- *sale\_price* `string` - The sale price.
- *quantity* `number` - The item quantity.
- *quantity\_limits* `object` - The item quantity limits object with the following keys:
- *editable* `boolean` - Whether the quantity is editable.
- *maximum* `number` - The maximum quantity.
- *minimum* `number` - The minimum quantity.
- *multiple\_of* `number` - The multiple of quantity.
- *short\_description* `string` - The item short description.
- *show\_backorder\_badge* `boolean` - Whether to show the backorder badge.
- *sku* `string` - The item SKU.
- *sold\_individually* `boolean` - Whether the item is sold individually.
- *totals* `object` - The item totals object with the following keys:
- *currency\_code* `string` - The currency code.
- *currency\_decimal\_separator* `string` - The currency decimal separator.
- *currency\_minor\_unit* `number` - The currency minor unit.
- *currency\_prefix* `string` - The currency prefix.
- *currency\_suffix* `string` - The currency suffix.
- *currency\_symbol* `string` - The currency symbol.
- *currency\_thousand\_separator* `string` - The currency thousand separator.
- *line\_subtotal* `string` - The line subtotal.
- *line\_subtotal\_tax* `string` - The line subtotal tax.
- *line\_total* `string` - The line total.
- *line\_total\_tax* `string` - The line total tax.
- *type* `string` - The item type.
- *variation* `array` - The item variation array.
