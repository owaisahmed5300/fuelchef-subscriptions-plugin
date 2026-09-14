# Cart line items

The following Cart Line Items filters are available:

- `cartItemClass`
- `cartItemPrice`
- `itemName`
- `saleBadgePriceFormat`
- `showRemoveItemLink`
- `subtotalPriceFormat`

The following objects are shared between the filters:

- Cart object
- Cart Item object

The following screenshot shows which parts the individual filters affect:

![Cart Line Items](https://woocommerce.com/wp-content/uploads/2023/10/Screenshot-2023-10-26-at-13.12.33.png)

## `cartItemClass`

### Description

The `cartItemClass` filter allows to change the cart item class.

### Parameters

- *defaultValue* `object` (default: `''`) - The default cart item class.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.

### Returns

- `string` - The modified cart item class, or an empty string.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemClass = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return 'my-custom-class';};registerCheckoutFilters( 'example-extension', {	cartItemClass: modifyCartItemClass,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemClass = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return 'cool-class';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return 'hot-class';	}	return 'my-custom-class';};registerCheckoutFilters( 'example-extension', {	cartItemClass: modifyCartItemClass,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Cart Item Class filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/a587a6ce-d051-4ed0-bba5-815b5d72179d)

![After applying the Cart Item Class filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/9b25eeae-6d81-4e28-b177-32f942e1d0c2)

## `cartItemPrice`

### Description

The `cartItemPrice` filter allows to format the cart item price.

### Parameters

- *defaultValue* `string` (default: `<price/>`) - The default cart item price.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the cart item price, which must contain the substring
  `<price/>`, or the original price format.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemPrice = ( defaultValue, extensions, args, validation ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return '<price/> for all items';};registerCheckoutFilters( 'example-extension', {	cartItemPrice: modifyCartItemPrice,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCartItemPrice = ( defaultValue, extensions, args, validation ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return '<price/> to keep you warm';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return '<price/> to keep you cool';	}	return '<price/> for all items';};registerCheckoutFilters( 'example-extension', {	cartItemPrice: modifyCartItemPrice,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Cart Item Price filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/bbaeb68a-492e-41e7-87b7-4b8b05ca3709)

![After applying the Cart Item Price filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/bbaeb68a-492e-41e7-87b7-4b8b05ca3709)

## `itemName`

### Description

The `itemName` filter allows to change the cart item name.

### Parameters

- *defaultValue* `string` - The default cart item name.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.

### Returns

- `string` - The original or modified cart item name.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyItemName = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return `🪴 ${ defaultValue } 🪴`;};registerCheckoutFilters( 'example-extension', {	itemName: modifyItemName,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyItemName = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return `⛷️ ${ defaultValue } ⛷️`;	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return `🏄‍♂️ ${ defaultValue } 🏄‍♂️`;	}	return `🪴 ${ defaultValue } 🪴`;};registerCheckoutFilters( 'example-extension', {	itemName: modifyItemName,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Item Name filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/97d0f501-138e-4448-93df-a4d865b524e6)

![After applying the Item Name filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/69381932-d064-4e8f-b378-c2477fef56ae)

## `saleBadgePriceFormat`

### Description

The `saleBadgePriceFormat` filter allows to format the cart item sale badge price.

### Parameters

- *defaultValue* `string` (default: `<price/>`) - The default cart item sale badge price.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the cart item sale badge price, which must contain the substring
  `<price/>`, or the original price format.

### Code examples

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySaleBadgePriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	saleBadgePriceFormat: modifySaleBadgePriceFormat,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySaleBadgePriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return '<price/> per item while keeping warm';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return '<price/> per item while looking cool';	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	saleBadgePriceFormat: modifySaleBadgePriceFormat,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Sale Badge Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/d2aeb206-e620-44e0-93c1-31484cfcdca6)

![After applying the Sale Badge Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/6b929695-5d89-433b-8694-b9201a7c0519)

## `showRemoveItemLink`

### Description

The `showRemoveItemLink` is used to show or hide the cart item remove link.

### Parameters

- *defaultValue* (type: `boolean`, default: `true`) - The default value of the remove link.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.

### Returns

- `boolean` - `true` if the cart item remove link should be shown, `false` otherwise.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowRemoveItemLink = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return false;};registerCheckoutFilters( 'example-extension', {	showRemoveItemLink: modifyShowRemoveItemLink,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowRemoveItemLink = ( defaultValue, extensions, args ) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return false;	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return false;	}	return true;};registerCheckoutFilters( 'example-extension', {	showRemoveItemLink: modifyShowRemoveItemLink,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Show Remove Item Link filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/a4254f3b-f056-47ad-b34a-d5f6d5500e56)

![After applying the Show Remove Item Link filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/32c55dc7-ef65-4f35-ab90-9533bc79d362)

## `subtotalPriceFormat`

### Description

The `subtotalPriceFormat` filter allows to format the cart item subtotal price.

### Parameters

- *defaultValue* `string` (default: `<price/>`) - The default cart item subtotal price.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *cart* `object` - The cart object from `wc/store/cart`, see Cart object.
- *cartItem* `object` - The cart item object from `wc/store/cart`, see Cart Item object.
- *context* `string` (allowed values: `cart` or `summary`) - The context of the item.
- *validation* `boolean` - Checks if the return value contains the substring `<price/>`.

### Returns

- `string` - The modified format of the cart item subtotal price, which must contain the substring
  `<price/>`, or the original price format.

### Code examples

#### Basic example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySubtotalPriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	subtotalPriceFormat: modifySubtotalPriceFormat,} );
```

#### Advanced example

```tsx
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifySubtotalPriceFormat = (	defaultValue,	extensions,	args,	validation) => {	const isCartContext = args?.context === 'cart';	if ( ! isCartContext ) {		return defaultValue;	}	if ( args?.cartItem?.name === 'Beanie with Logo' ) {		return '<price/> per warm beanie';	}	if ( args?.cartItem?.name === 'Sunglasses' ) {		return '<price/> per cool sunglasses';	}	return '<price/> per item';};registerCheckoutFilters( 'example-extension', {	subtotalPriceFormat: modifySubtotalPriceFormat,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Subtotal Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/a392cb24-4c40-4e25-8396-bf4971830e22)

![After applying the Subtotal Price Format filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/af69b26f-662a-4ef9-a288-3713b6e46373)

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
- *cartItems* `array` - The cart items array with cart item objects, see Cart Item object.
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
