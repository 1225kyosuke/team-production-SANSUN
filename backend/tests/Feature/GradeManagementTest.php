<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(DatabaseSeeder::class); }

    private function login(string $email,string $password):string
    {
        return $this->postJson('/api/login',['email'=>$email,'password'=>$password])->assertOk()->json('token');
    }
    private function auth(string $token):array { return ['Authorization'=>'Bearer '.$token]; }

    public function test_login_role_and_single_session_are_enforced():void
    {
        $first=$this->login('teacher@sansun.test','Teacher123');$second=$this->login('teacher@sansun.test','Teacher123');
        $this->withHeaders($this->auth($first))->getJson('/api/me')->assertUnauthorized();
        $this->withHeaders($this->auth($second))->getJson('/api/me')->assertOk()->assertJsonPath('role','teacher');
    }
    public function test_teacher_only_sees_assigned_subjects_and_cannot_access_others():void
    {
        $token=$this->login('teacher@sansun.test','Teacher123');$subjects=$this->withHeaders($this->auth($token))->getJson('/api/subjects')->assertOk()->json();
        $this->assertNotEmpty($subjects);$this->assertTrue(collect($subjects)->every(fn($s)=>$s['teacher_id']===User::where('email','teacher@sansun.test')->value('id')));
        $other=Subject::where('teacher_id','!=',User::where('email','teacher@sansun.test')->value('id'))->firstOrFail();$this->withHeaders($this->auth($token))->getJson("/api/subjects/{$other->id}/grades")->assertForbidden();
    }
    public function test_weight_validation_grade_calculation_and_finalization_lock():void
    {
        $teacher=$this->login('teacher@sansun.test','Teacher123');$subject=Subject::where('code','MATH2-F')->firstOrFail();
        $this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/weights",['attendance_weight'=>50,'attitude_weight'=>30,'assignment_weight'=>10])->assertUnprocessable();
        $this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/weights",['attendance_weight'=>50,'attitude_weight'=>25,'assignment_weight'=>25])->assertOk();
        $grades=Grade::where('subject_id',$subject->id)->get()->map(fn($g)=>['id'=>$g->id,'attendance_rate'=>90,'attitude'=>8,'assignment'=>8])->all();
        $this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/grades",['grades'=>$grades])->assertOk()->assertJsonPath('grades.0.score',85)->assertJsonPath('grades.0.evaluation','優');
        $staff=$this->login('staff@sansun.test','Staff123');$this->withHeaders($this->auth($staff))->postJson("/api/subjects/{$subject->id}/finalize")->assertOk();
        $this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/grades",['grades'=>$grades])->assertStatus(423);
        $this->withHeaders($this->auth($staff))->postJson("/api/subjects/{$subject->id}/unfinalize")->assertOk();
    }
    public function test_staff_can_import_csv_and_export_csv_and_pdf():void
    {
        $token=$this->login('staff@sansun.test','Staff123');$path=tempnam(sys_get_temp_dir(),'students');file_put_contents($path,"student_number,name,course,grade_year,class_name,status\n20269999,試験 太郎,共通,1,1組,在籍中\n");
        $this->withHeaders($this->auth($token))->post('/api/csv/import',['type'=>'students','file'=>new \Illuminate\Http\UploadedFile($path,'students.csv','text/csv',null,true)])->assertOk();
        $subject=Subject::firstOrFail();$this->withHeaders($this->auth($token))->get("/api/reports/csv?subject_id={$subject->id}")->assertOk();$this->withHeaders($this->auth($token))->get("/api/reports/pdf?subject_id={$subject->id}")->assertOk()->assertHeader('content-type','application/pdf');
    }
    public function test_evaluation_boundaries_and_partial_csv_errors():void
    {
        $teacher=$this->login('teacher@sansun.test','Teacher123');$subject=Subject::where('code','MATH2-F')->firstOrFail();
        $this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/weights",['attendance_weight'=>100,'attitude_weight'=>0,'assignment_weight'=>0])->assertOk();
        $values=[59,60,69,70,80,90];$expected=['不可','可','可','良','優','秀'];$grades=Grade::where('subject_id',$subject->id)->orderBy('id')->get()->values()->map(fn($g,$i)=>['id'=>$g->id,'attendance_rate'=>$values[$i],'attitude'=>5,'assignment'=>5])->all();
        $response=$this->withHeaders($this->auth($teacher))->putJson("/api/subjects/{$subject->id}/grades",['grades'=>$grades])->assertOk();foreach($expected as $i=>$evaluation)$response->assertJsonPath("grades.{$i}.evaluation",$evaluation);
        $staff=$this->login('staff@sansun.test','Staff123');$before=\App\Models\Student::count();$path=tempnam(sys_get_temp_dir(),'invalid');file_put_contents($path,"student_number,name,course,grade_year\n20268888,正常 行,共通,1\n20268889,,共通,9\n");
        $this->withHeaders($this->auth($staff))->post('/api/csv/import',['type'=>'students','file'=>new \Illuminate\Http\UploadedFile($path,'invalid.csv','text/csv',null,true)])->assertOk()->assertJsonPath('count',1)->assertJsonStructure(['errors'=>[['line','field','reason']]]);$this->assertSame($before+1,\App\Models\Student::count());
    }

    public function test_user_password_setup_link_enables_login():void
    {
        $user=User::where('email','teacher2@sansun.test')->firstOrFail();$user->forceFill(['must_set_password'=>true,'password_setup_token_hash'=>hash('sha256','setup-token'),'password_setup_expires_at'=>now()->addHour()])->save();
        $this->postJson('/api/login',['email'=>$user->email,'password'=>'Teacher123'])->assertForbidden()->assertJsonPath('must_set_password',true);
        $this->postJson('/api/password/setup',['email'=>$user->email,'token'=>'setup-token','password'=>'NewPass123','password_confirmation'=>'NewPass123'])->assertOk();
        $this->postJson('/api/login',['email'=>$user->email,'password'=>'NewPass123'])->assertOk();
    }
}
