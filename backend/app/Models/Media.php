<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'media_ref',
        'submission_id',
        'question_id',
        'row_id',
        'media_type',
        'file_path',
        'captured_at',
        'gps_at_capture_lat',
        'gps_at_capture_lng',
        'phash',
        'matched_baseline_id',
        'ocr_text',
        'ocr_avg_confidence',
        'text_height_ratio',
        'brightness_mean',
        'image_width',
        'image_height',
        'sharpness_laplacian_var',
        'contrast_std_dev',
        'glare_ratio_pct',
        'noise_sigma',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'gps_at_capture_lat' => 'decimal:7',
            'gps_at_capture_lng' => 'decimal:7',
            'image_width' => 'integer',
            'image_height' => 'integer',
            'embedding' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function matchedBaseline(): BelongsTo
    {
        return $this->belongsTo(OutletBaseline::class, 'matched_baseline_id');
    }
}
