<?php

namespace App\Http\Controllers;

use App\Models\Deploy;
use App\Support\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DeployController extends Controller
{
    /** GET /api/deploys — riwayat deploy + ringkasan + info server yang sedang berjalan. */
    public function index(): JsonResponse
    {
        try {
            $rows = Deploy::orderByDesc('created_at')->orderByDesc('id')->limit(500)->get();
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal memuat riwayat deploy dari database.', 500);
        }

        $success = $rows->where('status', 'Berhasil');

        return response()->json([
            'deploys' => $rows,
            'summary' => [
                'totalDeploys' => $rows->count(),
                'successCount' => $success->count(),
                'failedCount' => $rows->where('status', 'Gagal')->count(),
                'productionCount' => $rows->where('environment', 'Production')->count(),
                'lastDeployAt' => Iso::format($rows->first()?->created_at),
                'lastDeployVersion' => $rows->first()?->version,
                'lastSuccessAt' => Iso::format($success->first()?->created_at),
            ],
            'current' => [
                'isVercel' => false,
                'environment' => app()->environment(),
                'region' => null,
                'deploymentId' => null,
                'url' => config('app.url'),
                'branch' => null,
                'commitSha' => null,
                'commitMessage' => null,
                'author' => null,
                // Nama kunci dipertahankan agar admin.js tidak perlu diubah.
                'nodeVersion' => 'PHP '.PHP_VERSION.' · Laravel '.app()->version(),
            ],
            'serverTime' => Iso::format(now()),
        ]);
    }

    /** POST /api/deploys — catat deploy manual (form "Catat Deploy" di admin). */
    public function store(Request $request): JsonResponse
    {
        $version = $this->clean($request->input('version'), 40);
        if ($version === '') {
            return $this->error('Versi / nama rilis wajib diisi.');
        }

        $environment = $this->clean($request->input('environment'), 20) ?: 'Production';
        if (! in_array($environment, Deploy::ENVIRONMENTS, true)) {
            return $this->error('Lingkungan tidak valid. Pilih: '.implode(', ', Deploy::ENVIRONMENTS).'.');
        }

        $status = $this->clean($request->input('status'), 20) ?: 'Berhasil';
        if (! in_array($status, Deploy::STATUSES, true)) {
            return $this->error('Status tidak valid. Pilih: '.implode(', ', Deploy::STATUSES).'.');
        }

        $url = $this->clean($request->input('url'), 300) ?: null;
        if ($url && ! preg_match('#^https?://#i', $url)) {
            return $this->error('URL harus diawali http:// atau https://.');
        }

        $duration = null;
        $rawDuration = $request->input('durationSeconds');
        if ($rawDuration !== null && $rawDuration !== '') {
            $duration = filter_var($rawDuration, FILTER_VALIDATE_INT);
            if ($duration === false || $duration < 0 || $duration > 86400) {
                return $this->error('Durasi harus berupa bilangan bulat detik (0 – 86400).');
            }
        }

        try {
            $deploy = Deploy::create([
                'version' => $version,
                'environment' => $environment,
                'status' => $status,
                'branch' => $this->clean($request->input('branch'), 100) ?: 'main',
                'commit_message' => $this->clean($request->input('commitMessage'), 300) ?: '-',
                'deployed_by' => $this->clean($request->input('deployedBy'), 100) ?: '-',
                'url' => $url,
                'duration_seconds' => $duration,
                'source' => 'manual',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal menyimpan data deploy.', 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Deploy {$deploy->version} berhasil dicatat.",
            'deploy' => $deploy,
        ], 201);
    }
}
