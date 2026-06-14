<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Solution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function reportSolution(Request $request, Solution $solution): JsonResponse
    {
        return $this->doReport($request, $solution);
    }

    public function reportComment(Request $request, Comment $comment): JsonResponse
    {
        return $this->doReport($request, $comment);
    }

    private function doReport(Request $request, Solution|Comment $reportable): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        // Prevent reporting own content.
        if (isset($reportable->user_id) && $reportable->user_id === $request->user()->id) {
            return response()->json(['message' => 'نمی‌توانید محتوای خودتان را گزارش دهید.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = Report::where('user_id', $request->user()->id)
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->id)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'قبلاً گزارش داده‌اید.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($request, $reportable, $data): void {
            Report::create([
                'user_id'         => $request->user()->id,
                'reportable_type' => $reportable->getMorphClass(),
                'reportable_id'   => $reportable->id,
                'reason'          => $data['reason'] ?? null,
            ]);

            $reportable->increment('reports_count');

            $threshold = Setting::getInt('report_threshold', 3);
            if ($reportable->reports_count >= $threshold) {
                $reportable->update(['is_hidden' => true]);
            }
        });

        return response()->json(['message' => 'گزارش ثبت شد.']);
    }
}
