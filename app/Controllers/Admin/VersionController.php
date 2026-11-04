<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Config;
use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Models\Plugin;
use Wordpresso\Models\PluginVersion;
use Wordpresso\Models\UpdateLog;
use Wordpresso\Services\PackageStorage;
use Wordpresso\Services\PackageValidator;

/**
 * Upload, validate, publish, withdraw and roll back plugin versions.
 * Uploaded packages enter as drafts and must be explicitly published.
 */
final class VersionController extends BaseController
{
    public function upload(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);

        $plugin = Plugin::find((int) $params['id']);
        if ($plugin === null) {
            return Response::error('Plugin not found.', 404);
        }

        $file = $request->post['package'] ?? ($_FILES['package'] ?? null);
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', $this->uploadErrorMessage($file));
            return Response::redirect('/admin/plugins/' . $plugin['id']);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            Session::flash('error', 'Invalid upload.');
            return Response::redirect('/admin/plugins/' . $plugin['id']);
        }

        $version = trim((string) $request->post('version', ''));
        if ($version === '' || !preg_match('/^\d+(\.\d+)*([.-][A-Za-z0-9]+)?$/', $version)) {
            Session::flash('error', 'Please enter a valid version number, e.g. 1.2.3.');
            return Response::redirect('/admin/plugins/' . $plugin['id']);
        }

        if (PluginVersion::findByVersion((int) $plugin['id'], $version) !== null) {
            Session::flash('error', "Version {$version} already exists for this plugin.");
            return Response::redirect('/admin/plugins/' . $plugin['id']);
        }

        $validation = PackageValidator::validate($file['tmp_name'], $plugin['basename'], $version);
        if (!$validation['ok']) {
            Session::flash('error', 'Package validation failed: ' . implode(' ', $validation['errors']));
            return Response::redirect('/admin/plugins/' . $plugin['id']);
        }

        $relative = PackageStorage::store($file['tmp_name'], $plugin['slug'], $version, $validation['sha256']);

        $versionId = PluginVersion::create([
            'plugin_id' => (int) $plugin['id'],
            'version' => $version,
            'package_path' => $relative,
            'package_sha256' => $validation['sha256'],
            'package_size' => $validation['size'],
            'changelog' => trim((string) $request->post('changelog', '')),
            'status' => 'draft',
            'min_wp_version' => trim((string) $request->post('min_wp_version', '')) ?: $plugin['min_wp_version'],
            'min_php_version' => trim((string) $request->post('min_php_version', '')) ?: $plugin['min_php_version'],
            'tested_wp_version' => trim((string) $request->post('tested_wp_version', '')) ?: $plugin['tested_wp_version'],
        ]);

        UpdateLog::record('version_upload', 'success', "Draft version {$version} uploaded.", null, null, (int) $plugin['id'], $request->ip, ['version_id' => $versionId]);
        Session::flash('success', "Version {$version} uploaded and validated. Review and publish it.");
        return Response::redirect('/admin/plugins/' . $plugin['id']);
    }

    public function publish(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = PluginVersion::find((int) $params['id']);
        if ($version === null) {
            return Response::error('Version not found.', 404);
        }

        // Re-verify integrity right before publishing.
        $check = PackageStorage::absolutePath($version['package_path'], $version['package_sha256']);
        if ($check === null) {
            Session::flash('error', 'Package integrity check failed — the stored ZIP is missing or corrupt. Re-upload it.');
            return Response::redirect('/admin/plugins/' . $version['plugin_id']);
        }

        PluginVersion::publish((int) $version['id']);
        UpdateLog::record('version_publish', 'success', "Version {$version['version']} published.", null, null, (int) $version['plugin_id'], $request->ip);
        Session::flash('success', "Version {$version['version']} is now live.");
        return Response::redirect('/admin/plugins/' . $version['plugin_id']);
    }

    public function withdraw(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = PluginVersion::find((int) $params['id']);
        if ($version === null) {
            return Response::error('Version not found.', 404);
        }
        PluginVersion::withdraw((int) $version['id']);
        UpdateLog::record('version_withdraw', 'success', "Version {$version['version']} withdrawn.", null, null, (int) $version['plugin_id'], $request->ip);
        Session::flash('success', "Version {$version['version']} withdrawn. Customers will not receive it.");
        return Response::redirect('/admin/plugins/' . $version['plugin_id']);
    }

    public function rollback(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = PluginVersion::find((int) $params['id']);
        if ($version === null) {
            return Response::error('Version not found.', 404);
        }
        if ($version['status'] === 'withdrawn') {
            Session::flash('error', 'Cannot roll back to a withdrawn version. Publish it first.');
            return Response::redirect('/admin/plugins/' . $version['plugin_id']);
        }
        PluginVersion::publish((int) $version['id']);
        UpdateLog::record('version_rollback', 'success', "Rolled back to version {$version['version']}.", null, null, (int) $version['plugin_id'], $request->ip);
        Session::flash('success', "Now serving version {$version['version']} again.");
        return Response::redirect('/admin/plugins/' . $version['plugin_id']);
    }

    public function saveChangelog(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = PluginVersion::find((int) $params['id']);
        if ($version === null) {
            return Response::error('Version not found.', 404);
        }
        PluginVersion::saveChangelog((int) $version['id'], trim((string) $request->post('changelog', '')));
        Session::flash('success', 'Changelog saved.');
        return Response::redirect('/admin/plugins/' . $version['plugin_id']);
    }

    public function delete(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = PluginVersion::find((int) $params['id']);
        if ($version === null) return Response::error('Version not found.', 404);
        if ((int) $version['is_current'] === 1) {
            Session::flash('error', 'Publish another release before deleting the current release.');
            return Response::redirect('/admin/plugins/' . $version['plugin_id']);
        }
        PluginVersion::delete((int) $version['id']);
        Session::flash('success', "Version {$version['version']} deleted.");
        return Response::redirect('/admin/plugins/' . $version['plugin_id']);
    }

    private function uploadErrorMessage(?array $file): string
    {
        return match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted — try again.',
            UPLOAD_ERR_NO_FILE => 'Please choose a ZIP package to upload.',
            default => 'Upload failed (code ' . ($file['error'] ?? 'unknown') . ').',
        };
    }
}
