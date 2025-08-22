# Modern WordPress Plugin Starter Template

A comprehensive, industry-standard WordPress plugin template following best practices from **Advanced Custom Fields**, **StudioPress**, and **Genesis Custom Blocks**.

## 🚀 Features

### Architecture & Design Patterns
- **Modular Architecture**: Clean separation of concerns with dedicated managers
- **ACF Integration**: Full Advanced Custom Fields support with fallbacks
- **Gutenberg Blocks**: Modern block development with ACF integration
- **REST API**: Custom endpoints with proper authentication
- **Admin Interface**: Professional WordPress admin integration

### Development Tools
- **Composer**: PHP dependency management and dev tools
- **npm Scripts**: Frontend build tools and automation
- **PHPCS**: WordPress coding standards enforcement
- **ESLint**: JavaScript code quality
- **Autoprefixer**: CSS vendor prefixing

### Security & Performance
- **Input Sanitization**: Comprehensive data validation
- **Nonce Verification**: CSRF protection
- **Capability Checks**: Proper permission handling
- **Database Optimization**: Efficient queries and caching

## 📁 Project Structure

```
modern-wp-plugin/
├── modern-wp-plugin.php          # Main plugin file
├── composer.json                 # PHP dependencies
├── package.json                  # Node.js dependencies
├── includes/                     # Core plugin logic
│   ├── class-autoloader.php      # PSR-4 autoloader
│   ├── core/                     # Core classes
│   │   ├── plugin.php            # Main plugin class
│   │   ├── activator.php         # Activation handler
│   │   └── deactivator.php       # Deactivation handler
│   ├── admin/                    # Admin interface
│   │   └── admin-manager.php     # Admin functionality
│   ├── fields/                   # Field management
│   │   └── field-manager.php     # ACF integration
│   ├── blocks/                   # Gutenberg blocks
│   │   └── block-manager.php     # Block registration
│   └── api/                      # REST API
│       └── api-manager.php       # API endpoints
├── assets/                       # Frontend assets
│   ├── css/                      # Stylesheets
│   └── js/                       # JavaScript files
├── templates/                    # Template files
│   └── blocks/                   # Block templates
└── languages/                    # Internationalization
```

## 🛠 Setup Instructions

### 1. Installation

1. **Clone or download** this template
2. **Rename** the folder to your plugin name
3. **Update** plugin information in `modern-wp-plugin.php`
4. **Replace** all instances of:
   - `modern-wp-plugin` → your plugin slug
   - `ModernWPPlugin` → your plugin namespace
   - `MODERN_WP_PLUGIN` → your plugin constants prefix

### 2. Development Environment

#### Install PHP Dependencies
```bash
composer install
```

#### Install Node.js Dependencies
```bash
npm install
```

### 3. Available Scripts

#### PHP Development
```bash
# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf

# Run tests
composer test
```

#### Frontend Development
```bash
# Build for production
npm run build

# Development with watch mode
npm run dev

# Lint CSS
npm run lint:css

# Lint JavaScript
npm run lint:js

# Fix JavaScript issues
npm run fix:js

# Generate .pot file for translations
npm run makepot

# Create distribution zip
npm run zip
```

## 🔧 Customization Guide

### Adding New Fields

1. **Edit** `includes/fields/field-manager.php`
2. **Add** your field group in `register_field_groups()`
3. **Use** `Field_Manager::get_field()` to retrieve values

### Creating New Blocks

1. **Register** block in `includes/blocks/block-manager.php`
2. **Create** template in `templates/blocks/your-block.php`
3. **Add** styles in `assets/css/style.css`

### Adding API Endpoints

1. **Edit** `includes/api/api-manager.php`
2. **Register** route in `register_rest_routes()`
3. **Add** callback and permission functions

### Admin Settings

1. **Modify** `includes/admin/admin-manager.php`
2. **Add** settings in `admin_init()`
3. **Create** callbacks for your settings

## 📚 Best Practices Followed

### From Advanced Custom Fields
- ✅ Field-centric architecture
- ✅ Extensible design patterns
- ✅ Database optimization
- ✅ Developer-friendly APIs

### From StudioPress/Genesis
- ✅ Comprehensive build tools
- ✅ Standards compliance
- ✅ Automated workflows
- ✅ Clean separation of concerns

### From Genesis Custom Blocks
- ✅ Block-first architecture
- ✅ Configuration-driven design
- ✅ Template flexibility
- ✅ Modern Gutenberg integration

## 🔒 Security Features

- **Input Sanitization**: All user inputs properly sanitized
- **Output Escaping**: All outputs properly escaped
- **Nonce Verification**: CSRF protection on forms
- **Capability Checks**: Proper permission verification
- **SQL Injection Prevention**: Prepared statements used

## 🎨 Styling Guidelines

### CSS Organization
- **Base styles**: Component-specific styles
- **Block styles**: Gutenberg block styling
- **Responsive**: Mobile-first approach
- **Accessibility**: WCAG compliant

### JavaScript Structure
- **Modular**: Object-oriented approach
- **Event-driven**: Proper event handling
- **Performance**: Debounced resize events
- **Compatibility**: Fallbacks for older browsers

## 🌐 Internationalization

The template is fully internationalization-ready:

1. **Text Domain**: `modern-wp-plugin`
2. **POT Generation**: `npm run makepot`
3. **Translation Functions**: All strings use `__()`, `_e()`, etc.

## 🧪 Testing

### PHP Testing
```bash
# Run PHPUnit tests
composer test

# Generate coverage report
composer test:coverage
```

### JavaScript Testing
```bash
# Run Jest tests
npm run test

# Watch mode
npm run test:watch
```

## 📦 Distribution

### Create Release Package
```bash
# Build and create zip file
npm run zip
```

This excludes development files and creates a clean distribution package.

## 🤝 Contributing

1. **Fork** the repository
2. **Create** feature branch
3. **Follow** coding standards
4. **Add** tests for new features
5. **Submit** pull request

## 📄 License

GPL v2 or later - same as WordPress

## 🆘 Support

- **Documentation**: Check inline comments
- **Issues**: Use GitHub issues
- **Standards**: Follow WordPress Coding Standards

---

**Happy Plugin Development!** 🎉

This template provides a solid foundation for creating modern, secure, and performant WordPress plugins following industry best practices.