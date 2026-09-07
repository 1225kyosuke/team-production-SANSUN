<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $students=[];
        foreach([['20261001','鈴木 花子','スズキ ハナコ','システムエンジニア'],['20261002','佐藤 大輝','サトウ ダイキ','システムエンジニア'],['20261003','高橋 翔太','タカハシ ショウタ','システムエンジニア'],['20261004','田中 美咲','タナカ ミサキ','システムエンジニア'],['20261005','伊藤 悠真','イトウ ユウマ','システムエンジニア'],['20261006','渡辺 楓','ワタナベ カエデ','システムエンジニア']] as $row)$students[]=Student::firstOrCreate(['student_number'=>$row[0]],['name'=>$row[1],'kana'=>$row[2],'course'=>$row[3],'grade_year'=>2,'class_name'=>'1組','status'=>'在籍中']);
        $teacher=User::where('email','teacher@sansun.test')->firstOrFail();$teacher2=User::where('email','teacher2@sansun.test')->firstOrFail();
        $subjects=[
            Subject::firstOrCreate(['code'=>'WEB201'],['name'=>'Webアプリ開発','year'=>2026,'term'=>'前期','course'=>'システムエンジニア','class_name'=>'2年1組','teacher_id'=>$teacher->id,'deadline'=>'2026-08-31 23:59:00','attendance_weight'=>50,'attitude_weight'=>30,'assignment_weight'=>20]),
            Subject::firstOrCreate(['code'=>'DB201'],['name'=>'データベース演習','year'=>2025,'term'=>'後期','course'=>'システムエンジニア','class_name'=>'1年1組','teacher_id'=>$teacher2->id,'is_finalized'=>true,'finalized_at'=>now()->subMonths(5),'attendance_weight'=>50,'attitude_weight'=>30,'assignment_weight'=>20]),
        ];
        $values=[[96,9,9],[90,8,8],[98,10,9],[null,7,6],[85,7,7],[70,4,4]];
        foreach($subjects as $index=>$subject){foreach($students as $i=>$student){Enrollment::firstOrCreate(['student_id'=>$student->id,'subject_id'=>$subject->id]);$row=$index===0?$values[$i]:[max(60,92-$i*4),max(5,9-$i%4),max(5,8-$i%3)];$grade=Grade::firstOrCreate(['student_id'=>$student->id,'subject_id'=>$subject->id],['attendance_rate'=>$row[0],'attitude'=>$row[1],'assignment'=>$row[2]]);$this->calculate($grade,$subject);}}
    }
    private function calculate(Grade $grade,Subject $subject):void
    {
        if($grade->attendance_rate===null||$grade->attitude===null||$grade->assignment===null)return;
        $score=(int)round($grade->attendance_rate*$subject->attendance_weight/100+$grade->attitude*10*$subject->attitude_weight/100+$grade->assignment*10*$subject->assignment_weight/100);
        $grade->update(['score'=>$score,'evaluation'=>$score>=90?'秀':($score>=80?'優':($score>=70?'良':($score>=60?'可':'不可')))]);
    }
}
