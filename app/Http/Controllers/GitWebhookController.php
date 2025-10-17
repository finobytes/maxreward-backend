<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GitWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // GET method - show deployment interface or info
        if ($request->isMethod('get')) {
            // If requesting info only
            if ($request->has('info')) {
                return response()->json([
                    'deploy_path' => env('DEPLOY_PATH'),
                    'deploy_branch' => env('DEPLOY_BRANCH'),
                ]);
            }
            
            return $this->showDeploymentPage();
        }

        // Only POST allowed for actual deployment
        if (!$request->isMethod('post')) {
            Log::warning('Git webhook: Method not allowed', ['method' => $request->method()]);
            return response()->json(['error' => 'Method Not Allowed'], 405);
        }

        // Check if webhook secret is configured
        $secret = env('GIT_WEBHOOK_SECRET');
        if (empty($secret)) {
            Log::error('Git webhook: Secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        // Get raw payload
        $payload = $request->getContent();

        // Verify signature
        $signature = $request->header('X-Hub-Signature-256'); // GitHub
        $tokenHeader = $request->header('X-Webhook-Token');   // Custom header

        $valid = false;

        if ($signature) {
            // GitHub signature verification (sha256)
            $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
            $valid = hash_equals($hash, $signature);
            Log::info('Git webhook: GitHub signature verification', ['valid' => $valid]);
        } elseif ($tokenHeader) {
            // Custom token header verification
            $valid = hash_equals($secret, $tokenHeader);
            Log::info('Git webhook: Custom token verification', ['valid' => $valid]);
        } else {
            Log::warning('Git webhook: No signature or token provided');
            return response()->json(['error' => 'Missing signature/header'], 403);
        }

        if (!$valid) {
            Log::warning('Git webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Parse payload and check branch
        $json = json_decode($payload, true);
        $deployBranch = env('DEPLOY_BRANCH', 'main');

        if (is_array($json) && isset($json['ref'])) {
            $ref = $json['ref']; // e.g., "refs/heads/main"
            
            if ($ref !== "refs/heads/{$deployBranch}") {
                Log::info('Git webhook: Push to non-deploy branch ignored', [
                    'ref' => $ref,
                    'deploy_branch' => $deployBranch
                ]);
                return response()->json([
                    'success' => true,
                    'message' => "Push to non-deploy branch ({$ref}), ignored"
                ], 200);
            }
        }

        Log::info('Git webhook: Starting deployment', ['branch' => $deployBranch]);

        // Get deploy path
        $deployPath = env('DEPLOY_PATH', base_path());

        // Validate deploy path
        if (!is_dir($deployPath)) {
            Log::error('Git webhook: Deploy path not found', ['path' => $deployPath]);
            return response()->json(['error' => "Deploy path not found: {$deployPath}"], 500);
        }

        // Check if .git directory exists
        if (!is_dir($deployPath . '/.git')) {
            Log::error('Git webhook: Not a git repository', ['path' => $deployPath]);
            return response()->json(['error' => "Not a git repository: {$deployPath}"], 500);
        }

        try {
            $output = [];
            $success = true;

            // Step 1: Git pull
            Log::info('Git webhook: Step 1 - Git operations');
            $gitCommands = [
                "cd " . escapeshellarg($deployPath),
                "git fetch --all --prune 2>&1",
                "git reset --hard origin/" . escapeshellarg($deployBranch) . " 2>&1",
                "git pull origin " . escapeshellarg($deployBranch) . " 2>&1",
            ];
            
            $result = $this->runCommand(implode(' && ', $gitCommands));
            $output[] = "=== Git Operations ===\n" . $result['output'];
            
            if (!$result['success']) {
                throw new \Exception("Git operations failed: " . $result['error']);
            }

            // Step 2: Composer install (if composer.json exists)
            if (file_exists($deployPath . '/composer.json')) {
                Log::info('Git webhook: Step 2 - Composer install');
                $composerCommand = "cd " . escapeshellarg($deployPath) . " && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>&1";
                
                $result = $this->runCommand($composerCommand);
                $output[] = "\n=== Composer Install ===\n" . $result['output'];
                
                if (!$result['success']) {
                    Log::warning('Git webhook: Composer install failed (continuing anyway)', [
                        'error' => $result['error']
                    ]);
                }
            }

            // Step 3: Laravel optimizations
            Log::info('Git webhook: Step 3 - Laravel optimizations');
            $laravelCommands = [
                "cd " . escapeshellarg($deployPath),
                "php artisan config:clear 2>&1",
                "php artisan cache:clear 2>&1",
                "php artisan route:clear 2>&1",
                "php artisan view:clear 2>&1",
                "php artisan config:cache 2>&1",
                "php artisan route:cache 2>&1",
                "php artisan view:cache 2>&1",
            ];
            
            $result = $this->runCommand(implode(' && ', $laravelCommands));
            $output[] = "\n=== Laravel Optimizations ===\n" . $result['output'];
            
            if (!$result['success']) {
                Log::warning('Git webhook: Laravel optimizations had issues', [
                    'error' => $result['error']
                ]);
            }

            // Step 4: Migrations (Optional - only if AUTO_MIGRATE is true)
            if (env('AUTO_MIGRATE', false)) {
                Log::info('Git webhook: Step 4 - Running migrations');
                $migrateCommand = "cd " . escapeshellarg($deployPath) . " && php artisan migrate --force 2>&1";
                
                $result = $this->runCommand($migrateCommand);
                $output[] = "\n=== Migrations ===\n" . $result['output'];
                
                if (!$result['success']) {
                    Log::warning('Git webhook: Migration failed', ['error' => $result['error']]);
                }
            }

            Log::info('Git webhook: Deployment completed successfully');

            return response()->json([
                'success' => true,
                'message' => 'Deployment completed successfully',
                'output' => implode("\n", $output),
                'timestamp' => now()->toIso8601String(),
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Git webhook: Deployment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deployment failed',
                'error' => $e->getMessage(),
                'output' => implode("\n", $output ?? []),
            ], 500);
        }
    }

    /**
     * Run shell command safely
     * 
     * @param string $command
     * @return array
     */
    private function runCommand(string $command): array
    {
        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Show deployment page with button
     * 
     * @return \Illuminate\Http\Response
     */
    private function showDeploymentPage()
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Auto Deploy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            border-radius: 8px;
        }
        .info-box strong {
            color: #667eea;
            display: block;
            margin-bottom: 8px;
        }
        .info-box div {
            color: #555;
            font-size: 14px;
            margin: 5px 0;
        }
        #deployBtn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            margin: 20px 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        #deployBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        #deployBtn:active {
            transform: translateY(0);
        }
        #deployBtn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        #output {
            margin-top: 30px;
            padding: 20px;
            background: #1e1e1e;
            color: #00ff00;
            border-radius: 12px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            display: none;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        #output.show {
            display: block;
        }
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .success {
            color: #00ff00 !important;
        }
        .error {
            color: #ff4444 !important;
        }
        .status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .footer {
            margin-top: 30px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Git Auto Deploy</h1>
        <p class="subtitle">Deploy your latest code changes</p>
        
        <div class="info-box">
            <strong>📋 Deployment Info</strong>
            <div>🌿 Branch: <code id="branch">main</code></div>
            <div>📁 Path: <code id="path">/home/solaimanbd.com/public_html</code></div>
        </div>

        <button id="deployBtn" onclick="deploy()">
            <span id="btnText">Deploy Now</span>
        </button>

        <div id="output"></div>

        <div class="footer">
            MaxReward Backend | Auto Deployment System
        </div>
    </div>

    <script>
        const secretToken = prompt('Enter deployment token:');
        
        if (!secretToken) {
            document.getElementById('deployBtn').disabled = true;
            document.getElementById('btnText').textContent = 'Token Required';
        }

        async function deploy() {
            const btn = document.getElementById('deployBtn');
            const output = document.getElementById('output');
            const btnText = document.getElementById('btnText');
            
            // Disable button
            btn.disabled = true;
            btnText.innerHTML = '<span class="spinner"></span> Deploying...';
            
            // Show output
            output.classList.add('show');
            output.innerHTML = '⏳ Starting deployment...\n\n';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Webhook-Token': secretToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        ref: 'refs/heads/main',
                        manual_deploy: true
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    output.innerHTML += '<span class="success">✅ Deployment Successful!</span>\n\n';
                    output.innerHTML += data.output || 'Deployment completed successfully.';
                    btnText.innerHTML = '✅ Deployed!';
                    
                    setTimeout(() => {
                        btnText.textContent = 'Deploy Again';
                        btn.disabled = false;
                    }, 3000);
                } else {
                    output.innerHTML += '<span class="error">❌ Deployment Failed!</span>\n\n';
                    output.innerHTML += 'Error: ' + (data.error || data.message || 'Unknown error');
                    if (data.output) {
                        output.innerHTML += '\n\nOutput:\n' + data.output;
                    }
                    btnText.textContent = 'Try Again';
                    btn.disabled = false;
                }
            } catch (error) {
                output.innerHTML += '<span class="error">❌ Request Failed!</span>\n\n';
                output.innerHTML += 'Error: ' + error.message;
                btnText.textContent = 'Try Again';
                btn.disabled = false;
            }
        }

        // Update info from env
        fetch(window.location.href + '?info=1')
            .then(r => r.json())
            .then(data => {
                if (data.deploy_branch) {
                    document.getElementById('branch').textContent = data.deploy_branch;
                }
                if (data.deploy_path) {
                    document.getElementById('path').textContent = data.deploy_path;
                }
            })
            .catch(() => {});
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}