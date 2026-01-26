# Competitor Knowledge

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%3E%3D8.0-purple.svg)](https://woocommerce.com)
[![License](https://img.shields.io/badge/License-GPL--3.0--or--later-green.svg)](LICENSE)

AI-powered competitor analysis for WooCommerce products. Automatically discover, track, and analyze competitor pricing and product information using advanced AI models and web search capabilities.

## 🚀 Features

### Core Capabilities
- **Multi-Provider AI Support**: Choose between Google Gemini, Ollama (local), or OpenRouter
- **Intelligent Web Search**: Powered by Tavily API for accurate competitor discovery
- **Automated Analysis**: Background processing using WooCommerce Action Scheduler
- **Price History Tracking**: Monitor competitor price changes over time with visual charts
- **Price Drop Alerts**: Email notifications when competitors offer better prices
- **Scheduled Analysis**: Automatic daily, weekly, or monthly competitor checks
- **Bulk Operations**: Analyze multiple products simultaneously
- **Secure API Key Storage**: Encrypted storage for sensitive credentials

### Technical Highlights
- **Modern PHP Architecture**: PSR-4 autoloading, strict typing, OOP design patterns
- **Dependency Injection**: Container-based service management
- **Interface-Driven Design**: Swappable AI and search providers
- **Comprehensive Testing**: PHPUnit tests with code coverage reporting
- **Code Quality**: PHPStan level 5, PHPCS WordPress standards compliance
- **Scalable**: Built for distributed systems and high-availability environments

## 📋 Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 6.0 or higher
- **WooCommerce**: 8.0 or higher
- **Composer**: For dependency management
- **Node.js & npm**: For asset compilation

## 🔧 Installation

### 1. Clone or Download

```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/competitor-knowledge.git
cd competitor-knowledge
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev

# Install Node dependencies
npm install

# Build assets
npm run build
```

### 3. Activate Plugin

Navigate to **WordPress Admin → Plugins** and activate **Competitor Knowledge**.

## ⚙️ Configuration

### Initial Setup

1. Go to **Settings → Competitor Knowledge**
2. Configure your AI provider:
   - **Google Gemini**: Enter your Google AI API key
   - **Ollama**: Set your local Ollama URL (default: `http://localhost:11434`)
   - **OpenRouter**: Enter your OpenRouter API key
3. Enter your **Tavily Search API Key**
4. Configure notification settings (optional)
5. Enable scheduled analysis (optional)

### API Keys

#### Google Gemini
1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Create a new API key
3. Paste into the plugin settings

#### Tavily Search
1. Sign up at [Tavily](https://tavily.com)
2. Generate an API key
3. Paste into the plugin settings

#### OpenRouter (Optional)
1. Sign up at [OpenRouter](https://openrouter.ai)
2. Generate an API key
3. Paste into the plugin settings

#### Ollama (Optional - Local AI)
1. Install [Ollama](https://ollama.ai)
2. Pull a model: `ollama pull llama3`
3. Ensure Ollama is running locally

## 📖 Usage

### Manual Analysis

1. Navigate to **Products** in WordPress admin
2. Edit any WooCommerce product
3. Scroll to the **Competitor Analysis** metabox
4. Click **Run New Analysis**
5. View results including:
   - Competitor names and URLs
   - Price comparisons
   - Stock status
   - Comparison notes
   - Price history chart

### Bulk Analysis

1. Go to **Products** list
2. Select multiple products using checkboxes
3. Choose **Run Competitor Analysis** from bulk actions
4. Click **Apply**

### Scheduled Analysis

1. Go to **Settings → Competitor Knowledge**
2. Enable **Scheduled Analysis**
3. Select frequency (Daily, Weekly, Monthly)
4. Save settings

Analysis will run automatically in the background for all products with existing analyses.

### Price Alerts

Configure email notifications for price drops:

1. Set **Notification Email** in settings
2. Set **Price Drop Threshold** (percentage)
3. Receive alerts when competitors price below your threshold

## 🏗️ Architecture

### Directory Structure

```
competitor-knowledge/
├── src/
│   ├── AI/                    # AI provider implementations
│   │   ├── Contracts/         # AI provider interface
│   │   └── Providers/         # Google, Ollama, OpenRouter
│   ├── Admin/                 # WordPress admin integration
│   │   ├── Ajax.php          # AJAX handlers
│   │   ├── BulkActions.php   # Bulk operations
│   │   ├── Metaboxes.php     # Product metaboxes
│   │   └── Settings.php      # Settings page
│   ├── Analysis/              # Core analysis logic
│   │   ├── Jobs/             # Background job handlers
│   │   ├── Analyzer.php      # Main analysis orchestrator
│   │   └── AnalysisResult.php
│   ├── Core/                  # Plugin bootstrap
│   │   ├── Container.php     # DI container
│   │   ├── Encryption.php    # API key encryption
│   │   └── Plugin.php        # Main plugin class
│   ├── Data/                  # Data layer
│   │   ├── AnalysisCPT.php   # Custom post type
│   │   ├── AnalysisRepository.php
│   │   ├── Installer.php     # Database setup
│   │   └── PriceHistoryRepository.php
│   └── Search/                # Search provider implementations
│       ├── Contracts/         # Search provider interface
│       └── Providers/         # Tavily implementation
├── assets/                    # Compiled assets
├── assets-src/                # Source assets
│   ├── js/                   # JavaScript source
│   └── scss/                 # SCSS source
├── templates/                 # PHP templates
├── tests/                     # PHPUnit tests
├── vendor/                    # Composer dependencies
├── composer.json
├── package.json
├── phpunit.xml.dist
├── phpstan.neon.dist
└── phpcs.xml.dist
```

### Design Patterns

- **Dependency Injection**: Service container for loose coupling
- **Repository Pattern**: Data access abstraction
- **Strategy Pattern**: Swappable AI and search providers
- **Factory Pattern**: Dynamic provider instantiation
- **Interface Segregation**: Clean contracts for extensibility

### Data Flow

```mermaid
graph LR
    A[Product] --> B[Analysis Job]
    B --> C[Analyzer]
    C --> D[Search Provider]
    D --> E[Tavily API]
    E --> D
    D --> C
    C --> F[AI Provider]
    F --> G[Google/Ollama/OpenRouter]
    G --> F
    F --> C
    C --> H[Repository]
    H --> I[Database]
    C --> J[Price History]
    J --> I
    C --> K[Email Alerts]
```

## 🧪 Development

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer coverage

# Generate HTML coverage report
composer coverage-html
```

### Code Quality

```bash
# Run PHP CodeSniffer
composer lint

# Auto-fix coding standards
composer fix

# Run PHPStan analysis
composer analyze
```

### Asset Development

```bash
# Watch for changes and auto-compile
npm run watch

# Build for production
npm run build
```

### Adding a New AI Provider

1. Create a new class in `src/AI/Providers/`
2. Implement `AIProviderInterface`
3. Register in `src/Core/Plugin.php` container
4. Add settings fields in `src/Admin/Settings.php`

Example:

```php
namespace CompetitorKnowledge\AI\Providers;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Analysis\AnalysisResult;

class CustomProvider implements AIProviderInterface {
    public function analyze(string $prompt, array $context): AnalysisResult {
        // Implementation
    }
}
```

## 🪝 Hooks Reference

The plugin provides comprehensive actions and filters for third-party integration. All hooks are prefixed with `ck_` (Competitor Knowledge).

### Plugin Initialization

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_before_init` | `$container` | Fires before plugin services are registered. Use to prepare dependencies. |
| `ck_after_init` | `$container` | Fires after plugin is fully initialized. |
| `ck_services_registered` | `$container` | Fires after core services are registered. Use to register additional services. |
| `ck_hooks_registered` | — | Fires after WordPress hooks are registered. |
| `ck_cpt_registered` | — | Fires after the analysis CPT is registered. Use to add taxonomies. |
| `ck_admin_assets_enqueued` | — | Fires after admin assets are enqueued. Use to add custom scripts. |

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_search_provider` | `$provider` | Replace or decorate the search provider instance. |
| `ck_ai_provider` | `$provider`, `$provider_type`, `$model_name` | Replace or decorate the AI provider instance. |
| `ck_ai_model_name` | `$model_name`, `$provider_type` | Modify the AI model name before provider instantiation. |
| `ck_admin_script_data` | `$data` | Modify the localized JavaScript data for admin scripts. |

**Example: Register a Custom AI Provider**

```php
add_filter( 'ck_ai_provider', function( $provider, $type, $model ) {
    if ( 'custom' === $type ) {
        return new MyCustomAIProvider( $model );
    }
    return $provider;
}, 10, 3 );

add_filter( 'ck_ai_provider_options', function( $providers ) {
    $providers['custom'] = __( 'My Custom Provider', 'my-plugin' );
    return $providers;
} );
```

### Analysis Process

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_before_analysis` | `$analysis_id`, `$product_id`, `$product` | Fires before analysis starts. |
| `ck_after_analysis` | `$analysis_id`, `$product_id`, `$product` | Fires after successful analysis completion. |
| `ck_analysis_failed` | `$analysis_id`, `$product_id`, `$exception` | Fires when analysis fails. Use for custom error handling. |
| `ck_after_search` | `$search_results`, `$query`, `$analysis_id` | Fires after search results are retrieved. |
| `ck_before_ai_analysis` | `$prompt`, `$context`, `$analysis_id` | Fires before AI analysis is performed. |
| `ck_after_ai_analysis` | `$analysis_result`, `$analysis_id` | Fires after AI analysis completes. |
| `ck_after_save_results` | `$analysis_id`, `$result_data`, `$product_id` | Fires after results are saved to database. |

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_analysis_product_data` | `$product_data`, `$product`, `$analysis_id` | Modify product data used for analysis. |
| `ck_get_product_data` | `$data`, `$product` | Modify raw product data extraction. |
| `ck_search_query` | `$query`, `$product`, `$analysis_id` | Modify the search query string. |
| `ck_search_results_limit` | `$limit`, `$product`, `$analysis_id` | Control number of search results (default: 5). |
| `ck_ai_context` | `$context`, `$product`, `$analysis_id` | Modify context data passed to AI. |
| `ck_ai_prompt` | `$prompt`, `$context`, `$product`, `$analysis_id` | Modify the AI prompt. |
| `ck_analysis_result` | `$analysis_result`, `$product`, `$analysis_id` | Modify analysis result from AI. |
| `ck_before_save_results` | `$result_data`, `$analysis_id`, `$product_id` | Modify data before saving to database. |

**Example: Add Custom Data to Analysis**

```php
// Add custom product attributes to analysis context
add_filter( 'ck_analysis_product_data', function( $data, $product, $analysis_id ) {
    $data['brand'] = $product->get_attribute( 'brand' );
    $data['categories'] = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
    return $data;
}, 10, 3 );

// Customize the search query
add_filter( 'ck_search_query', function( $query, $product, $analysis_id ) {
    $brand = $product->get_attribute( 'brand' );
    return sprintf( 'Buy %s %s %s best price', $brand, $product->get_name(), $product->get_sku() );
}, 10, 3 );
```

### Price History & Alerts

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_before_add_price_record` | `$product_id`, `$analysis_id`, `$competitor_name`, `$price_data` | Before price record is added. |
| `ck_after_add_price_record` | `$product_id`, `$analysis_id`, `$competitor_name`, `$price_data` | After price record is added. |
| `ck_before_price_alert` | `$email`, `$subject`, `$message`, `$product`, `$competitor_name`, `$competitor_price`, `$analysis_id` | Before alert email is sent. |
| `ck_after_price_alert` | `$email`, `$subject`, `$message`, `$product`, `$competitor_name`, `$competitor_price`, `$analysis_id` | After alert email is sent. |

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_competitors_for_price_history` | `$competitors`, `$analysis_id`, `$product_id` | Filter competitors before price history processing. |
| `ck_price_record_data` | `$price_data`, `$comp`, `$product_id`, `$analysis_id` | Modify price data before recording. |
| `ck_price_alert_threshold` | `$threshold`, `$product`, `$competitor_name`, `$competitor_price` | Modify alert threshold per-product. |
| `ck_price_alert_email` | `$email`, `$product`, `$competitor_name`, `$competitor_price` | Modify notification email address. |
| `ck_should_send_price_alert` | `$should_send`, `$diff_percent`, `$threshold`, `$product`, `$competitor_name`, `$competitor_price` | Override alert sending logic. |
| `ck_price_alert_subject` | `$subject`, `$product`, `$competitor_name`, `$diff_percent` | Modify email subject. |
| `ck_price_alert_message` | `$message`, `$product`, `$competitor_name`, `$competitor_price`, `$diff_percent` | Modify email message body. |

**Example: Custom Price Alert Logic**

```php
// Send alerts to different emails based on product category
add_filter( 'ck_price_alert_email', function( $email, $product, $competitor_name, $price ) {
    $categories = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
    if ( in_array( 'electronics', $categories, true ) ) {
        return 'electronics-team@example.com';
    }
    return $email;
}, 10, 4 );

// Custom alert threshold per product
add_filter( 'ck_price_alert_threshold', function( $threshold, $product ) {
    $custom = get_post_meta( $product->get_id(), '_custom_price_threshold', true );
    return $custom ? (float) $custom : $threshold;
}, 10, 2 );
```

### Data Repository

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_analysis_created` | `$post_id`, `$product_id` | Fires after an analysis record is created. |
| `ck_analysis_status_changed` | `$analysis_id`, `$new_status`, `$old_status` | Fires when analysis status changes. |
| `ck_analysis_results_saved` | `$analysis_id`, `$data` | Fires after results are stored. |

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_analysis_post_title` | `$title`, `$product_id` | Modify the analysis post title. |
| `ck_analysis_data_before_save` | `$data`, `$analysis_id` | Modify analysis data before storage. |

**Example: Log Analysis Status Changes**

```php
add_action( 'ck_analysis_status_changed', function( $analysis_id, $new_status, $old_status ) {
    if ( 'completed' === $new_status ) {
        // Send notification, update external system, etc.
        do_action( 'my_custom_analysis_completed', $analysis_id );
    }
}, 10, 3 );
```

### Scheduled Analysis

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_before_scheduled_analysis` | — | Fires before scheduled job runs. |
| `ck_after_scheduled_analysis` | `$product_ids` | Fires after scheduled job completes. |
| `ck_product_scheduled_for_analysis` | `$product_id`, `$analysis_id` | Fires after a product is scheduled. |

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_scheduled_analysis_batch_limit` | `$limit` | Control batch size (default: 50). |
| `ck_scheduled_analysis_query_args` | `$args`, `$categories` | Modify the products query arguments. |
| `ck_scheduled_analysis_products` | `$product_ids` | Filter final product list to analyze. |
| `ck_scheduled_analysis_job_delay` | `$delay` | Control delay between jobs in seconds (default: 60). |

**Example: Limit Scheduled Analysis to Specific Products**

```php
// Only analyze products with a specific meta flag
add_filter( 'ck_scheduled_analysis_query_args', function( $args, $categories ) {
    $args['meta_query'] = [
        [
            'key'   => '_enable_competitor_tracking',
            'value' => 'yes',
        ],
    ];
    return $args;
}, 10, 2 );

// Exclude out-of-stock products
add_filter( 'ck_scheduled_analysis_products', function( $product_ids ) {
    return array_filter( $product_ids, function( $id ) {
        $product = wc_get_product( $id );
        return $product && $product->is_in_stock();
    } );
} );
```

### AJAX Requests

#### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_before_ajax_analysis` | `$product_id` | Before AJAX analysis request is processed. |
| `ck_after_ajax_analysis` | `$product_id`, `$analysis_id` | After AJAX analysis is successfully scheduled. |

### Settings

#### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `ck_sensitive_settings_fields` | `$fields` | Add custom fields to encrypt on save. |
| `ck_ai_provider_options` | `$providers` | Add custom AI providers to dropdown. |

**Example: Add Custom AI Provider to Settings**

```php
// Add provider to dropdown
add_filter( 'ck_ai_provider_options', function( $providers ) {
    $providers['anthropic'] = __( 'Anthropic Claude', 'my-plugin' );
    return $providers;
} );

// Handle the custom provider
add_filter( 'ck_ai_provider', function( $provider, $type, $model ) {
    if ( 'anthropic' === $type ) {
        $api_key = \CompetitorKnowledge\Admin\Settings::get_decrypted( 'anthropic_api_key' );
        return new My_Anthropic_Provider( $api_key, $model );
    }
    return $provider;
}, 10, 3 );

// Encrypt the API key
add_filter( 'ck_sensitive_settings_fields', function( $fields ) {
    $fields[] = 'anthropic_api_key';
    return $fields;
} );
```

## 🔐 Security

- **API Key Encryption**: All sensitive credentials are encrypted using WordPress salts
- **Nonce Verification**: All AJAX requests are protected
- **Capability Checks**: Admin-only access to settings and operations
- **Data Sanitization**: All inputs are sanitized and validated
- **Prepared Statements**: Database queries use `$wpdb->prepare()`

## 🐛 Debugging

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at `wp-content/debug.log` for analysis errors.

### Action Scheduler Logs

View background job status:
1. Go to **WooCommerce → Status → Scheduled Actions**
2. Search for `ck_run_analysis` or `ck_scheduled_analysis`

## 📊 Database Schema

### Custom Tables

#### `wp_ck_price_history`
Stores competitor price history for charting and trend analysis.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `product_id` | BIGINT | WooCommerce product ID |
| `analysis_id` | BIGINT | Analysis post ID |
| `competitor_name` | VARCHAR(255) | Competitor identifier |
| `price` | DECIMAL(10,2) | Competitor price |
| `currency` | VARCHAR(10) | Currency code |
| `recorded_at` | DATETIME | Timestamp |

### Custom Post Types

#### `ck_analysis`
Stores analysis results and metadata.

**Meta Keys:**
- `_ck_target_product_id`: Associated WooCommerce product
- `_ck_status`: `pending`, `processing`, `completed`, `failed`
- `_ck_results`: JSON-encoded analysis results

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Follow WordPress coding standards
4. Write tests for new functionality
5. Ensure all tests pass: `composer test`
6. Run code quality checks: `composer lint && composer analyze`
7. Commit your changes: `git commit -m 'Add amazing feature'`
8. Push to the branch: `git push origin feature/amazing-feature`
9. Open a Pull Request

## 📝 License

This plugin is licensed under the **GPL-3.0-or-later** license. See [LICENSE](LICENSE) file for details.

## 👤 Author

**Ahmad Wael**
- Website: [www.bbioon.com](https://www.bbioon.com)
- Email: ahmad@bbioon.com

## 🙏 Acknowledgments

- **Google Gemini**: For powerful AI analysis capabilities
- **Tavily**: For accurate web search results
- **Ollama**: For local AI model support
- **WooCommerce**: For the robust e-commerce platform
- **Action Scheduler**: For reliable background processing

## 📚 Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)
- [Google AI Documentation](https://ai.google.dev/docs)
- [Tavily API Documentation](https://docs.tavily.com/)
- [Ollama Documentation](https://github.com/ollama/ollama)

## 🗺️ Roadmap

- [ ] Support for additional AI providers (Anthropic Claude, OpenAI)
- [ ] Advanced filtering and search in analysis history
- [ ] Export analysis reports (PDF, CSV)
- [ ] Multi-currency support
- [ ] Competitor product matching confidence scores
- [ ] REST API endpoints for external integrations
- [ ] Dashboard widget with quick insights
- [ ] Comparison matrix view for multiple competitors

## ❓ FAQ

### Q: How often should I run competitor analysis?

**A:** It depends on your market dynamics. For fast-moving products, daily analysis is recommended. For stable markets, weekly or monthly may suffice.

### Q: Which AI provider should I choose?

**A:** 
- **Google Gemini**: Best overall accuracy and speed (requires API key)
- **Ollama**: Best for privacy and no API costs (requires local setup)
- **OpenRouter**: Access to multiple models (requires API key)

### Q: Are API keys stored securely?

**A:** Yes, all API keys are encrypted using WordPress authentication salts before storage.

### Q: Can I analyze products in bulk?

**A:** Yes, use the bulk actions dropdown on the Products page to analyze multiple products at once.

### Q: How do I view price history?

**A:** Edit any product and check the Competitor Analysis metabox. If multiple analyses exist, you'll see a price history chart.

---

**Made with ❤️ by [Ahmad Wael](https://www.bbioon.com)**
