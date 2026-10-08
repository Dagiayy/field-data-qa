<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Console\Command;

/**
 * Backfills every submission's SubmissionTrustScore row using the current
 * TrustScoreCalculator formula. A stored trust score is frozen at whatever
 * the calculator returned at ingestion time — changing the calculator's
 * weights or per-dimension scoring logic (as this build's Trust Score
 * redesign does) has no effect on already-scored submissions until this is
 * run.
 */
class RecomputeTrustScores extends Command
{
    protected $signature = 'trust-scores:recompute {--agent= : Only recompute submissions for this agent_id}';

    protected $description = "Recompute every submission's Trust Score using the current TrustScoreCalculator formula";

    public function handle(TrustScoreCalculator $calculator): int
    {
        $query = Submission::query()->with(['quest', 'qaFlags', 'qaReviews', 'media']);

        if ($agentId = $this->option('agent')) {
            $query->where('agent_id', $agentId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No matching submissions found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($submissions) use ($calculator, $bar) {
            foreach ($submissions as $submission) {
                $score = $calculator->compute($submission);

                SubmissionTrustScore::updateOrCreate(
                    ['submission_id' => $submission->id],
                    [
                        'gps_score' => $score['gps'],
                        'photo_score' => $score['photo'],
                        'time_score' => $score['time'],
                        'completeness_score' => $score['completeness'],
                        'audit_confirmation_score' => $score['audit_confirmation'],
                        'total_score' => $score['total'],
                        'weights_used' => $score['weights'],
                    ]
                );

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Recomputed Trust Scores for {$total} submission(s).");

        return self::SUCCESS;
    }
}
