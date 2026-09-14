# Coupons

The following Coupon filters are available:

- `coupons`
- `showApplyCouponNotice`
- `showRemoveCouponNotice`

## `coupons`

### Description

The current functionality is to display the coupon codes in the Cart and Checkout sidebars. This
could be undesirable if you dynamically generate a coupon code that is not user-friendly. It may,
therefore, be desirable to change the way this code is displayed. To achieve this, the filter
`coupons` exists. This filter could also be used to show or hide coupons. This filter must *not* be
used to alter the value/totals of a coupon. This will not carry through to the Cart totals.

### Parameters

- *coupons* `object` - The coupons object with the following keys:
- *code* `string` - The coupon code.
- *discount\_type* `string` - The type of discount. Can be `percent` or `fixed_cart`.
- *totals* `object` - The totals object with the following keys:
  -   *currency\_code* `string` - The currency code.
  -   *currency\_decimal\_separator* `string` - The currency decimal separator.
  -   *currency\_minor\_unit* `number` - The currency minor unit.
  -   *currency\_prefix* `string` - The currency prefix.
  -   *currency\_suffix* `string` - The currency suffix.
  -   *currency\_symbol* `string` - The currency symbol.
  -   *currency\_thousand\_separator* `string` - The currency thousand separator.
  -   *total\_discount* `string` - The total discount.
  -   *total\_discount\_tax* `string` - The total discount tax.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following key:
- *context* `string` (default: `summary`) - The context of the item.

### Returns

- `array` - The coupons array of objects with the same keys as above.

### Code example

```ts
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyCoupons = ( coupons, extensions, args ) => {	return coupons.map( ( coupon ) => {		if ( ! coupon.label.match( /autocoupon(?:_\d+)+/ ) ) {			return coupon;		}		return {			...coupon,			label: 'Automatic coupon',		};	} );};registerCheckoutFilters( 'example-extension', {	coupons: modifyCoupons,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Coupons filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/6cab1aff-e4b9-4909-b81c-5726c6a20c40)

![After applying the Coupons filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/a5cc2572-16e7-4781-a5ab-5d6cdced2ff6)

## `showApplyCouponNotice`

### Description

### Parameters

- *value* `boolean` (default: `true`) - Whether to show the apply coupon notice.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *context* `string` (allowed values: `wc/cart` and `wc/checkout`) - The context of the coupon
  notice.
- *couponCode* `string` - The coupon code.

### Returns

- `boolean` - Whether to show the apply coupon notice.

### Code examples

#### Basic example

```ts
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowApplyCouponNotice = ( defaultValue, extensions, args ) => {	return false;};registerCheckoutFilters( 'example-extension', {	showApplyCouponNotice: modifyShowApplyCouponNotice,} );
```

#### Advanced example

```ts
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowApplyCouponNotice = ( defaultValue, extensions, args ) => {	if ( args?.couponCode === '10off' ) {		return false;	}	return defaultValue;};registerCheckoutFilters( 'example-extension', {	showApplyCouponNotice: modifyShowApplyCouponNotice,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Show Apply Coupon Notice filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/374d4899-61f3-49b2-ae04-5541d4c130c2)

![After applying the Show Apply Coupon Notice filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/c35dbd9b-eee4-4afe-9a29-9c554d467729)

## `showRemoveCouponNotice`

### Description

### Parameters

- *value* `boolean` (default: `true`) - Whether to show the remove coupon notice.
- *extensions* `object` (default: `{}`) - The extensions object.
- *args* `object` - The arguments object with the following keys:
- *context* `string` (allowed values: `wc/cart` and `wc/checkout`) - The context of the coupon
  notice.
- *couponCode* `string` - The coupon code.

### Returns

- `boolean` - Whether to show the remove coupon notice.

### Code examples

#### Basic example

```ts
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowRemoveCouponNotice = ( defaultValue, extensions, args ) => {	return false;};registerCheckoutFilters( 'example-extension', {	showRemoveCouponNotice: modifyShowRemoveCouponNotice,} );
```

#### Advanced example

```ts
const { registerCheckoutFilters } = window.wc.blocksCheckout;const modifyShowRemoveCouponNotice = ( defaultValue, extensions, args ) => {	if ( args?.couponCode === '10off' ) {		return false;	}	return defaultValue;};registerCheckoutFilters( 'example-extension', {	showRemoveCouponNotice: modifyShowRemoveCouponNotice,} );
```

> Filters can be also combined.
> See [Combined filters](index.md)
> for an example.

### Screenshots

Before

After

![Before applying the Show Remove Coupon Notice filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/9d8607fa-ab20-4181-b70b-7954e7aa49cb)

![After applying the Show Remove Coupon Notice filter](https://github.com/woocommerce/woocommerce-blocks/assets/3323310/83d5f65f-c4f3-4707-a250-077952514931)
