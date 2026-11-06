<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Services\Scheduling\AgentProposalWorkflowService;
use App\Services\Scheduling\GeminiDraftInterpreter;
use App\Services\Scheduling\ProposalWorkflowService;
use App\Services\Scheduling\SchedoraSchedulingAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiSchedulerV2Controller extends Controller
{
    public function agent(Request $request, SchedoraSchedulingAgent $agent): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:3000'],
            'current_state' => ['sometimes', 'array'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:1200'],
        ]);
        $sessionState = $request->session()->get('schedora_ai_conversation_state', []);
        $currentState = array_replace(
            is_array($sessionState) ? $sessionState : [],
            $validated['current_state'] ?? [],
        );

        try {
            $result = $agent->handle(
                $validated['message'],
                $validated['history'] ?? [],
                $currentState,
                (int) $request->user()->id,
            );
            $request->session()->put('schedora_ai_conversation_state', $result['state']);

            return response()->json(['agent' => $result, 'source' => 'gemini-agent']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'source' => 'unavailable'], 503);
        }
    }

    public function submitAgent(Request $request, AgentProposalWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'option_label' => ['required', 'in:A,B,C'],
            'preview_token' => ['required', 'string', 'size:64'],
        ]);
        $proposal = $workflow->submit($data['option_label'], $data['preview_token']);

        return response()->json([
            'message' => 'Rekomendasi AI diajukan sebagai proposal pending untuk ditinjau BAAK.',
            'proposal_id' => $proposal->id,
            'status' => $proposal->status,
            'selected_option' => $data['option_label'],
            'is_official_reserved' => false,
        ], 201);
    }

    public function interpret(Request $request, GeminiDraftInterpreter $ai): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required_without:prompt', 'string', 'min:1', 'max:3000'],
            'prompt' => ['required_without:message', 'string', 'min:1', 'max:3000'],
            'draft' => ['sometimes', 'array'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:1000'],
        ]);
        try {
            return response()->json([
                'ai' => $ai->interpret(
                    $validated['message'] ?? $validated['prompt'],
                    $validated['draft'] ?? [],
                    $validated['history'] ?? [],
                ),
                'source' => 'gemini',
            ]);
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
            'selected_option' => $data['option_label'],
            'is_official_reserved' => false,
        ], 201);
    }

    /**
     * Resolve a unique active catalog match; Gemini and browser never supply invented IDs.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
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
