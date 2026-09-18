We have separate CSS/JS for Classic and Block Checkout. Please load each version’s assets based on whether its corresponding checkout shortcode or block is present on the current page.

For Classic Checkout, use `has_shortcode()` instead of `is_checkout()` to detect the checkout shortcode. Similarly, detect Block Checkout by checking for the checkout block.

Do not rely on the checkout page configured in WooCommerce Settings. The presence of the corresponding shortcode or block on the current page should be the only condition for loading its assets.

[data-fcs-block-fulfilment-date] + .fcs-fulfilment-date-window should have margin-top: 12px instead 5px.

Please review admin/checkout templates, CSS and JS against accessibility and fix any issue.

Please fix Weekdays open/close switch. Clicking always enabled the day instead making it off when its really marked as off. It has bugs there. Please fix bugs.

Please also avoid sending too many ajax request when a day open/close switch or time change. Instead All changes must require Save Changes button click.

Can we consider make this message also customizable from settings "Fulfilment available between 9:00 am and 7:00 pm." (Hours with dynamic placeholder).

Please remove the grid in settings page checkout fields tab. Keep them stack as they were (like other fields).

Block checkout fields should be cache free. Right now subscribe save option keep appearing due to cache while it suppose to show message. It should remain cache free so as user remove/add quantities it should show field/message conditionally instead being cached.

Please make sure templates/* are editable from theme as well like other WC templates. Please also use official WC templating. use of /* @var to define all variables types for IDE Compat */

Please read instructions and put them in sequence so you can do all of those changes in sequence (faster without back n forth in files) and clean git/PR history and easily do verification once you do those changes.
