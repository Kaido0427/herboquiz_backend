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
        // « banque » = les questions pas encore rattachees a une manche.
        $cible = $request->query('manche_id');

        return Question::when($cible === 'banque', fn ($q) => $q->whereNull('manche_id'))
            ->when($cible && $cible !== 'banque', fn ($q) => $q->where('manche_id', $cible))
            ->orderBy('ordre')->get();
    }

    /** Rattache des questions de la banque a une manche. */
    public function affecter(Request $request)
    {
        $data = $request->validate([
            'manche_id'   => ['required', 'uuid', 'exists:manches,id'],
            'questions'   => ['required', 'array', 'min:1'],
            'questions.*' => ['uuid', 'exists:questions,id'],
        ]);

        $depart = Question::where('manche_id', $data['manche_id'])->max('ordre') ?? -1;

        foreach (array_values($data['questions']) as $i => $id) {
            Question::whereKey($id)->update([
                'manche_id' => $data['manche_id'],
                'ordre'     => $depart + 1 + $i,
            ]);
        }

        return response()->json(['affectees' => count($data['questions'])]);
    }

    public function store(Request $request)
    {
        return response()->json(Question::create($this->valider($request)), 201);
    }

    /** Saisie en lot : on prepare toutes les questions d'un match d'un coup. */
    public function storeLot(Request $request)
    {
        $data = $request->validate([
            // Nullable : on prepare la banque AVANT que les manches existent.
            // L'exiger bloquait toute saisie tant qu'aucune manche n'etait
            // creee, ce qui interdisait de preparer les questions a l'avance.
            'manche_id'          => ['nullable', 'uuid', 'exists:manches,id'],
            'questions'          => ['required', 'array', 'min:1'],
            'questions.*.texte'  => ['required', 'string'],
            'questions.*.reponse'=> ['required', 'string'],
            'questions.*.theme'  => ['nullable', 'string', 'max:60'],
        ]);

        $creees = DB::transaction(function () use ($data) {
            $depart = Question::where('manche_id', $data['manche_id'] ?? null)->max('ordre') ?? -1;

            return collect($data['questions'])->map(fn ($q, $i) => Question::create([
                'manche_id'   => $data['manche_id'] ?? null,
                'propose_par' => request()->user()?->nom,
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
            'manche_id'   => ['nullable', 'uuid', 'exists:manches,id'],
            'propose_par' => ['nullable', 'string', 'max:60'],
            'validee'     => ['nullable', 'boolean'],
            'texte'       => ['required', 'string'],
            'reponse'   => ['required', 'string'],
            'indice'    => ['nullable', 'string'],
            'theme'     => ['nullable', 'string', 'max:60'],
            'ordre'     => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
