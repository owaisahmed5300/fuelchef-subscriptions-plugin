# Totals footer item

The following Totals Footer Item filter are available:

- `totalLabel`
- `totalValue`

## `totalLabel`

The following object is used in the filter:

- [Cart object](#cart-object)

### Description

The `totalLabel` filter allows to change the label of the total item in the footer of the Cart and
Checkout blocks.

### Parameters

- *defaultValue* `string` (default: `Total`) - The total label.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).

### Returns

- `string` - The updated total label.

### Code example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyTotalLabel = (defaultValue, extensions, args) => {
  return 'Deposit due today';
};
registerCheckoutFilters('example-extension', {totalLabel: modifyTotalLabel,});
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Total Label filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/5b2fb8ab-db84-4ed0-a676-d5203edc84d2)

![After applying the Total Label filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/07955eea-cb17-48e9-9cb5-6548dd6a3b24)

## `totalValue`

The following object is used in the filter:

- [Cart object](#cart-object)

### Description

The `totalValue` filter allows to format the total price in the footer of the Cart and Checkout
blocks.

### Parameters

- *defaultValue* `string` (default: `Total`) - The total label.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the total price, which must contain the substring `<price/>`, or
  the original price format.

### Code example

```ts
const {registerCheckoutFilters} = window.wc.blocksCheckout;
const modifyTotalsPrice = (defaultValue, extensions, args, validation) => {
  return 'Pay <price/> now';
};
registerCheckoutFilters('my-extension', {totalValue: modifyTotalsPrice,});
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Total Value filter](https://github.com/woocommerce/woocommerce/assets/3323310/4b788bdd-6fbd-406c-a9ad-4fb13f901c23)

![After applying the Total Value filter](https://github.com/woocommerce/woocommerce/assets/3323310/1b1b5f72-7f2f-4ee5-b2a4-1d8eb2208deb)

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
- *billingData* `object` - The billing data object with the same keys as the `billingAddress`
  object.
- *cartCoupons* `array` - The cart coupons array.
- *cartErrors* `array` - The cart errors array.
- *cartFees* `array` - The cart fees array.
- *cartHasCalculatedShipping* `boolean` - Whether the cart has calculated shipping.
- *cartIsLoading* `boolean` - Whether the cart is loading.
- *cartItemErrors* `array` - The cart item errors array.
- *cartItems* `array` - The cart items array with cart item objects,
  see [Cart Item object](#cart-item-object).
- *cartItemsCount* `number` - The cart items count.
- *cartItemsWeight* `number` - The cart items weight.
- *cartNeedsPayment* `boolean` - Whether the cart needs payment.
- *cartNeedsShipping* `boolean` - Whether the cart needs shipping.
- *cartTotals* `object` - The cart totals object with the following keys:
- *currency\_code* `string` - The currency code.
- *currency\_decimal\_separator* `string` - The currency decimal separator.
- *currency\_minor\_unit* `number` - The currency minor unit.
- *currency\_prefix* `string` - The currency prefix.
- *currency\_suffix* `string` - The currency suffix.
- *currency\_symbol* `string` - The currency symbol.
- *currency\_thousand\_separator* `string` - The currency thousand separator.
- *tax\_lines* `array` - The tax lines array with tax line objects with the following keys:
  -   *name* `string` - The name of the tax line.
  -   *price* `number` - The price of the tax line.
  -   *rate* `string` - The rate ID of the tax line.
- *total\_discount* `string` - The total discount.
- *total\_discount\_tax* `string` - The total discount tax.
- *total\_fees* `string` - The total fees.
- *total\_fees\_tax* `string` - The total fees tax.
- *total\_items* `string` - The total items.
- *total\_items\_tax* `string` - The total items tax.
- *total\_price* `string` - The total price.
- *total\_shipping* `string` - The total shipping.
- *total\_shipping\_tax* `string` - The total shipping tax.
- *total\_tax* `string` - The total tax.
- *crossSellsProducts* `array` - The cross sells products array with cross sells product objects.
- *extensions* `object` (default: `{}`) - The extensions object.
- *isLoadingRates* `boolean` - Whether the cart is loading shipping rates.
- *paymentRequirements* `array` - The payment requirements array.
- *shippingAddress* `object` - The shipping address object with the same keys as the
  `billingAddress` object.
- *shippingRates* `array` - The shipping rates array.

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
