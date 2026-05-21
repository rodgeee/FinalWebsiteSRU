<?php

namespace App\Service;

/**
 * Centralized workflow metadata for the shoe lab.
 */
final class ServiceWorkflow
{
    public const DEFAULT_STATUS = 'Awaiting Intake';

    /**
     * Describes each supported service stage.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function stageMeta(): array
    {
        return [
            'Awaiting Intake' => [
                'badge' => 'bg-slate-100 text-slate-700 border border-slate-200',
                'progress' => 12,
                'phase' => 'Intake bay',
                'bucket' => 'intake',
                'etaHours' => 48,
                'description' => 'Tagged, bagged, and waiting for diagnostics.',
            ],
            'Assessment & Quote' => [
                'badge' => 'bg-slate-100 text-slate-700 border border-slate-200',
                'progress' => 28,
                'phase' => 'Diagnostics desk',
                'bucket' => 'intake',
                'etaHours' => 36,
                'description' => 'Technician assessing materials & plan of attack.',
            ],
            'Deep Clean' => [
                'badge' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'progress' => 44,
                'phase' => 'Clean room',
                'bucket' => 'cleaning',
                'etaHours' => 28,
                'description' => 'Pre-treatment, soak, steam clean, and detailing.',
            ],
            'Drying & Deodorizing' => [
                'badge' => 'bg-sky-50 text-sky-700 border border-sky-200',
                'progress' => 62,
                'phase' => 'Dry room',
                'bucket' => 'cleaning',
                'etaHours' => 18,
                'description' => 'Climate-controlled cure with deodorizing cycle.',
            ],
            'Repair Bench' => [
                'badge' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'progress' => 78,
                'phase' => 'Repair bench',
                'bucket' => 'repair',
                'etaHours' => 30,
                'description' => 'Stitch-work, patching, sole swaps, and reglue.',
            ],
            'Finishing & QC' => [
                'badge' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'progress' => 90,
                'phase' => 'Finishing booth',
                'bucket' => 'repair',
                'etaHours' => 12,
                'description' => 'Conditioning, repaint, lace swap, and QA shots.',
            ],
            'Ready for Pickup' => [
                'badge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'progress' => 100,
                'phase' => 'Front desk',
                'bucket' => 'ready',
                'etaHours' => 0,
                'description' => 'Customer notified and awaiting release.',
            ],
            'Completed & Picked up' => [
                'badge' => 'bg-gray-100 text-gray-600 border border-gray-200',
                'progress' => 100,
                'phase' => 'Archive',
                'bucket' => 'complete',
                'etaHours' => 0,
                'description' => 'Closed job archived for reference.',
            ],
        ];
    }

    /**
     * Select options for the status field.
     *
     * @return array<string, string>
     */
    public static function statusChoices(): array
    {
        $statuses = array_keys(self::stageMeta());

        return array_combine($statuses, $statuses);
    }

    /**
     * Human labels for each pipeline bucket.
     *
     * @return array<string, string>
     */
    public static function bucketLabels(): array
    {
        return [
            'intake' => 'Intake',
            'cleaning' => 'Clean room',
            'repair' => 'Repair bench',
            'ready' => 'Ready to collect',
            'complete' => 'Completed',
        ];
    }
}

