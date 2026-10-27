<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\MLAnalysisService;
use Illuminate\Support\Facades\Log;
use Exception;

#[Signature('ml:run-analysis')]
#[Description('Execute the ML Risk Analysis pipeline.')]
class RunMLAnalysis extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MLAnalysisService $mlService)
    {
        $this->info("Starting ML Risk Analysis...");
        Log::info("ml:run-analysis - Execution started.");

        $start = microtime(true);

        try {
            $analysis = $mlService->runAnalysis();
            
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            // Log success with key metrics
            Log::info("ml:run-analysis - Execution completed successfully.", [
                'analysis_id' => $analysis->id,
                'duration_ms' => $duration
            ]);

            $this->info("ML Analysis #{$analysis->id} completed successfully in {$duration}ms.");
            return Command::SUCCESS;

        } catch (Exception $e) {
            // Determine if it's an "expected" failure (like empty dataset) or a real error
            if (str_contains($e->getMessage(), 'No eligible reports')) {
                Log::info("ml:run-analysis - Skipped: " . $e->getMessage());
                $this->warn("Analysis skipped: " . $e->getMessage());
                return Command::SUCCESS;
            }

            Log::error("ml:run-analysis - Execution failed.", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("ML Analysis failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
