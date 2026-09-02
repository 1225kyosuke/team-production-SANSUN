<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CsvController extends Controller
{
    public function import(Request $request):JsonResponse
    {
        if($request->user()->role!=='staff')abort(403);$request->validate(['type'=>['required','in:students,users,subjects'],'file'=>['required','file','mimes:csv,txt','max:5120']]);
        $handle=fopen($request->file('file')->getRealPath(),'r');$headers=fgetcsv($handle);if(!$headers)return response()->json(['message'=>'ヘッダー行がありません。'],422);
        $headers=array_map(fn($v)=>trim((string)$v),$headers);$rows=[];$errors=[];$line=1;
        while(($values=fgetcsv($handle))!==false){$line++;if(count($headers)!==count($values)){$errors[]=['line'=>$line,'field'=>'行全体','reason'=>'列数がヘッダーと一致しません。'];continue;}$rows[]=['line'=>$line,'data'=>array_combine($headers,$values)];}fclose($handle);
        $rules=match($request->type){'students'=>['student_number'=>'required','name'=>'required','course'=>'required','grade_year'=>'required|integer|between:1,3'],'users'=>['name'=>'required','email'=>'required|email','role'=>'required|in:teacher,staff'],'subjects'=>['code'=>'required','name'=>'required','year'=>'required|integer','term'=>'required|in:前期,後期'],};
        foreach($rows as $row){$validator=Validator::make($row['data'],$rules);foreach($validator->errors()->messages() as $field=>$messages)$errors[]=['line'=>$row['line'],'field'=>$field,'reason'=>$messages[0]];}
        if($errors)return response()->json(['message'=>'CSVにエラーがあります。全件取り込みを中止しました。','errors'=>$errors],422);
        DB::transaction(function()use($rows,$request){foreach($rows as $row){$d=$row['data'];match($request->type){'students'=>Student::updateOrCreate(['student_number'=>$d['student_number']],['name'=>$d['name'],'course'=>$d['course'],'grade_year'=>(int)$d['grade_year'],'class_name'=>$d['class_name']??'1組','status'=>$d['status']??'在籍中','kana'=>$d['kana']??null,'email'=>$d['email']??null]),'users'=>User::updateOrCreate(['email'=>$d['email']],['name'=>$d['name'],'role'=>$d['role'],'kana'=>$d['kana']??null,'password'=>Hash::make($d['password']??'Password123'),'is_active'=>true]),'subjects'=>Subject::updateOrCreate(['code'=>$d['code']],['name'=>$d['name'],'year'=>(int)$d['year'],'term'=>$d['term'],'course'=>$d['course']??'共通','class_name'=>$d['class_name']??'1組']),};}});
        return response()->json(['message'=>count($rows).'件を取り込みました。','count'=>count($rows)]);
    }
}
