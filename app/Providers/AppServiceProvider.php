<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\GovernanceRecord;
use App\Models\HealthAssessment;
use App\Models\MemberHistory;
use App\Models\Submission;
use App\Policies\ActivityLogPolicy;
use App\Policies\CooperativeAssetPolicy;
use App\Policies\CooperativeLicensePolicy;
use App\Policies\CooperativePolicy;
use App\Policies\DocumentPolicy;
use App\Policies\GovernanceRecordPolicy;
use App\Policies\HealthAssessmentPolicy;
use App\Policies\MemberHistoryPolicy;
use App\Policies\SubmissionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Otorisasi data induk koperasi (termasuk isolasi antar koperasi / anti-IDOR).
        Gate::policy(Cooperative::class, CooperativePolicy::class);

        // Otorisasi perizinan & dokumen (F3).
        Gate::policy(CooperativeLicense::class, CooperativeLicensePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);

        // Otorisasi data periodik F4 (kesehatan, tata kelola, aset, riwayat anggota).
        Gate::policy(HealthAssessment::class, HealthAssessmentPolicy::class);
        Gate::policy(GovernanceRecord::class, GovernanceRecordPolicy::class);
        Gate::policy(CooperativeAsset::class, CooperativeAssetPolicy::class);
        Gate::policy(MemberHistory::class, MemberHistoryPolicy::class);

        // Otorisasi workflow pengajuan & audit trail F5.
        Gate::policy(Submission::class, SubmissionPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
    }
}
