import express, { Request, Response, NextFunction } from 'express';
import cookieParser from 'cookie-parser';
import path from 'path';
import { fileURLToPath } from 'url';
import crypto from 'crypto';
import { db } from './src/db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = Number(process.env.PORT) || 3000;
const HOST = process.env.HOST || '0.0.0.0';

// Express setup
app.set('views', path.join(__dirname, 'views'));
app.set('view engine', 'ejs');

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cookieParser('wordpresso-secret-cookie-key'));
app.use('/assets', express.static(path.join(__dirname, 'assets')));

// Helper for cart and session data in signed/unsigned cookies
interface CartItem {
  id: number;
  product_id: number;
  category: 'Plugin' | 'Theme' | 'Plan';
  name: string;
  price: number;
}

function getCart(req: Request): CartItem[] {
  try {
    if (req.cookies.wp_cart) {
      return JSON.parse(req.cookies.wp_cart);
    }
  } catch {
    // fallback
  }
  return [];
}

function setCart(res: Response, cart: CartItem[]) {
  res.cookie('wp_cart', JSON.stringify(cart), { maxAge: 86400000 * 7, httpOnly: true });
}

function getCurrentCustomer(req: Request) {
  const customerId = req.cookies.wp_customer_id;
  if (customerId) {
    return db.customers.find((c) => c.id === Number(customerId)) || null;
  }
  return null;
}

function isAdminLoggedIn(req: Request): boolean {
  return req.cookies.wp_admin_logged_in === '1';
}

// Global template variables middleware
app.use((req: Request, res: Response, next: NextFunction) => {
  const customer = getCurrentCustomer(req);
  const cart = getCart(req);
  res.locals.siteName = db.settings.site_title;
  res.locals.storeTagline = db.settings.site_tagline;
  res.locals.storeDescription = db.settings.site_description;
  res.locals.metaDescription = db.settings.meta_description;
  res.locals.footerText = db.settings.footer_text;
  res.locals.supportEmail = db.settings.support_email;
  res.locals.customer = customer;
  res.locals.cartCount = cart.length;
  next();
});

// ==========================================
// STOREFRONT ROUTES
// ==========================================

// Homepage
app.get('/', (req: Request, res: Response) => {
  const catalog = db.getAllCatalog();
  const featured = catalog[0] || null;
  const themeCount = db.themes.filter((t) => t.active).length;
  const pluginCount = db.plugins.filter((p) => p.active).length;

  res.render('store/home', {
    title: 'WordPress Store & Auto-Update Engine',
    featured,
    themeCount,
    pluginCount,
  });
});

// Store Catalog
app.get(['/store', '/plugins'], (req: Request, res: Response) => {
  const category = (req.query.category as string) || '';
  let products = db.getAllCatalog();

  if (category) {
    products = products.filter((p) => p.category.toLowerCase() === category.toLowerCase());
  }

  res.render('store/catalog', {
    title: 'Store Catalog',
    products,
    selectedCategory: category,
  });
});

// Single Product detail by type & id
app.get('/product/:type/:id', (req: Request, res: Response) => {
  const { type, id } = req.params;
  const numId = Number(id);

  let product: any = null;
  if (type.toLowerCase() === 'plugin') {
    product = db.plugins.find((p) => p.id === numId);
  } else if (type.toLowerCase() === 'theme') {
    product = db.themes.find((t) => t.id === numId);
  }

  if (!product) {
    return res.status(404).render('errors/404', { title: 'Product Not Found' });
  }

  res.render('store/product', {
    title: product.name,
    product,
  });
});

// Single Plugin by slug
app.get('/plugins/:slug', (req: Request, res: Response) => {
  const { slug } = req.params;
  const plugin = db.plugins.find((p) => p.slug === slug);

  if (!plugin) {
    return res.status(404).render('errors/404', { title: 'Plugin Not Found' });
  }

  res.render('store/product', {
    title: plugin.name,
    product: plugin,
  });
});

// Pricing & Plans
app.get(['/plans', '/pricing'], (req: Request, res: Response) => {
  const plans = db.plans.filter((p) => p.active);
  res.render('store/pricing', {
    title: 'Plans & Membership',
    plans,
  });
});

// Shopping Cart
app.get('/cart', (req: Request, res: Response) => {
  const cart = getCart(req);
  const total = cart.reduce((sum, item) => sum + Number(item.price), 0);

  res.render('store/cart', {
    title: 'Shopping Cart',
    cart,
    total,
  });
});

// Add to Cart
app.post('/cart/add', (req: Request, res: Response) => {
  const { product_id, category } = req.body;
  const id = Number(product_id);
  const cart = getCart(req);

  let itemToAdd: CartItem | null = null;
  if (category === 'Plugin') {
    const p = db.plugins.find((x) => x.id === id);
    if (p) itemToAdd = { id: Date.now(), product_id: p.id, category: 'Plugin', name: p.name, price: p.price };
  } else if (category === 'Theme') {
    const t = db.themes.find((x) => x.id === id);
    if (t) itemToAdd = { id: Date.now(), product_id: t.id, category: 'Theme', name: t.name, price: t.price };
  } else if (category === 'Plan') {
    const pl = db.plans.find((x) => x.id === id);
    if (pl) itemToAdd = { id: Date.now(), product_id: pl.id, category: 'Plan', name: pl.name, price: pl.price };
  }

  if (itemToAdd) {
    cart.push(itemToAdd);
    setCart(res, cart);
  }

  res.redirect('/cart');
});

// Remove from Cart
app.post('/cart/remove', (req: Request, res: Response) => {
  const { index } = req.body;
  const idx = Number(index);
  const cart = getCart(req);

  if (!isNaN(idx) && idx >= 0 && idx < cart.length) {
    cart.splice(idx, 1);
    setCart(res, cart);
  }

  res.redirect('/cart');
});

// Checkout - create order
app.post('/checkout', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) {
    return res.redirect('/login');
  }

  const cart = getCart(req);
  if (cart.length === 0) {
    return res.redirect('/cart');
  }

  const amount = cart.reduce((sum, it) => sum + Number(it.price), 0);
  const orderCode = 'ORD-' + Math.floor(1000 + Math.random() * 9000) + '-WPO';

  const order = {
    id: db.orders.length + 1,
    order_code: orderCode,
    customer_id: customer.id,
    amount,
    currency: 'USDT',
    status: 'pending' as const,
    tx_hash: null,
    payment_expires_at: new Date(Date.now() + 86400000).toISOString(),
    paid_at: null,
    created_at: new Date().toISOString(),
    items: cart.map((c, i) => ({
      id: i + 1,
      product_id: c.product_id,
      product_name: c.name,
      category: c.category,
      unit_price: c.price,
      quantity: 1,
    })),
  };

  db.orders.push(order);
  setCart(res, []); // clear cart

  res.redirect(`/checkout/${orderCode}`);
});

// Checkout payment page
app.get('/checkout/:code', (req: Request, res: Response) => {
  const { code } = req.params;
  const order = db.orders.find((o) => o.order_code === code);

  if (!order) {
    return res.status(404).render('errors/404', { title: 'Order Not Found' });
  }

  res.render('store/checkout', {
    title: `Payment for ${order.order_code}`,
    order,
    wallet: db.settings.bsc_wallet,
    usdtContract: db.settings.usdt_contract,
  });
});

// Verify payment
app.post('/checkout/:code/verify', (req: Request, res: Response) => {
  const { code } = req.params;
  const { tx_hash } = req.body;
  const order = db.orders.find((o) => o.order_code === code);

  if (order && tx_hash) {
    order.tx_hash = String(tx_hash).trim();
    order.status = 'confirmed';
    order.paid_at = new Date().toISOString();

    db.logs.push({
      id: db.logs.length + 1,
      website_id: null,
      customer_id: order.customer_id,
      plugin_id: null,
      action: 'order_payment_verified',
      outcome: 'success',
      message: `Order ${order.order_code} confirmed with tx ${order.tx_hash.substring(0, 16)}...`,
      ip: req.ip || '127.0.0.1',
      context: JSON.stringify({ amount: order.amount, currency: 'USDT' }),
      created_at: new Date().toISOString(),
    });
  }

  res.redirect(`/checkout/${code}`);
});

// Customer Authentication
app.get('/login', (req: Request, res: Response) => {
  if (getCurrentCustomer(req)) return res.redirect('/account');
  res.render('store/login', { title: 'Sign In', error: null });
});

app.post('/login', (req: Request, res: Response) => {
  const { email, password } = req.body;
  const customer = db.customers.find((c) => c.email.toLowerCase() === String(email).trim().toLowerCase());

  if (customer && customer.password_hash === password) {
    res.cookie('wp_customer_id', customer.id.toString(), { maxAge: 86400000 * 7, httpOnly: true });
    return res.redirect('/account');
  }

  res.render('store/login', { title: 'Sign In', error: 'Invalid email or password credentials.' });
});

app.get('/register', (req: Request, res: Response) => {
  if (getCurrentCustomer(req)) return res.redirect('/account');
  res.render('store/register', { title: 'Create Account', error: null });
});

app.post('/register', (req: Request, res: Response) => {
  const { name, email, password } = req.body;
  const existing = db.customers.find((c) => c.email.toLowerCase() === String(email).trim().toLowerCase());

  if (existing) {
    return res.render('store/register', { title: 'Create Account', error: 'An account with this email already exists.' });
  }

  const newCustomer = {
    id: db.customers.length + 1,
    email: String(email).trim(),
    name: String(name).trim(),
    password_hash: String(password),
    status: 'active' as const,
    created_at: new Date().toISOString(),
  };

  db.customers.push(newCustomer);
  res.cookie('wp_customer_id', newCustomer.id.toString(), { maxAge: 86400000 * 7, httpOnly: true });
  res.redirect('/account');
});

app.post('/logout', (req: Request, res: Response) => {
  res.clearCookie('wp_customer_id');
  res.redirect('/');
});

// Customer Account Dashboard
app.get('/account', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) return res.redirect('/login');

  const customerWebsites = db.websites.filter((w) => w.customer_id === customer.id);
  const customerOrders = db.orders.filter((o) => o.customer_id === customer.id);

  res.render('store/account/dashboard', {
    title: 'My Account',
    customer,
    websites: customerWebsites,
    orders: customerOrders,
  });
});

app.get('/account/websites', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) return res.redirect('/login');

  const customerWebsites = db.websites.filter((w) => w.customer_id === customer.id);
  res.render('store/account/websites', {
    title: 'Connected Websites',
    websites: customerWebsites,
  });
});

app.post('/account/websites', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) return res.redirect('/login');

  const { name, url } = req.body;
  const apiKey = 'wp_live_' + crypto.randomBytes(8).toString('hex');
  const secret = 'sec_' + crypto.randomBytes(12).toString('hex');

  const site = {
    id: db.websites.length + 1,
    customer_id: customer.id,
    name: String(name).trim(),
    url: String(url).trim(),
    api_key: apiKey,
    secret,
    status: 'active' as const,
    wp_version: '6.7',
    php_version: '8.2',
    last_check_at: null,
    last_check_ip: null,
    connected_at: new Date().toISOString(),
  };

  db.websites.push(site);
  res.redirect('/account/websites');
});

app.post('/account/websites/:id/delete', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) return res.redirect('/login');

  const siteId = Number(req.params.id);
  const idx = db.websites.findIndex((w) => w.id === siteId && w.customer_id === customer.id);
  if (idx !== -1) {
    db.websites.splice(idx, 1);
  }

  res.redirect('/account/websites');
});

app.get('/account/orders', (req: Request, res: Response) => {
  const customer = getCurrentCustomer(req);
  if (!customer) return res.redirect('/login');

  const customerOrders = db.orders.filter((o) => o.customer_id === customer.id);
  res.render('store/account/orders', {
    title: 'Orders',
    orders: customerOrders,
  });
});

// Legal pages
app.get('/privacy', (req: Request, res: Response) => {
  res.render('store/legal', { title: 'Privacy Policy' });
});

app.get('/terms', (req: Request, res: Response) => {
  res.render('store/legal', { title: 'Terms of Use' });
});

// ==========================================
// ADMIN DASHBOARD ROUTES
// ==========================================

// Admin auth check middleware
function requireAdmin(req: Request, res: Response, next: NextFunction) {
  if (!isAdminLoggedIn(req)) {
    return res.redirect('/admin/login');
  }
  next();
}

app.get('/admin/login', (req: Request, res: Response) => {
  if (isAdminLoggedIn(req)) return res.redirect('/admin');
  res.render('admin/login', { error: null });
});

app.post('/admin/login', (req: Request, res: Response) => {
  const { email, password } = req.body;
  const admin = db.admins.find((a) => a.email.toLowerCase() === String(email).trim().toLowerCase());

  if (admin && admin.password_hash === password) {
    res.cookie('wp_admin_logged_in', '1', { maxAge: 86400000, httpOnly: true });
    return res.redirect('/admin');
  }

  res.render('admin/login', { error: 'Invalid admin credentials.' });
});

app.post('/admin/logout', (req: Request, res: Response) => {
  res.clearCookie('wp_admin_logged_in');
  res.redirect('/admin/login');
});

app.get('/admin', requireAdmin, (req: Request, res: Response) => {
  const revenue = db.orders
    .filter((o) => o.status === 'confirmed')
    .reduce((sum, o) => sum + Number(o.amount), 0);

  const stats = {
    revenue,
    productCount: db.plugins.length + db.themes.length,
    pluginCount: db.plugins.length,
    themeCount: db.themes.length,
    customerCount: db.customers.length,
    websiteCount: db.websites.length,
    pendingOrders: db.orders.filter((o) => o.status === 'pending').length,
  };

  const recentLogs = db.logs.slice(-5).reverse();

  res.render('admin/dashboard', {
    title: 'Dashboard',
    activeTab: 'dashboard',
    stats,
    recentLogs,
  });
});

app.get('/admin/products', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/products', {
    title: 'Products',
    activeTab: 'products',
    plugins: db.plugins,
    themes: db.themes,
  });
});

app.post('/admin/products/:type/:id/toggle', requireAdmin, (req: Request, res: Response) => {
  const { type, id } = req.params;
  const numId = Number(id);

  if (type === 'plugin') {
    const p = db.plugins.find((x) => x.id === numId);
    if (p) p.active = !p.active;
  } else if (type === 'theme') {
    const t = db.themes.find((x) => x.id === numId);
    if (t) t.active = !t.active;
  }

  res.redirect('/admin/products');
});

app.get('/admin/orders', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/orders', {
    title: 'Orders',
    activeTab: 'orders',
    orders: [...db.orders].reverse(),
  });
});

app.post('/admin/orders/:id/confirm', requireAdmin, (req: Request, res: Response) => {
  const o = db.orders.find((x) => x.id === Number(req.params.id));
  if (o) {
    o.status = 'confirmed';
    o.paid_at = new Date().toISOString();
  }
  res.redirect('/admin/orders');
});

app.post('/admin/orders/:id/reject', requireAdmin, (req: Request, res: Response) => {
  const o = db.orders.find((x) => x.id === Number(req.params.id));
  if (o) {
    o.status = 'rejected';
  }
  res.redirect('/admin/orders');
});

app.get('/admin/customers', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/customers', {
    title: 'Customers',
    activeTab: 'customers',
    customers: db.customers,
  });
});

app.post('/admin/customers/:id/suspend', requireAdmin, (req: Request, res: Response) => {
  const c = db.customers.find((x) => x.id === Number(req.params.id));
  if (c) {
    c.status = c.status === 'active' ? 'suspended' : 'active';
  }
  res.redirect('/admin/customers');
});

app.get('/admin/websites', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/websites', {
    title: 'Websites',
    activeTab: 'websites',
    websites: db.websites,
  });
});

app.post('/admin/websites/:id/revoke', requireAdmin, (req: Request, res: Response) => {
  const w = db.websites.find((x) => x.id === Number(req.params.id));
  if (w) {
    w.status = 'revoked';
  }
  res.redirect('/admin/websites');
});

app.get('/admin/logs', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/logs', {
    title: 'Audit Logs',
    activeTab: 'logs',
    logs: [...db.logs].reverse(),
  });
});

app.post('/admin/logs/prune', requireAdmin, (req: Request, res: Response) => {
  db.logs = db.logs.slice(-10);
  res.redirect('/admin/logs');
});

app.get('/admin/store-settings', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/settings', {
    title: 'Storefront Settings',
    activeTab: 'settings',
    settings: db.settings,
  });
});

app.post('/admin/store-settings', requireAdmin, (req: Request, res: Response) => {
  const { site_title, site_tagline, site_description, footer_text, support_email } = req.body;
  if (site_title) db.settings.site_title = site_title;
  if (site_tagline) db.settings.site_tagline = site_tagline;
  if (site_description) db.settings.site_description = site_description;
  if (footer_text) db.settings.footer_text = footer_text;
  if (support_email) db.settings.support_email = support_email;

  res.redirect('/admin/store-settings');
});

app.get('/admin/payments', requireAdmin, (req: Request, res: Response) => {
  res.render('admin/payments', {
    title: 'Payment Gateway',
    activeTab: 'payments',
    settings: db.settings,
  });
});

app.post('/admin/payments', requireAdmin, (req: Request, res: Response) => {
  const { bsc_wallet, usdt_contract, bsc_rpc } = req.body;
  if (bsc_wallet) db.settings.bsc_wallet = bsc_wallet;
  if (usdt_contract) db.settings.usdt_contract = usdt_contract;
  if (bsc_rpc) db.settings.bsc_rpc = bsc_rpc;

  res.redirect('/admin/payments');
});

// ==========================================
// WORDPRESS AUTO UPDATER API v1
// ==========================================

// Register website from WordPress client
app.post('/api/v1/register', (req: Request, res: Response) => {
  const { url, name } = req.body;
  const apiKey = 'wp_live_' + crypto.randomBytes(8).toString('hex');
  const secret = 'sec_' + crypto.randomBytes(12).toString('hex');

  const website = {
    id: db.websites.length + 1,
    customer_id: 1, // Default customer
    name: name || 'Remote WordPress Site',
    url: url || 'https://example.com',
    api_key: apiKey,
    secret,
    status: 'active' as const,
    wp_version: req.body.wp_version || '6.7',
    php_version: req.body.php_version || '8.2',
    last_check_at: new Date().toISOString(),
    last_check_ip: req.ip || '127.0.0.1',
    connected_at: new Date().toISOString(),
  };

  db.websites.push(website);

  res.json({
    success: true,
    data: {
      api_key: apiKey,
      secret,
    },
  });
});

// Check updates
app.post('/api/v1/updates', (req: Request, res: Response) => {
  const plugins = db.plugins.filter((p) => p.active && p.enabled);
  const updates: Record<string, any> = {};

  for (const p of plugins) {
    updates[p.basename] = {
      id: p.id,
      slug: p.slug,
      plugin: p.basename,
      new_version: p.version,
      url: `${req.protocol}://${req.get('host')}/plugins/${p.slug}`,
      package: `${req.protocol}://${req.get('host')}/api/v1/package/${crypto.randomBytes(16).toString('hex')}`,
      tested: p.tested_wp_version,
      requires_php: p.min_php_version,
      requires: p.min_wp_version,
    };
  }

  db.logs.push({
    id: db.logs.length + 1,
    website_id: 1,
    customer_id: 1,
    plugin_id: null,
    action: 'api_update_check',
    outcome: 'success',
    message: `Client checked updates, returned ${plugins.length} active plugins`,
    ip: req.ip || '127.0.0.1',
    context: JSON.stringify(req.body || {}),
    created_at: new Date().toISOString(),
  });

  res.json({
    success: true,
    plugins: updates,
  });
});

// Package token authorize
app.post('/api/v1/package', (req: Request, res: Response) => {
  const token = crypto.randomBytes(24).toString('hex');
  res.json({
    success: true,
    token,
    download_url: `${req.protocol}://${req.get('host')}/api/v1/package/${token}`,
    expires_in: 600,
  });
});

// Package download (returns mock ZIP payload with correct zip headers)
app.get('/api/v1/package/:token', (req: Request, res: Response) => {
  res.setHeader('Content-Disposition', 'attachment; filename="wordpresso-package.zip"');
  res.setHeader('Content-Type', 'application/zip');
  // Minimal ZIP header magic bytes (PK 05 06 empty zip)
  const emptyZip = Buffer.from([0x50, 0x4b, 0x05, 0x06, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
  res.send(emptyZip);
});

// Update status confirmation
app.post('/api/v1/status', (req: Request, res: Response) => {
  res.json({ success: true, recorded: true });
});

// 404 handler
app.use((req: Request, res: Response) => {
  if (req.path.startsWith('/api/')) {
    return res.status(404).json({ error: 'Endpoint not found' });
  }
  res.status(404).render('errors/404', { title: 'Not Found' });
});

// 500 handler
app.use((err: any, req: Request, res: Response, next: NextFunction) => {
  console.error('[wordpresso]', err);
  if (req.path.startsWith('/api/')) {
    return res.status(500).json({ error: 'Internal server error' });
  }
  res.status(500).render('errors/500', { title: 'Error', message: err.message });
});

// Server start
app.listen(PORT, HOST, () => {
  console.log(`Wordpresso Store Server running on http://${HOST}:${PORT}`);
});
