<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Services\Scheduling\GeminiDraftInterpreter;
use App\Services\Scheduling\ProposalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiSchedulerV2Controller extends Controller
{
    public function interpret(Request $request, GeminiDraftInterpreter $ai): JsonResponse
    {
        $validated = $request->validate(['prompt' => ['required', 'string', 'min:10', 'max:3000']]);
        try {
            return response()->json(['ai' => $ai->interpret($validated['prompt']), 'source' => 'gemini']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'source' => 'unavailable'], 503);
        }
    }

    public function preview(Request $request, ProposalWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['draft' => ['required', 'array']]);
        // All resource IDs and dates are checked inside the G3 shared normalizer.
        return response()->json($workflow->preview($this->withCatalogCompetency($data['draft'])));
    }

    public function submit(Request $request, ProposalWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'draft' => ['required', 'array'],
            'option_label' => ['required', 'in:A,B,C'],
            'preview_token' => ['required', 'string', 'size:64'],
        ]);
        $proposal = $workflow->submit($this->withCatalogCompetency($data['draft']), $data['option_label'], $data['preview_token']);

        return response()->json([
            'message' => 'Proposal berhasil disimpan untuk ditinjau BAAK; belum menjadi jadwal resmi.',
            'proposal_id' => $proposal->id,
            'status' => $proposal->status,
            'selected_option' => $proposal->selectedOption?->option_label,
            'is_official_reserved' => false,
        ], 201);
    }

    /** Resolve a UNIQUE active catalog match; Gemini and browser never supply invented IDs. */
    private function withCatalogCompetency(array $draft): array
    {
        if (! empty($draft['required_competency_ids']) || ! empty($draft['curriculum_version_id'])) {
            return $draft;
        }
        $title = mb_strtolower(trim((string) ($draft['title'] ?? '')));
        $department = $draft['department'] ?? null;
        if (mb_strlen($title) < 3 || ! in_array($department, ['computer', 'speakout'], true)) {
            return $draft;
        }
        $matches = Competency::query()->where('department', $department)->where('is_active', true)
            ->get(['id', 'name'])->filter(function ($competency) use ($title): bool {
                $name = mb_strtolower(trim($competency->name));

                return $name === $title || str_contains($name, $title);
            });
        if ($matches->count() === 1) {
            $draft['required_competency_ids'] = [(int) $matches->first()->id];
        }

        return $draft;
    }

}
