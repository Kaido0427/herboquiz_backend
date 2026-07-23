<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        return Question::when($request->query('manche_id'),
            fn ($q, $m) => $q->where('manche_id', $m))
            ->orderBy('ordre')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Question::create($this->valider($request)), 201);
    }

    /** Saisie en lot : on prepare toutes les questions d'un match d'un coup. */
    public function storeLot(Request $request)
    {
        $data = $request->validate([
            'manche_id'          => ['required', 'uuid', 'exists:manches,id'],
            'questions'          => ['required', 'array', 'min:1'],
            'questions.*.texte'  => ['required', 'string'],
            'questions.*.reponse'=> ['required', 'string'],
            'questions.*.theme'  => ['nullable', 'string', 'max:60'],
        ]);

        $creees = DB::transaction(function () use ($data) {
            $depart = Question::where('manche_id', $data['manche_id'])->max('ordre') ?? -1;

            return collect($data['questions'])->map(fn ($q, $i) => Question::create([
                'manche_id' => $data['manche_id'],
                'texte'     => $q['texte'],
                'reponse'   => $q['reponse'],
                'theme'     => $q['theme'] ?? null,
                'ordre'     => $depart + 1 + $i,
            ]));
        });

        return response()->json(['creees' => $creees->count()], 201);
    }

    public function update(Request $request, Question $question)
    {
        $question->update($this->valider($request));

        return response()->json($question);
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return response()->json(['message' => 'Question supprimee.']);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'manche_id' => ['nullable', 'uuid', 'exists:manches,id'],
            'texte'     => ['required', 'string'],
            'reponse'   => ['required', 'string'],
            'indice'    => ['nullable', 'string'],
            'theme'     => ['nullable', 'string', 'max:60'],
            'ordre'     => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
