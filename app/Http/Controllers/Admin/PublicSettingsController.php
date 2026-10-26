<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePublicSettingsRequest;
use App\Services\ActivityLogger;
use App\Services\PublicFieldSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pengaturan field yang tampil di direktori & profil publik (FR-191).
 *
 * Allowlist Q-008 adalah batas maksimum. Admin hanya memilih subset; server
 * menolak nilai di luar allowlist dan service menyaring ulang sebelum disimpan.
 */
class PublicSettingsController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function edit(PublicFieldSettings $publicSettings): View
    {
        Gate::authorize('manage-public-settings');

        return view('admin.pengaturan.publik', [
            'settings' => $publicSettings->current(),
            'allowedFields' => $publicSettings->allowedFields(),
            'fieldLabels' => $publicSettings->labels(),
        ]);
    }

    public function update(UpdatePublicSettingsRequest $request, PublicFieldSettings $publicSettings): RedirectResponse
    {
        Gate::authorize('manage-public-settings');

        $saved = $publicSettings->save(
            (array) $request->validated('fields'),
            $request->boolean('show_region'),
            $request->boolean('contact_email'),
            $request->boolean('contact_telephone'),
        );

        $this->logger->log(
            'update_public_settings',
            null,
            $request->user(),
            [
                'fields' => $saved['fields'],
                'show_region' => $saved['show_region'],
                'contact' => $saved['contact'],
            ],
            'Pengaturan field publik diperbarui.',
        );

        return back()->with('status', 'Pengaturan field publik berhasil disimpan.');
    }
}
