<?php

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Domain\Users\UserRole;
use App\Filament\Resources\RecommendationRuns\Pages\ListRecommendationRuns;
use App\Filament\Resources\RecommendationRuns\Pages\ViewRecommendationRun;
use App\Jobs\ProcessRecommendationRun;
use App\Models\RecommendationRun;
use App\Models\User;
use App\Services\MachineLearning\ModelArtifactLoader;
use App\Services\Recommendation\RecommendationRunService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function recommendationActionAdmin(): User
{
    return User::query()->create([
        'name' => 'Admin Rekomendasi',
        'email' => 'recommendation-action@example.test',
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);
}

it('queues a recommendation run from the Filament header action', function () {
    Queue::fake();
    $admin = recommendationActionAdmin();
    $this->actingAs($admin);

    Livewire::test(ListRecommendationRuns::class)
        ->callAction('proses_rekomendasi')
        ->assertHasNoActionErrors();

    $run = RecommendationRun::query()->sole();

    expect($run->status)->toBe(RecommendationRunStatus::Pending)
        ->and($run->triggered_by)->toBe($admin->id);
    Queue::assertPushed(ProcessRecommendationRun::class);
});

it('creates a traceable retry for a failed run from its Filament detail page', function () {
    Queue::fake();
    $admin = recommendationActionAdmin();
    $this->actingAs($admin);
    $failed = app(RecommendationRunService::class)->start($admin, queue: true);
    $failed->forceFill(['status' => RecommendationRunStatus::Failed])->save();

    Livewire::test(ViewRecommendationRun::class, ['record' => $failed->getRouteKey()])
        ->assertActionVisible('retry')
        ->callAction('retry')
        ->assertHasNoActionErrors();

    $retry = RecommendationRun::query()->where('retry_of_id', $failed->id)->sole();

    expect($retry->status)->toBe(RecommendationRunStatus::Pending);
    Queue::assertPushed(ProcessRecommendationRun::class, 2);
});

it('disables starting a run when the local model artifact cannot be validated', function () {
    $this->actingAs(recommendationActionAdmin());
    config()->set('ml.artifact_path', base_path('resources/ml/missing-artifact.json'));
    app()->forgetInstance(ModelArtifactLoader::class);

    Livewire::test(ListRecommendationRuns::class)
        ->assertActionDisabled('proses_rekomendasi');
});
