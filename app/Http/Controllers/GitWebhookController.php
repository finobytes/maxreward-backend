<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class GitWebhookController extends Controller
{
    public function autoDeploy(Request $request)
    {
        // 1. Verify Token
        $providedToken = $request->query('token');
        $secret = env('GIT_WEBHOOK_SECRET');

        if (!$secret) {
            return response()->json([
                'success' => false,
                'message' => 'GIT_WEBHOOK_SECRET not configured in .env'
            ], 500);
        }

        if ($providedToken !== $secret) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing token'
            ], 403);
        }

        // 2. Get Deploy Path & Branch
        $deployPath   = env('DEPLOY_PATH', base_path());
        $deployBranch = env('DEPLOY_BRANCH', 'main');

        if (!is_dir($deployPath)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid deploy path: $deployPath"
            ], 500);
        }

        if (!is_dir($deployPath . '/.git')) {
            return response()->json([
                'success' => false,
                'message' => 'Target is not a Git repository'
            ], 500);
        }

        $output = [];
        try {
            // === Step 1: Git reset & pull ===
            $commands = [
                "cd " . escapeshellarg($deployPath),
                "git fetch --all --prune 2>&1",
                "git reset --hard origin/{$deployBranch} 2>&1",
                "git pull origin {$deployBranch} 2>&1",
            ];
            $gitResult = $this->runCommand(implode(' && ', $commands));
            $output[] = "=== Git ===\n" . $gitResult['output'];

            // If git failed, abort
            if (!$gitResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Git pull failed',
                    'error'   => $gitResult['error'],
                    'output'  => implode("\n", $output),
                ], 500);
            }

            // === Step 2: Composer install if needed ===
            // if (file_exists("$deployPath/composer.json")) {
            //     $composerCmd = "cd " . escapeshellarg($deployPath) .
            //                    " && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1";
            //     $composerRes = $this->runCommand($composerCmd);
            //     $output[] = "=== Composer ===\n" . $composerRes['output'];
            // }

            // === Step 3: Laravel optimizations ===
            $artisanCommands = [
                "cd " . escapeshellarg($deployPath),
                "php artisan config:clear 2>&1",
                "php artisan cache:clear 2>&1",
                "php artisan route:clear 2>&1",
                "php artisan view:clear 2>&1",
                "php artisan config:cache 2>&1",
                "php artisan route:cache 2>&1",
                "php artisan view:cache 2>&1",
            ];
            $artisanResult = $this->runCommand(implode(' && ', $artisanCommands));
            $output[] = "=== Artisan Optimize ===\n" . $artisanResult['output'];

            // === Step 4 (Optional): Migrations if AUTO_MIGRATE=true ===
            if (env('AUTO_MIGRATE', false) === true) {
                $migrateCmd = "cd " . escapeshellarg($deployPath) .
                              " && php artisan migrate --force 2>&1";
                $migrateRes = $this->runCommand($migrateCmd);
                $output[] = "=== Artisan Migrate ===\n" . $migrateRes['output'];
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Deployment completed successfully',
            //     'output'  => implode("\n", $output),
            // ], 200);

            $htmlOutput = nl2br(e(implode("\n", $output)));

            return response()->make("
                <html>
                    <head>
                        <title>Deployment Result</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 30px; background: #f4f4f9; }
                            .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                            .status { font-size: 18px; margin-bottom: 10px; }
                            .success { color: green; }
                            .error { color: red; }
                            pre { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='status success'>✅ Deployment Successful</div>
                            <pre>{$htmlOutput}</pre>
                        </div>
                    </body>
                </html>
            ", 200, ['Content-Type' => 'text/html']);


        } catch (\Throwable $e) {
            Log::error('AutoDeploy Error', [
                'error' => $e->getMessage(),
            ]);
            // return response()->json([
            //     'success' => false,
            //     'message' => 'Deployment failed',
            //     'error'   => $e->getMessage(),
            //     'output'  => implode("\n", $output),
            // ], 500);

            $htmlOutput = nl2br(e(implode("\n", $output)));

            return response()->make("
                <html>
                    <head>
                        <title>Deployment Failed</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 30px; background: #f4f4f9; }
                            .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                            .status { font-size: 18px; margin-bottom: 10px; }
                            .error { color: red; }
                            pre { background: #1e1e1e; color: #ff4d4d; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='status error'>❌ Deployment Failed</div>
                            <pre>Error: {$e->getMessage()}\n\n{$htmlOutput}</pre>
                        </div>
                    </body>
                </html>
            ", 500, ['Content-Type' => 'text/html']);

        }
    }

    private function runCommand(string $command): array
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300); // seconds
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error'   => $process->getErrorOutput(),
        ];
    }
}
