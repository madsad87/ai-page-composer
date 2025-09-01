# AI Page Composer

AI-powered WordPress plugin for intelligent content generation and page composition.

## 🚀 Features

### AI Content Generation
- **Outline Generation**: AI-driven content structure creation
- **Section Generation**: Dynamic section content with LLM integration
- **Blueprint System**: Reusable content templates with schema validation
- **Block Assembly**: Intelligent Gutenberg block composition

### Content Governance
- **Run Tracking**: Complete audit trail of AI generations
- **History Management**: Version control and rollback capabilities
- **Citation System**: Source tracking for generated content
- **Rerun Management**: Reproducible content generation workflows

### Development Tools
- **Composer**: PHP dependency management with PSR-4 autoloading
- **Webpack**: Modern JavaScript bundling and optimization
- **PHPCS**: WordPress coding standards enforcement
- **PHPUnit**: Comprehensive test suite
- **ESLint/Stylelint**: Frontend code quality tools

## 📁 Project Structure

```
ai-page-composer/
├── ai-page-composer.php          # Main plugin file
├── composer.json                 # PHP dependencies
├── package.json                  # Node.js dependencies
├── includes/                     # Core plugin logic
│   ├── class-autoloader.php      # PSR-4 autoloader (fallback)
│   ├── core/                     # Core classes
│   │   ├── plugin.php            # Main plugin class
│   │   ├── activator.php         # Activation handler
│   │   └── deactivator.php       # Deactivation handler
│   ├── admin/                    # Admin interface
│   │   ├── admin-manager.php     # Admin functionality
│   │   ├── settings-manager.php  # Settings management
│   │   └── block-preferences.php # Block preferences
│   ├── api/                      # REST API & AI services
│   │   ├── ai-service-client.php # AI service integration
│   │   ├── outline-controller.php# Outline generation
│   │   ├── section-controller.php# Section generation
│   │   ├── assembly-manager.php  # Block assembly
│   │   └── governance-controller.php # Run tracking
│   ├── blueprints/               # Blueprint system
│   │   ├── blueprint-manager.php # Blueprint management
│   │   └── schema-processor.php  # Schema validation
│   ├── blocks/                   # Gutenberg blocks
│   │   └── block-manager.php     # Block registration
│   └── utils/                    # Utility classes
│       ├── security-helper.php   # Security functions
│       └── validation-helper.php # Validation functions
├── assets/                       # Frontend assets
│   ├── css/                      # Stylesheets
│   └── js/                       # JavaScript files
├── templates/                    # Template files
│   ├── admin/                    # Admin templates
│   └── blocks/                   # Block templates
└── tests/                        # Test suite
    ├── api/                      # API tests
    ├── blueprints/               # Blueprint tests
    └── integration/              # Integration tests
```

## 🛠 Development Setup

### Prerequisites
- PHP 8.0+
- Composer
- Node.js 16+
- WordPress development environment

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/madsad87/ai-page-composer.git
cd ai-page-composer
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install Node.js dependencies**
```bash
npm install
```

### Development Workflow

#### PHP Development
```bash
# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf

# Run unit tests
composer test

# Run tests with coverage
composer test:coverage
```

#### Frontend Development
```bash
# Build for production
npm run build

# Development with watch mode
npm run dev

# Lint CSS
npm run lint:css

# Lint and fix JavaScript
npm run lint:js
npm run fix:js

# Run JavaScript tests
npm run test

# Create distribution zip
npm run zip
```

## 🔧 Plugin Architecture

### Core Components
1. **Plugin Manager**: Central coordinator for all modules
2. **API Layer**: REST endpoints for AI services
3. **Blueprint System**: Content template management
4. **Governance Layer**: Audit and history tracking
5. **Admin Interface**: WordPress dashboard integration

### Design Patterns
- **Manager Pattern**: Dedicated managers for each domain
- **PSR-4 Autoloading**: Composer-based class loading
- **Singleton Pattern**: Shared service instances
- **Observer Pattern**: WordPress hooks integration
- **Strategy Pattern**: Pluggable AI services

## 🧪 Testing

Run the comprehensive test suite to ensure functionality:

```bash
# Run all tests
composer test

# Run specific test suite
composer test --testsuite API

# Run tests with filter
composer test --filter OutlineGeneratorTest
```

## 📚 Documentation

- `GOVERNANCE_IMPLEMENTATION.md`: Run tracking and audit system
- `MVDB_INTEGRATION.md`: Content retrieval pipeline
- `OUTLINE_IMPLEMENTATION.md`: Outline generation logic
- Code-level PHPDoc documentation throughout

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a pull request

## 📄 License

GPL-2.0-or-later - See [LICENSE](LICENSE) file for details.