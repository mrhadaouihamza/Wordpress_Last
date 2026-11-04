<?php

declare(strict_types=1);

namespace Wordpresso\Controllers\Admin;

use Wordpresso\Core\Csrf;
use Wordpresso\Core\Request;
use Wordpresso\Core\Response;
use Wordpresso\Core\Session;
use Wordpresso\Models\ThemeVersion;
use Wordpresso\Services\PackageStorage;
use Wordpresso\Services\ThemePackageValidator;

final class ThemeVersionController extends BaseController
{
    public function upload(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $themeId = (int) $params['id'];
        $theme = \Wordpresso\Core\Db::fetch('SELECT * FROM themes WHERE id = ?', [$themeId]);
        if ($theme === null) {
            return Response::error('Theme not found.', 404);
        }
        $file = $request->post['package'] ?? ($_FILES['package'] ?? null);
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            Session::flash('error', 'Please upload a valid theme ZIP package.');
            return Response::redirect('/admin/products/theme/' . $themeId);
        }
        $version = trim((string) $request->post('version', ''));
        if ($version === '' || !preg_match('/^\d+(\.\d+)*([.-][A-Za-z0-9]+)?$/', $version)) {
            Session::flash('error', 'Please enter a valid version number, e.g. 1.2.3.');
            return Response::redirect('/admin/products/theme/' . $themeId);
        }
        if (ThemeVersion::findByVersion($themeId, $version) !== null) {
            Session::flash('error', "Version {$version} already exists for this theme.");
            return Response::redirect('/admin/products/theme/' . $themeId);
        }
        $check = ThemePackageValidator::validate($file['tmp_name'], (string) $theme['slug'], $version);
        if (!$check['ok']) {
            Session::flash('error', 'Package validation failed: ' . implode(' ', $check['errors']));
            return Response::redirect('/admin/products/theme/' . $themeId);
        }
        $path = PackageStorage::store($file['tmp_name'], (string) $theme['slug'], $version, $check['sha256']);
        ThemeVersion::create([
            'theme_id' => $themeId,
            'version' => $version,
            'package_path' => $path,
            'package_sha256' => $check['sha256'],
            'package_size' => $check['size'],
            'changelog' => trim((string) $request->post('changelog', '')),
            'min_wp_version' => trim((string) $request->post('min_wp_version', '')),
            'min_php_version' => trim((string) $request->post('min_php_version', '')),
            'tested_wp_version' => trim((string) $request->post('tested_wp_version', '')),
        ]);
        Session::flash('success', "Theme version {$version} uploaded and validated. Review and publish it.");
        return Response::redirect('/admin/products/theme/' . $themeId);
    }

    public function publish(Request $request, array $params): Response
    {
        return $this->changeStatus($request, $params, 'publish');
    }

    public function withdraw(Request $request, array $params): Response
    {
        return $this->changeStatus($request, $params, 'withdraw');
    }

    public function delete(Request $request, array $params): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = ThemeVersion::find((int) $params['id']);
        if ($version === null) return Response::error('Theme version not found.', 404);
        if ((int) $version['is_current'] === 1) {
            Session::flash('error', 'Publish another release before deleting the current release.');
        } else {
            ThemeVersion::delete((int) $version['id']);
            Session::flash('success', "Theme version {$version['version']} deleted.");
        }
        return Response::redirect('/admin/products/theme/' . (int) $version['theme_id']);
    }

    private function changeStatus(Request $request, array $params, string $action): Response
    {
        $this->requireAdmin();
        Csrf::requireValid($request->post('_csrf') ?? null);
        $version = ThemeVersion::find((int) $params['id']);
        if ($version === null) return Response::error('Theme version not found.', 404);
        if ($action === 'publish') {
            $check = PackageStorage::absolutePath((string) $version['package_path'], (string) $version['package_sha256']);
            if ($check === null) {
                Session::flash('error', 'Package integrity check failed. Re-upload this release.');
            } else {
                ThemeVersion::publish((int) $version['id']);
                Session::flash('success', "Theme version {$version['version']} is now live.");
            }
        } else {
            ThemeVersion::withdraw((int) $version['id']);
            Session::flash('success', "Theme version {$version['version']} withdrawn.");
        }
        return Response::redirect('/admin/products/theme/' . (int) $version['theme_id']);
    }
}
