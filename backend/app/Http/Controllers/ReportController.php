<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function csv(Request $request):StreamedResponse
    {
        $this->staffOnly($request);$grades=$this->query($request)->get();
        return response()->streamDownload(function()use($grades){$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['年度','学期','科目','学籍番号','氏名','コース','出席率','授業態度','課題評価','総合点','評価']);foreach($grades as $g)fputcsv($out,[$g->subject->year,$g->subject->term,$g->subject->name,$g->student->student_number,$g->student->name,$g->student->course,$g->attendance_rate,$g->attitude,$g->assignment,$g->score,$g->evaluation]);fclose($out);},'grades.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function pdf(Request $request):Response
    {
        $this->staffOnly($request);$this->bootPdfLibrary();$grades=$this->query($request)->get();$title=$request->student_id?'個人成績表':'科目別成績表';
        $options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);$pdf=new \Dompdf\Dompdf($options);$pdf->setPaper('A4','portrait');$pdf->loadHtml(view('reports.grades',['grades'=>$grades,'title'=>$title])->render(),'UTF-8');$pdf->render();
        return response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="grades.pdf"']);
    }
    public function studentHistory(Request $request,Student $student)
    {
        if($request->user()->role==='teacher'){$grades=Grade::with('subject')->where('student_id',$student->id)->whereHas('subject',fn($q)=>$q->where('teacher_id',$request->user()->id))->get();}else{$grades=Grade::with('subject')->where('student_id',$student->id)->get();}
        return response()->json(['student'=>$student,'grades'=>$grades->sortByDesc(fn($g)=>$g->subject->year.$g->subject->term)->values()]);
    }
    private function query(Request $request){return Grade::with(['student','subject'])->when($request->subject_id,fn($q,$v)=>$q->where('subject_id',$v))->when($request->student_id,fn($q,$v)=>$q->where('student_id',$v))->when($request->year,fn($q,$v)=>$q->whereHas('subject',fn($s)=>$s->where('year',$v)))->when($request->term,fn($q,$v)=>$q->whereHas('subject',fn($s)=>$s->where('term',$v)))->when($request->course,fn($q,$v)=>$q->whereHas('student',fn($s)=>$s->where('course',$v)));}
    private function staffOnly(Request $request):void{if($request->user()->role!=='staff')abort(403,'専任職員のみ出力できます。');}
    private function bootPdfLibrary():void
    {
        if(class_exists(\Dompdf\Dompdf::class))return;
        $maps=['Dompdf\\'=>base_path('vendor/dompdf/dompdf/src/'),'FontLib\\'=>base_path('vendor/dompdf/php-font-lib/src/FontLib/'),'Svg\\'=>base_path('vendor/dompdf/php-svg-lib/src/Svg/'),'Masterminds\\'=>base_path('vendor/masterminds/html5/src/'),'Sabberworm\\CSS\\'=>base_path('vendor/sabberworm/php-css-parser/src/')];
        spl_autoload_register(function(string $class)use($maps){if($class==='Dompdf\\Cpdf'){require_once base_path('vendor/dompdf/dompdf/lib/Cpdf.php');return;}foreach($maps as $prefix=>$dir){if(str_starts_with($class,$prefix)){$path=$dir.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require_once $path;return;}}});
    }
}
