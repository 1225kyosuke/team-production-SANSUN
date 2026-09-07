<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\PasswordLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MasterController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $subjects = Subject::with('teacher')->when($request->user()->role === 'teacher', fn ($q) => $q->where('teacher_id', $request->user()->id))->get();

        return response()->json(['students' => Student::count(), 'teachers' => User::where('role', 'teacher')->count(), 'subjects' => $subjects->count(), 'completed' => $subjects->where('is_finalized', true)->count(), 'recent_subjects' => $subjects->values()]);
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $this->staffOnly($request);

        return response()->json(match ($type) {
            'students' => Student::orderBy('student_number')->get(),'users' => User::orderBy('role')->orderBy('name')->get(),'subjects' => Subject::with(['teacher', 'students:id'])->orderByDesc('year')->get(),default => abort(404)
        });
    }

    public function showUser(Request $request, User $user): JsonResponse
    {
        $this->staffOnly($request);
        $user->load(['subjects' => fn ($query) => $query->orderByDesc('year')->orderBy('term')->orderBy('name')->orderBy('id')]);

        return response()->json(['user' => $user, 'subjects' => $user->subjects]);
    }

    public function sendUserPasswordReset(Request $request, User $user, PasswordLinkService $links): JsonResponse
    {
        $this->staffOnly($request);
        if (! $user->is_active) {
            return response()->json(['message' => '無効な利用者にはパスワード再設定メールを送信できません。'], 422);
        }

        $links->sendSetup($user, true);

        return response()->json(['message' => 'パスワード再設定用リンクをメールで送信しました。']);
    }

    public function approveUserRole(Request $request, User $user): JsonResponse
    {
        $this->staffOnly($request);
        if ($user->is($request->user())) {
            return response()->json(['message' => 'ログイン中の自分自身の役割は変更できません。'], 422);
        }
        $data = $request->validate(['role' => ['required', 'in:teacher,staff']]);
        $user->update(['role' => $data['role']]);
        $roleLabel = $data['role'] === 'staff' ? '専任職員' : '講師';

        return response()->json([
            'user' => $user->fresh(),
            'message' => $roleLabel.'として承認しました。',
        ]);
    }

    public function store(Request $request, string $type, PasswordLinkService $links): JsonResponse
    {
        $this->staffOnly($request);
        $record = match ($type) {
            'students' => Student::create($request->validate(['student_number' => ['required', 'unique:students'], 'name' => ['required'], 'kana' => ['nullable'], 'course' => ['required'], 'grade_year' => ['required', 'integer', 'between:1,3'], 'class_name' => ['required'], 'status' => ['required'], 'email' => ['nullable', 'email']])),
            'users' => tap(User::create(array_merge($request->validate(['name' => ['required'], 'email' => ['required', 'email', 'unique:users'], 'role' => ['required', 'in:teacher,staff'], 'kana' => ['nullable'], 'gender' => ['nullable']]), ['password' => Hash::make(Str::random(48)), 'must_set_password' => true])), fn ($user) => $links->sendSetup($user)),
            'subjects' => Subject::create($request->validate(['code' => ['required', 'unique:subjects'], 'name' => ['required'], 'year' => ['required', 'integer'], 'term' => ['required', 'in:前期,後期'], 'course' => ['required'], 'class_name' => ['required'], 'teacher_id' => ['nullable', 'exists:users,id']])),default => abort(404)
        };

        return response()->json($record, 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $record = match ($type) {
            'students' => Student::findOrFail($id),'users' => User::findOrFail($id),'subjects' => Subject::findOrFail($id),default => abort(404)
        };
        if ($type === 'users') {
            $request->validate(['role' => ['sometimes', 'in:teacher,staff'], 'is_active' => ['sometimes', 'boolean']]);
        }
        $allowed = match ($type) {
            'students' => ['name', 'kana', 'course', 'grade_year', 'class_name', 'status', 'email'],'users' => ['name', 'kana', 'role', 'is_active'],'subjects' => ['name', 'year', 'term', 'course', 'class_name', 'teacher_id', 'deadline'],default => []
        };
        $record->update($request->only($allowed));

        return response()->json($record->fresh());
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $record = match ($type) {
            'students' => Student::findOrFail($id),'users' => User::findOrFail($id),'subjects' => Subject::findOrFail($id),default => abort(404)
        };
        if ($type === 'users' && $record->id === $request->user()->id) {
            return response()->json(['message' => '自分自身のアカウントは削除できません。'], 422);
        }
        $record->delete();

        return response()->json(['message' => '削除しました。']);
    }

    public function assign(Request $request, Subject $subject): JsonResponse
    {
        $this->staffOnly($request);
        $values = $request->validate(['teacher_id' => ['nullable', 'exists:users,id'], 'student_ids' => ['array'], 'student_ids.*' => ['exists:students,id']]);
        if (array_key_exists('teacher_id', $values)) {
            $subject->update(['teacher_id' => $values['teacher_id']]);
        }
        if (array_key_exists('student_ids', $values)) {
            Enrollment::where('subject_id', $subject->id)->delete();
            Grade::where('subject_id', $subject->id)->whereNotIn('student_id', $values['student_ids'])->delete();
            foreach ($values['student_ids'] as $id) {
                Enrollment::firstOrCreate(['subject_id' => $subject->id, 'student_id' => $id]);
                Grade::firstOrCreate(['subject_id' => $subject->id, 'student_id' => $id]);
            }
        }

        return response()->json($subject->fresh('teacher'));
    }

    private function staffOnly(Request $request): void
    {
        if ($request->user()->role !== 'staff') {
            abort(403, '専任職員のみ操作できます。');
        }
    }
}
