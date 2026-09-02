<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MasterController extends Controller
{
    public function dashboard(Request $request):JsonResponse{$subjects=Subject::with('teacher')->when($request->user()->role==='teacher',fn($q)=>$q->where('teacher_id',$request->user()->id))->get();return response()->json(['students'=>Student::count(),'teachers'=>User::where('role','teacher')->count(),'subjects'=>$subjects->count(),'completed'=>$subjects->where('is_finalized',true)->count(),'recent_subjects'=>$subjects->values()]);}
    public function index(Request $request,string $type):JsonResponse{$this->staffOnly($request);return response()->json(match($type){'students'=>Student::orderBy('student_number')->get(),'users'=>User::orderBy('role')->orderBy('name')->get(),'subjects'=>Subject::with('teacher')->orderByDesc('year')->get(),default=>abort(404)});}
    public function store(Request $request,string $type):JsonResponse
    {
        $this->staffOnly($request);$record=match($type){
            'students'=>Student::create($request->validate(['student_number'=>['required','unique:students'],'name'=>['required'],'kana'=>['nullable'],'course'=>['required'],'grade_year'=>['required','integer','between:1,3'],'class_name'=>['required'],'status'=>['required'],'email'=>['nullable','email']])),
            'users'=>User::create(array_merge($request->validate(['name'=>['required'],'email'=>['required','email','unique:users'],'role'=>['required','in:teacher,staff'],'kana'=>['nullable']]),['password'=>Hash::make($request->input('password','Password123'))])),
            'subjects'=>Subject::create($request->validate(['code'=>['required','unique:subjects'],'name'=>['required'],'year'=>['required','integer'],'term'=>['required','in:前期,後期'],'course'=>['required'],'class_name'=>['required'],'teacher_id'=>['nullable','exists:users,id']])),default=>abort(404)};
        return response()->json($record,201);
    }
    public function update(Request $request,string $type,int $id):JsonResponse{$this->staffOnly($request);$record=match($type){'students'=>Student::findOrFail($id),'users'=>User::findOrFail($id),'subjects'=>Subject::findOrFail($id),default=>abort(404)};$allowed=match($type){'students'=>['name','kana','course','grade_year','class_name','status','email'],'users'=>['name','kana','role','is_active'],'subjects'=>['name','year','term','course','class_name','teacher_id','deadline'],default=>[]};$record->update($request->only($allowed));return response()->json($record->fresh());}
    public function assign(Request $request,Subject $subject):JsonResponse
    {
        $this->staffOnly($request);$values=$request->validate(['teacher_id'=>['nullable','exists:users,id'],'student_ids'=>['array'],'student_ids.*'=>['exists:students,id']]);
        if(array_key_exists('teacher_id',$values))$subject->update(['teacher_id'=>$values['teacher_id']]);
        if(array_key_exists('student_ids',$values)){Enrollment::where('subject_id',$subject->id)->delete();Grade::where('subject_id',$subject->id)->whereNotIn('student_id',$values['student_ids'])->delete();foreach($values['student_ids'] as $id){Enrollment::firstOrCreate(['subject_id'=>$subject->id,'student_id'=>$id]);Grade::firstOrCreate(['subject_id'=>$subject->id,'student_id'=>$id]);}}
        return response()->json($subject->fresh('teacher'));
    }
    private function staffOnly(Request $request):void{if($request->user()->role!=='staff')abort(403,'専任職員のみ操作できます。');}
}
