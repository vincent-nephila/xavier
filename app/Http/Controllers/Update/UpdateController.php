<?php

namespace App\Http\Controllers\Update;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateController extends Controller
{
    function updatehsconduct(){
        $quarters = \App\CtrQuarter::first();
        
        $hsgrades = DB::Select("select * from grade1 where SY_EFFECTIVE = '2016' and QTR = $quarters->qtrperiod");
        foreach($hsgrades as $hsgrade){
            $newconduct = new \App\ConductRepo;
            $newconduct->OSR = $hsgrade->obedience;
            $newconduct->DPT = $hsgrade->deportment;
            $newconduct->PTY =$hsgrade->piety;
            $newconduct->DI = $hsgrade->diligence;
            $newconduct->PG = $hsgrade->positive;
            $newconduct->SIS = $hsgrade->sociability;
            $newconduct->qtrperiod = $hsgrade->QTR;
            $newconduct->schoolyear = $hsgrade->SY_EFFECTIVE;
            $newconduct->idno=$hsgrade->SCODE;
            $newconduct->save();
            $this->updateconduct($hsgrade->SCODE, 'OSR', $hsgrade->obedience, $hsgrade->QTR, '2016');
            $this->updateconduct($hsgrade->SCODE, 'DPT', $hsgrade->deportment, $hsgrade->QTR, '2016');
            $this->updateconduct($hsgrade->SCODE, 'PTY', $hsgrade->piety, $hsgrade->QTR, '2016');
            $this->updateconduct($hsgrade->SCODE, 'DI', $hsgrade->diligence, $hsgrade->QTR, '2016');
            $this->updateconduct($hsgrade->SCODE, 'PG', $hsgrade->positive, $hsgrade->QTR, '2016');
            $this->updateconduct($hsgrade->SCODE, 'SIS', $hsgrade->sociability, $hsgrade->QTR, '2016');
        }
    }
   
    public function updateconduct($idno,$ctype,$cvalue,$qtrperiod,$schoolyear){
          if(strlen($idno)==5){
            $idno = "0".$idno;
        }   
          if(!is_null($cvalue) || $cvalue!=""){  
            switch($qtrperiod){
            case 1:
                $qtrname = "first_grading";
                break;
            case 2:
                $qtrname="second_grading";
                break;
            case 3:
                $qtrname="third_grading";
                break;
            case 4:
                $qtrname="fourth_grading";
        }
        
        $cupate = \App\Grade::where('idno',$idno)->where('subjectcode',$ctype)->where('schoolyear',$schoolyear)->first();
        if(count($cupate)>0){
        $cupate->$qtrname=$cvalue;
        $cupate->update();
        }
          }
        }
        
        public function updatehsgrade(){
            $sy = \App\ctrSchoolYear::first();
            $quarters = \App\CtrQuarter::first();
            //$grades = DB::connection('dbti2prod')->Select("select * from grade where SY_EFFECTIVE = $sy->schoolyear and QTR = $quarters->qtrperiod");
            $grades = DB::Select("select * from grade where SY_EFFECTIVE = $sy->schoolyear and QTR = $quarters->qtrperiod");
            foreach($grades as $grade){
             $this->updatehs($grade->SCODE, $grade->SUBJ_CODE, $grade->GRADE_PASS1, $grade->QTR);
            }
        }
        
        public function updatehs($idno, $subjectcode, $grade,$qtrperiod){
            switch($qtrperiod){
            case 1:
                $qtrname = "first_grading";
                break;
            case 2:
                $qtrname="second_grading";
                break;
            case 3:
                $qtrname="third_grading";
                break;
            case 4:
                $qtrname="fourth_grading";
                
        }
        //$check = \App\Status::where('idno',$idno)->where('department','Junior High School')->first();
        $check = \App\Status::where('idno',$idno)->first();
        //if(count($check) != 0 && $subjectcode =='MAPEH'){
        $withrecord = \App\SubjectRepo::where('qtrperiod',$qtrperiod)->where('idno',$idno)->where('subjectcode',$subjectcode)->exists();
        if(!$withrecord){
            if(count($check) != 0 ){
            $newgrade = \App\Grade::where('idno',$idno)->where('subjectcode',$subjectcode)->first();

            if(count($newgrade)>0){
            $newgrade->$qtrname=$grade;
            $newgrade->update();
            }
            $loadgrade = new \App\SubjectRepo;
            $loadgrade->idno=$idno;
            $loadgrade->subjectcode=$subjectcode;
            $loadgrade->grade=$grade;
            $loadgrade->qtrperiod=$qtrperiod;
            $loadgrade->schoolyear='2016';
            $loadgrade->save();
            }
            }
        }
        function checkno(){
            $idnos=DB::Select("select * from grade2");
            return view('checkno',compact('idnos'));
        }
        public function updatehsattendance(){
            $dayahs = DB::Select("Select * from grade2 where SUBJ_CODE = 'DAYA'");
            foreach($dayahs as $daya){
                $updayp = \App\Grade::where('idno',$daya->SCODE)->where('subjectcode','DAYP')->first();
               if(count($updayp)>0){
                $updayp->first_grading = 48 - $daya->GRADE_PASS1;
                $updayp->update();
               }
            }
        }
        
        function updateacctcode(){
            $updatedbs = DB::Select("select * from crsmodification");
            foreach($updatedbs as $updatedb){
                $updatecrs = \App\Credit::where('receipt_details',$updatedb->receipt_details)->get();
                foreach($updatecrs as $updatecr){
                    $crs = \App\Credit::find($updatecr->id);
                    $crs->acctcode = $updatedb->acctcode;
                    $crs->update();
                }
                
            }
            return "Done";
        }
        
        function updatecashdiscount(){
            $cashdiscounts = \App\Dedit::where('paymenttype','4')->get();
            if(count($cashdiscounts)>0){
                foreach($cashdiscounts as $cashdiscount){
                    $discountname = \App\Discount::where('idno',$cashdiscount->idno)->first();
                    $dname="Plan Discount";
                     if(count($discountname)>0){
                     $dname = $discountname->description;
                    }
                    $cashdiscount->acctcode = $dname;
                    $cashdiscount->update();
                }
                return "done updating";
            }
        }
        function prevgrade(){
            $sy = "2012";
            $students = DB::connection('dbti2test')->select("select distinct scode from grade_report where SY_EFFECTIVE = '$sy'");
            foreach($students as $student){
                $this->migrategrade($student->scode,$sy);
            }
            //$this->migrategrade("021067",$sy);
        }
        function migrategrade($scode,$sy){
                
                do{
                    if(strlen($scode) < 6){
                        $scode = "0".$scode;
                    }
                }while(strlen($scode) < 6);
              
            $hsgrades = DB::connection('dbti2test')->select("select * from grade_report "
                    . "where SY_EFFECTIVE = '$sy'"
                    . "and SCODE =".$scode);

            foreach($hsgrades as $grade){
                if($grade->GR_YR == 'I'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                }
                else if($grade->GR_YR == 'II'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ART,"ART");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                }
                else if($grade->GR_YR == 'III'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ART,"ART");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->WORK,"WORK");
                }
                else if($grade->GR_YR == 'IV'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ART,"ART");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HEK,"HEK");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->WORK,"WORK");
                }
                else if($grade->GR_YR == 'V'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ART,"ART");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HEK,"HEK");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->WORK,"WORK");
                }
                else if($grade->GR_YR == 'VI'){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CL,"CL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ART,"ART");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HEK,"HEK");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM1,"COM1");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->WORK,"WORK");
                }
                else if($grade->GR_YR == 1){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AT_MT,"AT/MT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM2,"COM2");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->DRAF,"DRAF");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ET_ELX,"ET_ELX");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SS,"SS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->VE,"VE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->RHGP,"RHGP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HPE,"H&PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                }            
                else if($grade->GR_YR == 2){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AT_MT,"AT/MT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->COM2,"COM2");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->DRAF,"DRAF");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ET_ELX,"ET_ELX");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SS,"SS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->VE,"VE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->RHGP,"RHGP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HPE,"H&PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->STAT,"STAT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ALGEB,"ALGEB");
                }            
                else if($grade->GR_YR == 3){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->eTEX,"eTEX");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AMT,"AMT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->DT,"DT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CT,"CT");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CADD,"CADD");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->AP,"AP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->VE,"VE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->RHGP,"RHGP");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->PE,"PE");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");
                }            
                else if($grade->GR_YR == 4){
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CADD,"CADD");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ALGEB,"ALGEB");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MUS,"MUS");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->RHGP,"RHGP");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SC,"SC");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SHOP,"SHOP");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->TECH,"TECH");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->MATH,"MATH");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->HPE,"H&PE");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->GMRC,"GMRC");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->FIL,"FIL");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->ENGL,"ENGL");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->CAT,"CAT");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->VE,"VE");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->SS,"SS");//
                    $this->savegrade($scode,$sy,$grade->QTR,$grade->GR_YR,$grade->SECTION,$grade->TRIGO,"TRIGO");//
                }                    
            }
            
        }
        
        function savegrade($scode,$sy,$qtr,$level,$section,$score,$subj){
                $check = $this->check($scode,$subj,$sy);
                if(empty($check)){
                    $subjects = DB::connection('dbti2test')->select("Select subj_card,class from subject_updated where subj_code = '$subj'");
                    $orders = DB::connection('dbti2test')->select("Select hs_subj_order,gs_subj_order from subject where subj_code = '$subj'");
                    $record = new \App\Grade();
                    $record->idno = $scode;
                    $record->level = $this->changegrade($level);
                    $record->subjectcode = $subj;
                    $record->section = $section;
                    
                    if($level == 1 ||$level == 2||$level == 3||$level == 4){
                        foreach($orders as $order){
                            $record->sortto = $order->hs_subj_order;
                        }
                    }else{
                        foreach($orders as $order){
                            $record->sortto = $order->gs_subj_order;
                        }
                    }
                    
                    foreach($subjects as $subject){
                    $record->subjectname = $subject->subj_card;
                    }
                    $record->subjecttype = $this->settype($subject->class);
                    if($qtr == 1){
                        $record->first_grading = $score;
                    }else if($qtr == 2){
                        $record->second_grading = $score;
                    }else if($qtr == 3){
                        $record->third_grading = $score;
                    }else if($qtr == 4){
                        $record->fourth_grading = $score;
                    }  
                    $record->schoolyear = $sy;
                    $record->save();
                }else{
                    $record = \App\Grade::where('idno',$scode)->where('subjectcode',$subj)->where('schoolyear',$sy)->first();
                    if($qtr == 1){
                        $record->first_grading = $score;
                    }else if($qtr == 2){
                        $record->second_grading = $score;
                    }else if($qtr == 3){
                        $record->third_grading = $score;
                    }else if($qtr == 4){
                        $record->fourth_grading = $score;
                    }        
                    $record->save();
                }
        }
        
        function settype($subjcode){
            $code = 4;
            if($subjcode == 'A'){
                $code = 0;
            }
            if($subjcode == 'T'){
                $code = 1;
            }
            if($subjcode == 'C'){
                $code = 3;
            }
            
            return $code;
        }
        
        function check($scode,$subj,$sy){
            $result = \App\Grade::where('idno',$scode)->where('subjectcode',$subj)->where('schoolyear',$sy)->first();
            
            return $result;
        }
        
        function changegrade($level){
            if($level == 'I'){
                $newlevel = "Grade 1";
            }
            else if($level == 'II'){
                $newlevel = "Grade 2";
            }
            else if($level == 'III'){
                $newlevel = "Grade 3";
            }
            else if($level == 'IV'){
                $newlevel = "Grade 4";
            }
            else if($level == 'V'){
                $newlevel = "Grade 5";
            }
            else if($level == 'VI'){
                $newlevel = "Grade 6";
            }
            else if($level == 1){
                $newlevel = "Grade 7";
            }            
            else if($level == 2){
                $newlevel = "Grade 8";
            }            
            else if($level == 3){
                $newlevel = "Grade 9";
            }            
            else if($level == 4){
                $newlevel = "Grade 10";
            }
            
            return $newlevel;
        }
}
