# Order summary items

The following Order Summary Items filters are available:

- `cartItemClass`
- `cartItemPrice`
- `cartItemScreenReaderPrice`
- `itemName`
- `subtotalPriceFormat`

The following objects are shared between the filters:

- Cart object
- Cart Item object

The following screenshot shows which parts the individual filters affect:

![Order Summary Items](https://woocommerce.com/wp-content/uploads/2023/10/Screenshot-2023-10-26-at-16.29.45.png)

## `cartItemClass`

### Description

The `cartItemClass` filter allows to change the order summary item class.

### Parameters

- *defaultValue* `string` (default: `''`) - The default order summary item class.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *cartItem* `object` - The order summary item object from `wc/store/cart`,
  see [order summary item object](#cart-item-object).
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.

### Returns

- `string` - The modified order summary item class, or an empty string.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemClass = ( defaultValue, extensions, args ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	return 'my-custom-class';};registerCheckoutFilters( 'example-extension', {	cartItemClass: modifyCartItemClass,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemClass = ( defaultValue, extensions, args ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return 'cool-class';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return 'hot-class';	}	return 'my-custom-class';};registerCheckoutFilters( 'example-extension', {	cartItemClass: modifyCartItemClass,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Cart Item Class filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/ff555a84-8d07-4889-97e1-8f7d50d47350)

![After applying the Cart Item Class filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/183809d8-03dc-466d-a415-d8d2062d880f)

## `cartItemPrice`

### Description

The `cartItemPrice` filter allows to format the order summary item price.

### Parameters

- *defaultValue* `string` (default: `<price/>`) - The default order summary item price.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *cartItem* `object` - The order summary item object from `wc/store/cart`,
  see [order summary item object](#cart-item-object).
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the order summary item price, which must contain the substring
  `<price/>`, or the original price format.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemPrice = ( defaultValue, extensions, args, validation ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	return '<price/> for all items';};registerCheckoutFilters( 'example-extension', {	cartItemPrice: modifyCartItemPrice,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemPrice = ( defaultValue, extensions, args, validation ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return '<price/> to keep you ☀️';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return '<price/> to keep you ❄️';	}	return '<price/> for all items';};registerCheckoutFilters( 'example-extension', {	cartItemPrice: modifyCartItemPrice,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Cart Item Price filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/58137fc4-884d-4783-9275-5f78abec1473)

![After applying the Cart Item Price filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/fb502b74-6447-49a8-8d35-241e738f089d)

## `cartItemScreenReaderPrice`

### Description

The `cartItemScreenReaderPrice` filter allows for formatting the order summary item price announced
to screen reader and assistive technology users. There are no visual changes on the screen. The code
changes can be seen in the `<span class="screen-reader-text">` included for each item in the cart.

### Parameters

- *defaultValue* `string` (default: `Total price for <quantity/> <productName/> item: <price/>` for
  purchases of a single item; `Total price for <quantity/> <productName/> items: <price/>` for
  purchases of multiple items) - The default order summary screen reader text.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *cartItem* `object` - The order summary item object from `wc/store/cart`,
  see [order summary item object](#cart-item-object).
- *context* `string` (`summary`) - The context of the item, fixed to match the context of other
  filters in the mini-cart summary.
- *validation* `boolean` - Checks if the return value contains the substrings `<quantity/>`,
  `<productName/>` and `<price/>`.

### Returns

- `string` - The modified format of the order summary item price, which must contain the substrings
  `<quantity/>`, `<productName/>` and `<price/>`.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const { _n } = window.wp.i18n;const modifyCartItemScreenReaderPrice = ( defaultValue, extensions, args, validation ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	return _n(		'<quantity/> <productName/> item will cost <price/>',		'<quantity/> <productName/> items will cost <price/>',		args?.cartItem?.quantity ?? 1,		'example-extension'	);};registerCheckoutFilters( 'example-extension', {	cartItemScreenReaderPrice: modifyCartItemScreenReaderPrice,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const { _n } = window.wp.i18n;const modifyCartItemScreenReaderPrice = ( defaultValue, extensions, args, validation ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return _n(			'Total price for <quantity/> <productName/> item: <price/> to keep you warm',			'Total price for <quantity/> <productName/> items: <price/> to keep you warm',			args?.cartItem?.quantity ?? 1,			'example-extension'		);	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return _n(			'Total price for <quantity/> <productName/> item: <price/> to keep you cool',			'Total price for <quantity/> <productName/> items: <price/> to keep you cool',			args?.cartItem?.quantity ?? 1,			'example-extension'		);	}	return defaultValue;};registerCheckoutFilters( 'example-extension', {	cartItemScreenReaderPrice: modifyCartItemScreenReaderPrice,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

## `itemName`

### Description

The `itemName` filter allows to change the order summary item name.

### Parameters

- *defaultValue* `string` - The default order summary item name.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *cartItem* `object` - The order summary item object from `wc/store/cart`,
  see [order summary item object](#cart-item-object).
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.

### Returns

- `string` - The original or modified order summary item name.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyItemName = ( defaultValue, extensions, args ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	return `🪴 ${ defaultValue } 🪴`;};registerCheckoutFilters( 'example-extension', {	itemName: modifyItemName,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyItemName = ( defaultValue, extensions, args ) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return `⛷️ ${ defaultValue } ⛷️`;	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return `🏄‍♂️ ${ defaultValue } 🏄‍♂️`;	}	return `🪴 ${ defaultValue } 🪴`;};registerCheckoutFilters( 'example-extension', {	itemName: modifyItemName,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Item Name filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/3dc0bda7-fccf-4f35-a2e2-aa04e616563a)

![After applying the Item Name filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/c96b8394-03a7-45f6-813b-5335f4bf83b5)

## `subtotalPriceFormat`

### Description

The `subtotalPriceFormat` filter allows to format the order summary item subtotal price.

### Parameters

- *defaultValue* `string` (default: `<price/>`) - The default order summary item subtotal price.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see [Cart object](#cart-object).
- *cartItem* `object` - The order summary item object from `wc/store/cart`,
  see [order summary item object](#cart-item-object).
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the order summary item subtotal price, which must contain the
  substring `<price/>`, or the original price format.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySubtotalPriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	subtotalPriceFormat: modifySubtotalPriceFormat,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySubtotalPriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isOrderSummaryContext = args?.context === 'summary';	if ( ! isOrderSummaryContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return '<price/> per warm beanie';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return '<price/> per cool sunglasses';	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	subtotalPriceFormat: modifySubtotalPriceFormat,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Subtotal Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/3574e7ae-9857-4651-ac9e-e6b597e3a589)

![After applying the Subtotal Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/94e18439-6d6b-44a4-ade1-8302c5984641)

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
