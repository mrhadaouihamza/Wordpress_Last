<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Storefront;

use Wordpresso\Core\Config;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\View;
use Wordpresso\Models\StoreSettings;

/**
 * Public legal pages: Privacy policy (/privacy) and Terms of use (/terms).
 *
 * The content is written by the store operator in Admin → Store settings and
 * stored as HTML; until something is saved there, a sensible default text is
 * published so the links are never empty.
 */
final class LegalController
{
    public function privacy(Request $request): Response
    {
        return $this->render('Privacy policy', 'privacy_policy', self::defaultPrivacy());
    }

    public function terms(Request $request): Response
    {
        return $this->render('Terms of use', 'terms_of_use', self::defaultTerms());
    }

    private function render(string $heading, string $settingKey, string $fallback): Response
    {
        $content = trim((string) StoreSettings::get($settingKey, ''));
        return Response::html(View::page('store', 'store/legal', [
            'siteName' => Config::get('site_name'),
            'customer' => HomeController::currentCustomer(),
            'title' => $heading,
            'heading' => $heading,
            'content' => $content !== '' ? $content : $fallback,
        ]));
    }

    private static function defaultPrivacy(): string
    {
        return <<<'HTML'
<p>This policy explains how your information is handled when you create an account, purchase products, and connect a WordPress site to this store. Replace this text in the admin panel with the policy that matches your business.</p>
<h2>What is collected</h2>
<ul>
<li><strong>Account details</strong> — your email address and name, used to create your account and send purchase information.</li>
<li><strong>Orders and payments</strong> — the products you buy, amounts, currency, and the blockchain transaction reference used to verify your payment.</li>
<li><strong>Connected websites</strong> — the site URL, name, and the WordPress/PHP version information reported by the Wordpresso Auto Updater plugin, together with update-check and download activity used to deliver your updates.</li>
</ul>
<h2>How it is used</h2>
<p>Your information is used to deliver purchases, grant product access, answer support requests, keep the update service reliable, and prevent abuse of the store. Blockchain transactions are public data and are only read to confirm that a payment reached the store's receiving address.</p>
<h2>Sharing and retention</h2>
<p>Personal data is not sold. It is retained while your account exists, and audit records without personal identifiers may be kept afterwards for security and operational purposes. You can delete your account at any time from <a href="/account/settings">Account settings</a>; this permanently removes your account details, website connections, subscriptions, and orders.</p>
<h2>Contact</h2>
<p>Questions about this policy can be sent to the support email shown in the site footer.</p>
HTML;
    }

    private static function defaultTerms(): string
    {
        return <<<'HTML'
<p>These terms govern your use of this store and its services. Replace this text in the admin panel with the terms that match your business.</p>
<h2>Accounts</h2>
<p>You need an account to purchase and connect websites. You are responsible for keeping your password confidential and for the activity that happens under your account.</p>
<h2>Purchases and access</h2>
<p>Prices are shown per product or plan. Payment is made in USDT on BNB Smart Chain and access begins after the transaction is verified on the network. Access periods do not renew automatically — buy another term to extend access. Orders can be reviewed or rejected by the store when a payment cannot be verified.</p>
<h2>Licenses and redistribution</h2>
<p>Purchased products are licensed to your account and its connected websites. Only upload, redistribute, or share plugin packages that you are legally entitled to redistribute; this store contains no mechanism for bypassing third-party licensing or protection systems.</p>
<h2>Connected websites</h2>
<p>API credentials issued for the Wordpresso Auto Updater plugin are personal to your account. Removing a website permanently deletes its credentials. The store may revoke access that is used abusively or in violation of these terms.</p>
<h2>Changes</h2>
<p>The store operator may update these terms; the current version is always published on this page.</p>
HTML;
    }
}
