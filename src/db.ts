export interface Plugin {
  id: number;
  slug: string;
  name: string;
  basename: string;
  description: string;
  changelog: string;
  price: number;
  category: 'Plugin';
  active: boolean;
  enabled: boolean;
  auto_update_allowed: boolean;
  min_wp_version: string;
  min_php_version: string;
  tested_wp_version: string;
  version: string;
  created_at: string;
}

export interface Theme {
  id: number;
  slug: string;
  name: string;
  description: string;
  price: number;
  category: 'Theme';
  active: boolean;
  version: string;
  created_at: string;
}

export interface Plan {
  id: number;
  slug: string;
  name: string;
  description: string;
  price: number;
  term_months: number;
  website_limit: number;
  active: boolean;
  included_plugins: number[];
  included_themes: number[];
  created_at: string;
}

export interface Customer {
  id: number;
  email: string;
  name: string;
  password_hash: string;
  status: 'active' | 'suspended';
  created_at: string;
}

export interface Admin {
  id: number;
  email: string;
  password_hash: string;
  created_at: string;
}

export interface Website {
  id: number;
  customer_id: number;
  name: string;
  url: string;
  api_key: string;
  secret: string;
  status: 'active' | 'revoked';
  wp_version: string;
  php_version: string;
  last_check_at: string | null;
  last_check_ip: string | null;
  connected_at: string;
}

export interface StoreOrder {
  id: number;
  order_code: string;
  customer_id: number;
  amount: number;
  currency: string;
  status: 'pending' | 'confirmed' | 'cancelled' | 'rejected';
  tx_hash: string | null;
  payment_expires_at: string;
  paid_at: string | null;
  created_at: string;
  items: Array<{
    id: number;
    product_id: number;
    product_name: string;
    category: 'Plugin' | 'Theme' | 'Plan';
    unit_price: number;
    quantity: number;
  }>;
}

export interface UpdateLog {
  id: number;
  website_id: number | null;
  customer_id: number | null;
  plugin_id: number | null;
  action: string;
  outcome: 'success' | 'failure' | 'warning';
  message: string;
  ip: string;
  context: string;
  created_at: string;
}

export interface StoreSettings {
  site_title: string;
  site_tagline: string;
  site_description: string;
  meta_description: string;
  footer_text: string;
  support_email: string;
  bsc_wallet: string;
  usdt_contract: string;
  bsc_rpc: string;
}

// In-Memory Database store
class Database {
  plugins: Plugin[] = [
    {
      id: 1,
      slug: 'wordpresso-auto-updater',
      name: 'Wordpresso Auto Updater Pro',
      basename: 'wordpresso-auto-updater/wordpresso-auto-updater.php',
      description: 'The native WordPress bridge that connects customer sites directly to your Wordpresso update server with automatic background checks and one-click upgrades.',
      changelog: 'v1.2.0 (2026-10-15)\n- Added granular HMAC signing verification\n- Improved transient cache invalidation\n- Added PHP 8.3 & 8.4 compatibility checks',
      price: 49.00,
      category: 'Plugin',
      active: true,
      enabled: true,
      auto_update_allowed: true,
      min_wp_version: '5.8',
      min_php_version: '8.0',
      tested_wp_version: '6.7',
      version: '1.2.0',
      created_at: new Date('2026-01-10').toISOString()
    },
    {
      id: 2,
      slug: 'woocommerce-dynamic-pricing-pro',
      name: 'WooCommerce Dynamic Pricing & Bulk Discounts',
      basename: 'wc-dynamic-pricing-pro/wc-dynamic-pricing-pro.php',
      description: 'Advanced rule-based pricing, tiered quantity discounts, customer group discounts, and scheduled promotional deals for WooCommerce stores.',
      changelog: 'v2.4.1 (2026-09-20)\n- Fixed tax calculation for tiered wholesale quantities\n- Enhanced REST API pricing triggers',
      price: 39.00,
      category: 'Plugin',
      active: true,
      enabled: true,
      auto_update_allowed: true,
      min_wp_version: '6.0',
      min_php_version: '8.1',
      tested_wp_version: '6.7',
      version: '2.4.1',
      created_at: new Date('2026-02-14').toISOString()
    },
    {
      id: 3,
      slug: 'elementor-studio-elements',
      name: 'Elementor Studio Pro Elements & Widgets',
      basename: 'elementor-studio-elements/elementor-studio-elements.php',
      description: 'Over 60 high-performance creative widgets, interactive particle effects, advanced carousel layouts, and query builders for Elementor.',
      changelog: 'v3.1.0 (2026-10-01)\n- Added modern bento grid widgets\n- Optimized asset payload reducing page weight by 45%',
      price: 29.00,
      category: 'Plugin',
      active: true,
      enabled: true,
      auto_update_allowed: true,
      min_wp_version: '5.9',
      min_php_version: '8.0',
      tested_wp_version: '6.7',
      version: '3.1.0',
      created_at: new Date('2026-03-01').toISOString()
    },
    {
      id: 4,
      slug: 'saas-flow-blocks',
      name: 'SaaS Flow Gutenberg Block Suite',
      basename: 'saas-flow-blocks/saas-flow-blocks.php',
      description: 'A dedicated block collection for high-converting SaaS landing pages: comparison matrices, testimonial walls, pricing calculators, and interactive feature tours.',
      changelog: 'v1.0.4 (2026-11-01)\n- Initial release for modern block themes and full-site editing',
      price: 35.00,
      category: 'Plugin',
      active: true,
      enabled: true,
      auto_update_allowed: true,
      min_wp_version: '6.2',
      min_php_version: '8.1',
      tested_wp_version: '6.7',
      version: '1.0.4',
      created_at: new Date('2026-04-12').toISOString()
    }
  ];

  themes: Theme[] = [
    {
      id: 1,
      slug: 'aura-portfolio-theme',
      name: 'Aura Minimal Portfolio & Agency Theme',
      description: 'Modern, ultra-fast block theme crafted for digital designers, studios, and creative agencies with smooth micro-interactions and dark mode presets.',
      price: 59.00,
      category: 'Theme',
      active: true,
      version: '1.5.0',
      created_at: new Date('2026-02-01').toISOString()
    },
    {
      id: 2,
      slug: 'apex-editorial-theme',
      name: 'Apex Magazine & Editorial Theme',
      description: 'Clean typographic layout designed for serious publications, news agencies, and niche technology blogs with lightning-fast Core Web Vitals scores.',
      price: 49.00,
      category: 'Theme',
      active: true,
      version: '2.0.1',
      created_at: new Date('2026-03-15').toISOString()
    }
  ];

  plans: Plan[] = [
    {
      id: 1,
      slug: 'starter-toolkit',
      name: 'Starter Toolkit Plan',
      description: 'Essential toolkit for single site owners or freelancers launching their first client portal.',
      price: 29.00,
      term_months: 6,
      website_limit: 2,
      active: true,
      included_plugins: [1, 3],
      included_themes: [],
      created_at: new Date('2026-01-01').toISOString()
    },
    {
      id: 2,
      slug: 'pro-developer-plan',
      name: 'Professional Developer Plan',
      description: 'Access all current and upcoming Wordpresso plugins with updater entitlement across up to 5 production websites.',
      price: 79.00,
      term_months: 12,
      website_limit: 5,
      active: true,
      included_plugins: [1, 2, 3, 4],
      included_themes: [1],
      created_at: new Date('2026-01-01').toISOString()
    },
    {
      id: 3,
      slug: 'agency-all-access',
      name: 'Agency All-Access Pass',
      description: 'Uncapped developer license including all plugins, themes, priority support, and 25 website connections.',
      price: 149.00,
      term_months: 12,
      website_limit: 25,
      active: true,
      included_plugins: [1, 2, 3, 4],
      included_themes: [1, 2],
      created_at: new Date('2026-01-01').toISOString()
    }
  ];

  customers: Customer[] = [
    {
      id: 1,
      email: 'demo@example.com',
      name: 'Sarah Connor',
      password_hash: 'password123',
      status: 'active',
      created_at: new Date('2026-01-15').toISOString()
    }
  ];

  admins: Admin[] = [
    {
      id: 1,
      email: 'admin@wordpresso.store',
      password_hash: 'admin123',
      created_at: new Date('2026-01-01').toISOString()
    }
  ];

  websites: Website[] = [
    {
      id: 1,
      customer_id: 1,
      name: 'Sarah Portfolio Studio',
      url: 'https://sarahconnor.design',
      api_key: 'wp_live_79a2b8e34cf89201',
      secret: 'sec_8fa0129bc489e271038290ab',
      status: 'active',
      wp_version: '6.7',
      php_version: '8.2',
      last_check_at: new Date().toISOString(),
      last_check_ip: '192.0.2.45',
      connected_at: new Date('2026-02-01').toISOString()
    }
  ];

  orders: StoreOrder[] = [
    {
      id: 1,
      order_code: 'ORD-9824-WPO',
      customer_id: 1,
      amount: 79.00,
      currency: 'USDT',
      status: 'confirmed',
      tx_hash: '0x4f82901a884810ecba1982bca8190284ab910283c710293847ab091283948192',
      payment_expires_at: new Date('2026-02-02').toISOString(),
      paid_at: new Date('2026-02-01T14:32:00Z').toISOString(),
      created_at: new Date('2026-02-01T14:20:00Z').toISOString(),
      items: [
        {
          id: 1,
          product_id: 2,
          product_name: 'Professional Developer Plan',
          category: 'Plan',
          unit_price: 79.00,
          quantity: 1
        }
      ]
    }
  ];

  logs: UpdateLog[] = [
    {
      id: 1,
      website_id: 1,
      customer_id: 1,
      plugin_id: 1,
      action: 'update_check',
      outcome: 'success',
      message: 'Client checked for updates: Wordpresso Auto Updater is current (v1.2.0)',
      ip: '192.0.2.45',
      context: '{"wp_version":"6.7","php_version":"8.2"}',
      created_at: new Date(Date.now() - 3600000).toISOString()
    },
    {
      id: 2,
      website_id: 1,
      customer_id: 1,
      plugin_id: 2,
      action: 'package_authorized',
      outcome: 'success',
      message: 'Download token generated for WooCommerce Dynamic Pricing Pro v2.4.1',
      ip: '192.0.2.45',
      context: '{"token_hash":"7c89..."}',
      created_at: new Date(Date.now() - 7200000).toISOString()
    }
  ];

  settings: StoreSettings = {
    site_title: 'Wordpresso',
    site_tagline: 'Premium WordPress tools for your next great website.',
    site_description: 'Browse plugins and themes in one catalog, then manage purchases and eligible plugin updates from your account.',
    meta_description: 'Browse WordPress plugins and themes, manage purchases, and connect eligible plugin updates.',
    footer_text: 'WordPress products, account access, and update connections in one place.',
    support_email: 'support@wordpresso.store',
    bsc_wallet: '0x71C94821a8B949b9C89284209Ac9329C71b02842',
    usdt_contract: '0x55d398326f99059fF775485246999027B3197955',
    bsc_rpc: 'https://bsc-dataseed.binance.org/'
  };

  getAllCatalog() {
    const list: Array<{
      id: number;
      source_id: number;
      category: 'Plugin' | 'Theme';
      slug: string;
      name: string;
      description: string;
      price: number;
      active: boolean;
      version: string;
    }> = [];

    for (const p of this.plugins) {
      if (p.active) {
        list.push({
          id: p.id,
          source_id: p.id,
          category: 'Plugin',
          slug: p.slug,
          name: p.name,
          description: p.description,
          price: p.price,
          active: p.active,
          version: p.version
        });
      }
    }

    for (const t of this.themes) {
      if (t.active) {
        list.push({
          id: t.id,
          source_id: t.id,
          category: 'Theme',
          slug: t.slug,
          name: t.name,
          description: t.description,
          price: t.price,
          active: t.active,
          version: t.version
        });
      }
    }

    return list;
  }
}

export const db = new Database();
