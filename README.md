# CV Enhanced WooCommerce Reviews

**Version:** 1.0.4  
**Author:** Duy Nguyen ([duyn1412](https://github.com/duyn1412))  
**WordPress Plugin:** Enhanced product review system for WooCommerce  

## Description

Upgrade your WooCommerce product review system with professional features including average rating summaries, advanced filtering/sorting, and helpful voting via AJAX. Fully responsive and performance-optimized.

## Features

### ✅ Core Features
- **Average Rating Summary** - Display clear average scores and total review counts
- **Star Distribution Chart** - Visual bar chart showing review breakdown by star rating
- **AJAX Pagination** - Fast, no-reload page navigation
- **Mobile & Desktop Optimized** - Fully responsive design

### ✅ Advanced Filtering & Sorting
- **Filter by Rating** - Dropdown to filter reviews by star rating (1-5 stars)
- **Sort Options:**
  - Newest (default)
  - Highest Rating
  - Lowest Rating
  - Most Helpful

### ✅ Helpful Voting System
- **User Voting** - Thumbs up 👍 / Thumbs down 👎 for each review
- **Anti-Spam Protection** - One vote per user (by user ID or IP/session for guests)
- **Real-time Updates** - Instant AJAX vote count updates
- **Most Helpful Sorting** - Reviews sorted by helpfulness score

### ✅ Technical Highlights
- **100% AJAX** - No page reloads for filtering, sorting, or voting
- **WooCommerce Compatible** - Uses official hooks and templates
- **Performance Optimized** - Efficient database queries
- **Clean Code** - Well-documented, modular architecture
- **English UI** - Professional, clear interface

## Installation

1. Upload the `cv-enhanced-review` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress 'Plugins' menu
3. Use the shortcode `[cv_woo_reviews]` in your product pages or templates

## Usage

### Shortcode
```php
[cv_woo_reviews]
```

Place this shortcode anywhere in your product layout to display the enhanced review system.

### Template Integration
```php
<?php echo do_shortcode('[cv_woo_reviews]'); ?>
```

## Requirements

- WordPress 5.0+
- WooCommerce 3.6+
- PHP 7.4+
- jQuery

## File Structure

```
cv-enhanced-review/
├── cv-enhanced-review.php       # Main plugin file
├── readme.txt                   # WordPress plugin readme
├── README.md                    # This file
├── includes/
│   └── class-cver-core.php     # Core functionality class
├── assets/
│   ├── css/
│   │   └── cver-style.css      # Plugin styles
│   └── js/
│       └── cver-script.js      # AJAX & interaction logic
└── languages/                   # Translation files (future)
```

## Changelog

### 1.0.4 (Current)
- Added rating filter dropdown
- Implemented helpful voting with icons (👍👎)
- Improved responsive design
- Enhanced AJAX performance

### 1.0.3
- Added filter and sort controls
- Improved pagination HTML structure
- Enhanced mobile compatibility

### 1.0.2
- Initial AJAX pagination implementation
- Added loading overlays
- Fixed cache issues

### 1.0.0
- Initial release
- Core review display functionality

## Screenshots

(Add screenshots here when ready)

## Support

For issues, questions, or feature requests, please visit:
- GitHub: [https://github.com/duyn1412](https://github.com/duyn1412)

## Credits

**Developed by:** Duy Nguyen  
**Profile:** [GitHub @duyn1412](https://github.com/duyn1412)  
**Experience:** WordPress Expert - 10+ years in I.T.

## License

GPLv2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

**⭐ If you find this plugin useful, please star the repository on GitHub!**

