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
use Throwable;

class CsvController extends Controller
{
    private const INITIAL_PASSWORD = 'Password123';

    private const ALIASES = [
        'users' => ['氏名'=>'name','name'=>'name','氏名（ひらがな）'=>'kana','ふりがな'=>'kana','kana'=>'kana','性別'=>'gender','gender'=>'gender','メールアドレス'=>'email','email'=>'email','役割'=>'role','role'=>'role','年齢'=>'age','age'=>'age'],
        'subjects' => ['専攻'=>'course','course'=>'course','科目名'=>'name','name'=>'name','担当講師'=>'teacher_name','担当講師名'=>'teacher_name','学年'=>'year','年度'=>'year','year'=>'year','科目コード'=>'code','code'=>'code','学期'=>'term','term'=>'term'],
        'students' => ['学籍番号'=>'student_number','student_number'=>'student_number','氏名'=>'name','name'=>'name','氏名（ひらがな）'=>'kana','ふりがな'=>'kana','kana'=>'kana','生年月日'=>'birth_date','birth_date'=>'birth_date','性別'=>'gender','gender'=>'gender','メールアドレス'=>'email','email'=>'email','電話番号'=>'phone','phone'=>'phone','郵便番号'=>'postal_code','postal_code'=>'postal_code','住所'=>'address','専攻'=>'course','course'=>'course','学年'=>'grade_year','grade_year'=>'grade_year','ステータス'=>'status','status'=>'status'],
    ];

    public function import(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'staff') abort(403);
        $request->validate(['type'=>'required|in:students,users,subjects','role'=>'nullable|in:teacher,staff','file'=>'required|file|mimes:csv,txt|max:5120']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        if (!$headers) return response()->json(['message'=>'ヘッダー行がありません。','errors'=>[]], 422);
        $headers = array_map(fn($value)=>trim((string)preg_replace('/^\xEF\xBB\xBF/', '', $value)), $headers);
        $mapped = array_map(fn($header)=>self::ALIASES[$request->type][$header] ?? '', $headers);
        $required = match ($request->type) {'users'=>['name','email'],'subjects'=>['name','course'],default=>['student_number','name','course']};
        if ($missing=array_diff($required,$mapped)) return response()->json(['message'=>'必須ヘッダーが不足しています。','errors'=>array_map(fn($field)=>['line'=>1,'field'=>$field,'reason'=>'必須項目です。'],$missing)],422);

        $rows=[]; $errors=[]; $line=1;
        while (($values=fgetcsv($handle)) !== false) {
            $line++;
            if (count($values)===1 && trim((string)$values[0])==='') continue;
            if (count($values)!==count($headers)) {$errors[]=['line'=>$line,'field'=>'行全体','reason'=>'列数が一致しません。'];continue;}
            $data=[]; foreach ($mapped as $index=>$key) if ($key) $data[$key]=trim((string)$values[$index]);
            if (isset($data['birth_date']) && preg_match('/^(\d{4})年(\d{1,2})月(\d{1,2})日$/u',$data['birth_date'],$m)) $data['birth_date']=sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]);
            $rows[]=['line'=>$line,'data'=>$data];
        }
        fclose($handle);

        $rules=match($request->type){'users'=>['name'=>'required','email'=>'required|email'],'subjects'=>['name'=>'required','course'=>'required|in:共通,Webデザイナー,システムエンジニア'],'students'=>['student_number'=>'required','name'=>'required','course'=>'required']};
        $count=0;
        foreach ($rows as $row) {
            $validator=Validator::make($row['data'],$rules,['required'=>'必須項目です。','email'=>'メールアドレス形式が不正です。','in'=>'許可されていない値です。']);
            if ($validator->fails()) {foreach($validator->errors()->messages() as $field=>$messages)$errors[]=['line'=>$row['line'],'field'=>$field,'reason'=>$messages[0]];continue;}
            try {
                DB::transaction(function() use ($request,$row,&$count) {
                    $data=$row['data'];
                    if ($request->type==='students') Student::updateOrCreate(['student_number'=>$data['student_number']],['name'=>$data['name'],'kana'=>$data['kana']??null,'birth_date'=>$data['birth_date']??null,'gender'=>$data['gender']??null,'email'=>$data['email']??null,'phone'=>$data['phone']??null,'postal_code'=>$data['postal_code']??null,'address'=>$data['address']??null,'course'=>$data['course'],'grade_year'=>(int)($data['grade_year']??1),'status'=>$data['status']??'在籍中','class_name'=>'1組']);
                    elseif ($request->type==='subjects') {
                        $year=(int)($data['year']??$request->input('year',date('Y')));
                        $code=$data['code']??'CSV-'.strtoupper(substr(sha1($data['name'].'|'.$data['course'].'|'.$year),0,12));
                        $teacher=$this->resolveTeacher($data['teacher_name']??null);
                        Subject::updateOrCreate(['code'=>$code],['name'=>$data['name'],'course'=>$data['course'],'year'=>$year,'term'=>$data['term']??'前期','class_name'=>'1組','teacher_id'=>$teacher?->id]);
                    } else {
                        $existing=User::where('email',$data['email'])->first();
                        $role=$request->input('role',$data['role']??'teacher');
                        User::updateOrCreate(['email'=>$data['email']],['name'=>$data['name'],'kana'=>$data['kana']??null,'gender'=>$data['gender']??null,'role'=>$role,'is_active'=>true,'password'=>$existing?->password??Hash::make(self::INITIAL_PASSWORD),'must_set_password'=>$existing?->must_set_password??false]);
                    }
                    $count++;
                });
            } catch (Throwable $exception) {$errors[]=['line'=>$row['line'],'field'=>'データ','reason'=>'登録できませんでした：'.$exception->getMessage()];}
        }
        return response()->json(['message'=>$count.'件を取り込みました。','count'=>$count,'errors'=>$errors]);
    }

    private function resolveTeacher(?string $value): ?User
    {
        if (!$value) return null;
        $value=trim($value);
        $byEmail=User::where('email',$value)->where('is_active',true)->first();
        if ($byEmail) return $byEmail;
        $normalized=$this->normalizeName($value);
        return User::where('is_active',true)->get()->first(fn(User $user)=>$this->normalizeName($user->name)===$normalized || $this->normalizeName((string)$user->kana)===$normalized);
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[\s　]+/u','',mb_convert_kana(trim($value),'sKV','UTF-8'))??'';
    }
}
