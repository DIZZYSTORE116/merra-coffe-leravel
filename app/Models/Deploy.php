<?php

namespace App\Models;

use App\Support\Iso;
use Illuminate\Database\Eloquent\Model;

class Deploy extends Model
{
    public const UPDATED_AT = null;

    public const ENVIRONMENTS = ['Production', 'Preview', 'Development'];

    public const STATUSES = ['Berhasil', 'Sedang Berjalan', 'Gagal', 'Dibatalkan'];

    protected $fillable = [
        'deployment_id', 'version', 'environment', 'status', 'branch', 'commit_sha',
        'commit_message', 'deployed_by', 'url', 'duration_seconds', 'source', 'created_at',
    ];

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'deploymentId' => $this->deployment_id,
            'version' => $this->version,
            'environment' => $this->environment,
            'status' => $this->status,
            'branch' => $this->branch,
            'commitSha' => $this->commit_sha,
            'commitMessage' => $this->commit_message,
            'deployedBy' => $this->deployed_by,
            'url' => $this->url,
            'durationSeconds' => $this->duration_seconds,
            'source' => $this->source,
            'createdAt' => Iso::format($this->created_at),
        ];
    }
}
