<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\GradeHistory;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::with('teacher')->orderByDesc('year')->orderBy('term')->orderBy('name');
        if ($request->user()->role === 'teacher') $query->where('teacher_id', $request->user()->id);
        return response()->json($query->get());
    }
    public function index(Request $request, Subject $subject): JsonResponse
    {
        $this->authorizeSubject($request, $subject);
        return response()->json(['subject' => $subject->load('teacher'), 'grades' => $subject->grades()->with('student')->get()->sortBy('student.student_number')->values()]);
    }
    public function bulkSave(Request $request, Subject $subject): JsonResponse
    {
        $this->authorizeEdit($request, $subject);
        $payload = $request->validate(['grades' => ['required', 'array'], 'grades.*.id' => ['required', 'integer'], 'grades.*.attendance_rate' => ['nullable', 'numeric', 'between:0,100'], 'grades.*.attitude' => ['nullable', 'integer', 'between:1,10'], 'grades.*.assignment' => ['nullable', 'integer', 'between:1,10']]);
        DB::transaction(function () use ($payload, $subject, $request) {
            foreach ($payload['grades'] as $row) {
                $grade = $subject->grades()->findOrFail($row['id']); $before = $grade->only(['attendance_rate','attitude','assignment','score','evaluation']);
                $grade->fill(collect($row)->only(['attendance_rate','attitude','assignment'])->all()); $this->calculate($grade); $grade->save();
                GradeHistory::create(['grade_id'=>$grade->id,'user_id'=>$request->user()->id,'action'=>'成績保存','before'=>$before,'after'=>$grade->only(['attendance_rate','attitude','assignment','score','evaluation'])]);
            }
        });
        return $this->index($request, $subject);
    }
    public function updateWeights(Request $request, Subject $subject): JsonResponse
    {
        $this->authorizeEdit($request, $subject);
        $values = $request->validate(['attendance_weight'=>['required','integer','between:0,100'],'attitude_weight'=>['required','integer','between:0,100'],'assignment_weight'=>['required','integer','between:0,100']]);
        if (array_sum($values)!==100) return response()->json(['message'=>'評価重みの合計は100%にしてください。'],422);
        DB::transaction(function () use ($subject,$values,$request) { $subject->update($values); $subject->grades->each(function(Grade $grade) use($request){$before=$grade->only(['score','evaluation']);$this->calculate($grade);$grade->save();GradeHistory::create(['grade_id'=>$grade->id,'user_id'=>$request->user()->id,'action'=>'重み変更による再計算','before'=>$before,'after'=>$grade->only(['score','evaluation'])]);}); });
        return $this->index($request,$subject->fresh());
    }
    public function finalize(Request $request, Subject $subject): JsonResponse
    {
        $this->staffOnly($request); $missing=$subject->grades()->whereNull('evaluation')->with('student')->get();
        if($missing->isNotEmpty())return response()->json(['message'=>'最終評価が未表示の学生がいるため確定できません。','missing'=>$missing->pluck('student.name')],422);
        $subject->update(['is_finalized'=>true,'finalized_at'=>now(),'finalized_by'=>$request->user()->id]);
        return response()->json(['message'=>"{$subject->year}年度{$subject->term}を確定しました。",'subject'=>$subject->fresh()]);
    }
    public function unfinalize(Request $request, Subject $subject): JsonResponse
    {
        $this->staffOnly($request);$subject->update(['is_finalized'=>false,'finalized_at'=>null,'finalized_by'=>null]);return response()->json(['message'=>'確定を解除しました。','subject'=>$subject->fresh()]);
    }
    public function histories(Request $request, Subject $subject): JsonResponse
    {
        $this->authorizeSubject($request,$subject);return response()->json(GradeHistory::whereIn('grade_id',$subject->grades()->pluck('id'))->latest()->limit(100)->get());
    }
    private function calculate(Grade $grade): void
    {
        if($grade->attendance_rate===null||$grade->attitude===null||$grade->assignment===null){$grade->score=null;$grade->evaluation=null;return;}
        $s=$grade->subject;$score=(int)round($grade->attendance_rate*$s->attendance_weight/100+$grade->attitude*10*$s->attitude_weight/100+$grade->assignment*10*$s->assignment_weight/100);
        $grade->score=$score;$grade->evaluation=$score>=90?'秀':($score>=80?'優':($score>=70?'良':($score>=60?'可':'不可')));
    }
    private function authorizeSubject(Request $request,Subject $subject):void{if($request->user()->role==='teacher'&&$subject->teacher_id!==$request->user()->id)abort(403,'担当外科目にはアクセスできません。');}
    private function authorizeEdit(Request $request,Subject $subject):void{$this->authorizeSubject($request,$subject);if($request->user()->role!=='teacher')abort(403,'専任職員は成績を編集できません。');if($subject->is_finalized)abort(423,'確定済みのため編集できません。');}
    private function staffOnly(Request $request):void{if($request->user()->role!=='staff')abort(403,'専任職員のみ操作できます。');}
}
