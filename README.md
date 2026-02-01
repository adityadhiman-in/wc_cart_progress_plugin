# Cart Progress Discount

![alt text](<Cart Progress.png>)

A professional WooCommerce plugin by **Aditya Dhiman** that displays a global floating incentive bar to boost your Average Order Value (AOV).

🔗 **Plugin URI:** https://adityadhiman.live  
👤 **Author:** Aditya Dhiman  
📧 **Author URI:** https://adityadhiman.live

---

## Description

Cart Progress Discount is a production-grade WordPress + WooCommerce plugin that shows a **global floating incentive bar** on all pages of your store. The bar displays how much more customers need to spend to unlock discounts, encouraging them to add more items to their cart.

### Features

✅ **Global Coverage** - Appears on all pages (home, shop, product, cart, checkout, blog)  
✅ **Automatic Discounts** - Applies discounts automatically at checkout  
✅ **Real-time Updates** - Updates instantly when cart changes  
✅ **No Theme Conflicts** - Fully isolated CSS and JavaScript  
✅ **Mobile Responsive** - Looks great on all devices  
✅ **No jQuery** - Pure vanilla JavaScript for best performance  
✅ **Accessibility Ready** - Follows WCAG guidelines  
✅ **Easy Configuration** - Simple PHP array to customize discount tiers

---

## Installation

1. Upload the `wc-cart-progress-discount` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure discount rules in the PHP file (see Configuration below)

---

## Configuration

Edit the discount rules in `wc-cart-progress-discount.php`:

```php
function wc_cpd_get_discount_rules(): array
{
    return [
        2000 => 5,   // ₹2000 → 5% discount
        3000 => 10,  // ₹3000 → 10% discount
    ];
}
```

Add or modify tiers as needed. Format: `amount => discount_percentage`

---

## How It Works

1. Customer adds items to cart
2. Floating bar appears at the top of the page showing:
   - "Add ₹XXX more to unlock X% OFF" (when working toward a discount)
   - Progress bar filling up
3. When discount threshold is reached:
   - Bar shows "🎉 You unlocked X% OFF"
   - Discount is automatically applied at checkout
4. Bar disappears when cart is empty

---

## Files Structure

```
wc-cart-progress-discount/
├── README.md
├── wc-cart-progress-discount.php
└── assets/
    ├── css/
    │   └── progress.css
    └── js/
        └── progress.js
```

---

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

---

## Changelog

### 2.0.0

- Added top position floating bar
- Fixed remaining amount calculation
- Enhanced UI with gradient and animations
- Added branding by Aditya Dhiman
- Removed all console logs
- Improved mobile responsiveness
- Added gift icon
- Full RTL support

### 1.5.0

- Fixed WooCommerce session initialization
- Added admin-ajax.php endpoint for better compatibility
- Added polling as fallback mechanism
- Better error handling

### 1.0.0

- Initial release
- Basic discount functionality
- REST API endpoint
- Floating bar UI

---

## License

This plugin is licensed under the GNU General Public License v2 or later.

---

## Support

For support, please contact:

- **Email:** adityadhiman.in@gmail.com
- **Website:** https://adityadhiman.live

---

## Credits

Created with ❤️ by **Aditya Dhiman**

🔗 https://adityadhiman.live

---

## Screenshots

### Incentive Bar (Working toward discount)

```
🎁  Add ₹800 more to unlock 10% OFF
[████████████████░░░░░░░░░░] 65%
```

### Incentive Bar (Discount unlocked)

```
🎉  You unlocked 10% OFF
[████████████████████████] 100%
```
