<?php

namespace App\Http\Controllers\Cashier;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class CashierController extends Controller
{
      public function __construct()
	{
		$this->middleware('auth');
	}
        
   function view($idno){
       if(\Auth::user()->accesslevel==env('USER_CASHIER') || \Auth::user()->accesslevel == env('USER_CASHIER_HEAD')){
       $student = \App\User::where('idno',$idno)->first();
       $status = \App\Status::where('idno',$idno)->first();  
       $reservation = 0;
       $totalprevious = 0;
       $ledgers = null;
       $dues = null;
       $penalty = 0;
       $totalmain = 0;
       $totalothers=0;

       //Get other added collection
       $matchfields = ["idno"=>$idno, "categoryswitch"=>env("OTHER_FEE")];
       $othercollections = \App\Ledger::where($matchfields)->get();
       //get total othercollection
       
       if(count($othercollections) > 0 ){
           foreach($othercollections as $othercollection){
               $totalothers = $totalothers + $othercollection->amount - $othercollection->payment - $othercollection->debitmemo;
           }
       }
       
       //get previous balance
       $previousbalances = DB::Select("select schoolyear, sum(amount)- sum(plandiscount)- sum(otherdiscount)
               - sum(debitmemo) - sum(payment) as amount from ledgers where idno = '$idno' 
               and categoryswitch >= '"  .env('PREVIOUS_ELEARNING_FEE') ."' group by schoolyear");
       if(count($previousbalances)>0){ 
       foreach($previousbalances as $prev){
            $totalprevious = $totalprevious + $prev->amount;
       }}
       
       //get reservation
       if(isset($status->status)){
           //if($status->status == "1"){
           $reservations = DB::Select("select amount as amount from advance_payments where idno = '$idno' and status = '0'");
           if(count($reservations)>0){
               foreach($reservations as $reserve)
               {
                   $reservation = $reservation + $reserve->amount;
               }
       }}
           
       //get current account
          if(isset($status->department)){
                $currentperiod = \App\ctrSchoolYear::where('department',$status->department)->first();  
                $ledgers = DB::Select("select sum(amount) as amount, sum(plandiscount) as plandiscount, sum(otherdiscount) as otherdiscount,
                sum(payment) as payment, sum(debitmemo) as debitmemo, receipt_details, schoolyear, period, categoryswitch from ledgers where
                idno = '$idno' and categoryswitch <= '10' group by receipt_details, schoolyear, period, categoryswitch order by categoryswitch");
               
                if(count($ledgers)>0){
                    foreach($ledgers as $ledger){
                        if($ledger->categoryswitch <= '6'){
                        $totalmain=$totalmain+$ledger->amount - $ledger->plandiscount -$ledger->otherdiscount - $ledger->debitmemo - $ledger->payment;
                        }
                        
                        }
                }
                
                $dues = DB::Select("select sum(amount) - sum(plandiscount) - sum(otherdiscount)
                - sum(payment)- sum(debitmemo) as balance, sum(plandiscount) + sum(otherdiscount) as discount, duedate, duetype from ledgers where
                idno = '$idno' and categoryswitch <= '6' group by duedate, duetype");
          
                /*foreach($dues as $due){
                if(((strtotime(date('Y-m-d'))/(60*60*24)) - strtotime($due->duedate)/(60*60*24)) > 1){
                   $penalty = $penalty + ($due->balance * 0.05);
                }
               }
       
           if($penalty > 0 && $penalty < 250){
               $penalty = 250;
           }*/
                
                $matchfields=["idno"=>$idno,"categoryswitch"=>env('PENALTY_CHARGE')];
                $penalties = \App\Ledger::where($matchfields)->get();
                if(count($penalties)>0){
                    foreach($penalties as $pen){
                        $penalty= $penalty + $pen->amount - $pen->debitmemo - $pen->otherdiscount - $pen->payment;
                    }
                }
           
        } 
           
           //history of payments
           $debits = DB::SELECT("select * from dedits where idno = '" . $idno . "' and "
                   . "paymenttype <= '2' order by transactiondate");
        
           $debitdms = DB::SELECT("select * from dedits where idno = '" . $idno . "' and "
                   . "paymenttype = '3' order by transactiondate");
           return view('cashier.studentledger',  compact('debitdms','debits','penalty','totalmain','totalprevious','previousbalances','othercollections','student','status','ledgers','reservation','dues','totalothers'));
           
       }   
       
   }
   
  

    function setStatus($idno){
        $newstatus = \App\User::where('idno',$idno)->first();
        if($newstatus->status < '2'){
            $newstatus->status = '2';
            $newstatus->update();
        }
        
        return true;
    }
    
    function getRefno(){
         $newref= \Auth::user()->id . \Auth::user()->reference_number;
         return $newref;
    }
    
    function getOR(){
        //$user = \App\User::where('idno', Auth::user()->idno )->first();
        $receiptno = \Auth::user()->receiptno;
        return $receiptno;
       
    }
    
    function setreceipt($id){
       //return  $id = Auth::user()->id;
       $receiptno = \Auth::user()->receiptno;
       return view('cashier.setreceipt',compact('id','receiptno'));
    }
    
    function setOR(Request $request){
     $receiptno = \App\User::where('id', \Auth::user()->id)->first();
     $receiptno->receiptno = $request->receiptno;
     $receiptno->save();
     return redirect('/');
    }
    
     function payment(Request $request){
            $account=null;
            $orno = $this->getOR();
            $refno = $this->getRefno();
            $discount = 0;
            $change = 0;
          
            $this->reset_or();
            if($request->totaldue > 0 ){
            $totaldue = $request->totaldue;       
            //$accounts = \App\Ledger::where('idno',$request->idno)->where('categoryswitch','<=','6')->orderBy('categoryswitch')->get();
                $accounts = DB::SELECT("select * from ledgers where idno = '".$request->idno."' and categoryswitch <= '6' "
                     . " and (amount - payment - debitmemo - plandiscount - otherdiscount) > 0 order By duedate, categorySwitch");    
                    foreach($accounts as $account){
                    
                        $balance = $account->amount - $account->payment - $account->plandiscount - $account->otherdiscount - $account->debitmemo;
                       
                            if($balance < $totaldue){
                                $discount = $discount + $account->plandiscount + $account->otherdiscount;
                                $updatepay = \App\Ledger::where('id',$account->id)->first();
                                $updatepay->payment = $updatepay->payment + $balance;
                                $updatepay->save();
                                $totaldue = $totaldue - $balance;
                                $this->credit($request->idno, $account->id, $refno, $orno, $account->amount-$account->payment-$account->debitmemo);
                            } else {
                                $updatepay = \App\Ledger::where('id',$account->id)->first();
                                $updatepay->payment = $updatepay->payment + $totaldue;
                                    $updatepay->save();
                                    if($totaldue==$balance){
                                    $discount = $discount + $account->plandiscount + $account->otherdiscount;    
                                    $this->credit($request->idno, $account->id, $refno, $orno, $account->amount -$account->payment - $account->debitmemo);
                                    }else{      
                                    $this->credit($request->idno, $account->id, $refno, $orno, $totaldue);
                                    }
                                $totaldue = 0;
                                break;
                            }
                    }
             
                    $this->changestatatus($request->idno, $request->reservation);
                        if($request->reservation > 0){
                        $this->debit_reservation_discount($request->idno,env('DEBIT_RESERVATION','Reservation') , $request->reservation);
                        $this->consumereservation($request->idno);
                        }
            }   
            
            if($request->previous > 0 ){
                $previous = $request->previous;
                $updateprevious = DB::SELECT("select * from ledgers where idno = '".$request->idno."' and categoryswitch >= '11' "
                         . " and amount - payment - debitmemo - plandiscount - otherdiscount > 0 order By categoryswitch");
                    foreach($updateprevious as $up){
                    $balance = $up->amount - $up->payment - $up->plandiscount - $up->otherdiscount - $up->debitmemo;
                        if($balance < $previous ){
                        $updatepay = \App\Ledger::where('id',$up->id)->first();
                        $updatepay->payment = $updatepay->payment + $balance;
                        $updatepay->save();
                        $previous = $previous - $balance;
                        $this->credit($request->idno, $up->id, $refno, $orno, $up->amount);
                        } else {
                        $updatepay = \App\Ledger::where('id',$up->id)->first();
                        $updatepay->payment = $updatepay->payment + $previous;
                        $updatepay->save();
                        $this->credit($request->idno, $up->id, $refno, $orno, $previous);
                        $previous = 0;
                        break;
                        }
                    }   
                }
            
            if(isset($request->other)){
                    foreach($request->other as $key=>$value){
                    $updateother = \App\Ledger::find($key);
                    $updateother->payment = $updateother->payment + $value;
                    $updateother->save();
                    $this->credit($updateother->idno, $updateother->id, $refno, $orno, $value);
                    }
                
                    $statusnow =  \App\Status::where('idno',$request->idno)->where('department','TVET')->first();
                    if(count($statusnow)>0){
                        if($statusnow->status=="1"){
                        $statusnow->status="2";
                        $statusnow->update(); 
                        }
                    }
                }
            
            if($request->penalty > 0){
                $penalty = $request->penalty;
                $updatepenalties = DB::SELECT("select * from ledgers where idno = '".$request->idno."' and categoryswitch = '". env('PENALTY_CHARGE'). "' "
                     . " and amount - payment - debitmemo - plandiscount - otherdiscount > 0");
                    foreach($updatepenalties as $pen){
                    $balance = $pen->amount - $pen->payment = $pen->plandiscount - $pen-> otherdiscount - $pen->debitmemo;
                        if($balance < $penalty ){
                        $updatepay = \App\Ledger::where('id',$pen->id)->first();
                        $updatepay->payment = $updatepay->payment + $balance;
                        $updatepay->save();
                        $penalty = $penalty - $balance;
                        $this->credit($request->idno, $pen->id, $refno, $orno, $pen->amount);
                        } else {
                        $updatepay = \App\Ledger::where('id',$pen->id)->first();
                        $updatepay->payment = $updatepay->payment + $penalty;
                        $updatepay->save();
                        $this->credit($request->idno, $pen->id, $refno, $orno, $penalty);
                        $penalty = 0;
                        break;
                        }
                    }
                }
                
            $bank_branch = "";
            $check_number = "";
            
            //if($request->receivecheck > "0"){
            $bank_branch=$request->bank_branch; 
            $check_number = $request->check_number;
            $iscbc = 0;
                if($request->iscbc =="cbc"){
                    $iscbc = 1;
                }
            $depositto = $request->depositto;    
            $totalcash = $request->receivecash - $request->change;
            $receiveamount = $request->receivecash ;
            $remarks=$request->remarks;
            $this->debit($request->idno,env('DEBIT_CHECK') , $bank_branch, $check_number,$totalcash, $request->receivecheck, $iscbc,$depositto,$receiveamount,$remarks);
            //}
            
            
           //if($request->receivecash > "0){
            //$this->debit($request->idno,env('DEBIT_CASH') , $bank_branch, $check_number, $request->receiveamount, '0');
            //}
                    
            if($discount > 0 ){
                $discountname="Plan Discount";
                $schoolyear = \App\CtrRefSchoolyear::first()->schoolyear;
                $disc = \App\Discount::where('idno',$request->idno)->first()->description;
                if(count($disc)>0){
                    $discountname = $disc;
                }
              $this->debit_reservation_discount($request->idno,env('DEBIT_DISCOUNT') , $discount, $discountname);
                  
          }
            
          
          return $this->viewreceipt($refno, $request->idno);
          //return redirect(url('/viewreceipt',array($refno,$request->idno)));  
          //return view("cashier.payment", compact('previous','idno','reservation','totaldue','totalother','totalprevious','totalpenalty'));
   }

   function changestatatus($idno, $reservation){
   $status = \App\Status::where('idno',$idno)->first();    
       if(count($status)> 0 ){
           if($status->status == "1"){
               if($reservation == "0"){
               $this->addreservation($idno);
               }
               $status->status='2';
               $status->date_enrolled=Carbon::now();
               $status->save();
           }
       }
   }
   
  function addreservation($idno){
      $status=  \App\Status::where('idno',$idno)->first();
      $addcredit = new \App\Credit;
      $addcredit->idno = $idno;
      $addcredit->transactiondate = Carbon::now();
      $addcredit->refno = $this->getRefno();
      $addcredit->receiptno = $this->getOR();
      $addcredit->categoryswitch = "9";
      $addcredit->acctcode = "Reservation";
      $addcredit->description = "Reservation";
      $addcredit->receipt_details = "Reservation";
      $addcredit->amount = "1000.00";
      if(isset($status->schoolyear)){
      $addcredit->schoolyear=$status->schoolyear;
      }
      $addcredit->postedby=\Auth::user()->idno;
      $addcredit->save();
      
      $adddebit = new \App\Dedit;
      $adddebit->idno = $idno;
      $adddebit->transactiondate = Carbon::now();
      $adddebit->paymenttype = '5';
      $adddebit->amount = "1000.00";
      $adddebit->refno = $this->getRefno();
      $adddebit->receiptno = $this->getOR();
      $adddebit->postedby = \Auth::user()->idno;
      if(isset($status->schoolyear)){
      $adddebit->schoolyear = $status->schoolyear;
      }
      $adddebit->save();
      
      $addreservation = new \App\AdvancePayment;
      $addreservation->idno = $idno;
      $addreservation->amount = "1000.00";
      $addreservation->refno = $this->getRefno();
      $addreservation->transactiondate=Carbon::now();
      $addreservation->postedby=\Auth::user()->idno;
      $addreservation->status = "1";
      $addreservation->save();
      
  } 
    function reset_or(){
        $resetor = \App\User::where('idno', \Auth::user()->idno)->first();
        $resetor->receiptno = $resetor->receiptno + 1;
        $resetor->reference_number = $resetor->reference_number + 1;
        $resetor->save();
    }
    
    function consumereservation($idno){
        $crs= \App\AdvancePayment::where('idno',$idno)->get();
        foreach($crs as $cr){
            $cr->status = "1";
            $cr->postedby = \Auth::user()->idno;
            $cr->save();
        }
    }
   
    function debit($idno, $paymenttype, $bank_branch, $check_number,$cashamount,$checkamount,$iscbc,$depositto,$receiveamount,$remarks){
        $student= \App\User::where('idno', $idno)->first();
        $debitaccount = new \App\Dedit;
        $debitaccount->idno = $idno;
        $debitaccount->transactiondate=Carbon::now();
        $debitaccount->refno=$this->getRefno();
        $debitaccount->receiptno = $this->getOR();
        $debitaccount->paymenttype = $paymenttype;
        $debitaccount->bank_branch = $bank_branch;
        $debitaccount->check_number = $check_number;
        $debitaccount->receivefrom = $student->lastname . ", " . $student->firstname . " " . $student->extensionname . " " .$student->middlename;
        $debitaccount->receiveamount=$receiveamount;
        $debitaccount->iscbc = $iscbc;
        $debitaccount->depositto = $depositto;
        $debitaccount->checkamount = $checkamount;
        $debitaccount->amount = $cashamount;
        $debitaccount->remarks = $remarks;
        $debitaccount->postedby=\Auth::user()->idno;
        $debitaccount->save();
        
    }
   
    function debit_reservation_discount($idno,$debittype,$amount,$discountname){
        $student = \App\User::where('idno',$idno)->first();
        $debitaccount = new \App\Dedit;
        $debitaccount->idno = $idno;
        $debitaccount->transactiondate=Carbon::now();
        $debitaccount->acctcode=$discountname;
        $debitaccount->refno=$this->getRefno();
        $debitaccount->receiptno = $this->getOR();
        $debitaccount->paymenttype = $debittype;
        $debitaccount->receivefrom = $student->lastname . ", " . $student->firstname . " " . $student->extensionname . " " .$student->middlename;
        $debitaccount->amount = $amount;    
        $debitaccount->postedby=\Auth::user()->idno;
        $debitaccount->save();
        
    }
  
   function credit($idno, $idledger, $refno, $receiptno, $amount){
       $ledger = \App\Ledger::find($idledger);
       $newcredit = new \App\Credit;
       $newcredit->idno=$idno;
       $newcredit->transactiondate = Carbon::now();
       $newcredit->referenceid = $idledger;
       $newcredit->refno = $refno;
       $newcredit->receiptno=$receiptno;
       $newcredit->categoryswitch = $ledger->categoryswitch;
       $newcredit->acctcode = $ledger->acctcode;
       $newcredit->description = $ledger->description;
       $newcredit->receipt_details = $ledger->receipt_details;
       $newcredit->duedate=$ledger->duedate;
       $newcredit->amount=$amount;
       $newcredit->schoolyear=$ledger->schoolyear;
       $newcredit->period=$ledger->period;
       $newcredit->postedby=\Auth::user()->idno;
       $newcredit->save();
       
   } 
   
   function viewreceipt($refno, $idno){
       $student = \App\User::where('idno',$idno)->first();
       $status= \App\Status::where('idno',$idno)->first();
       $debits = \App\Dedit::where('refno',$refno)->get();
       $debit_discount = \App\Dedit::where('refno',$refno)->where('paymenttype','4')->first();
       $debit_reservation = \App\Dedit::where('refno',$refno)->where('paymenttype','5')->first();
       $debit_cash = \App\Dedit::where('refno',$refno)->where('paymenttype','1')->first();
       $debit_dm = \App\Dedit::where('refno',$refno)->where('paymenttype','3')->first();
       $credits = DB::Select("select sum(amount) as amount, receipt_details, transactiondate, sub_department from credits "
               . "where refno = '$refno' group by receipt_details, transactiondate, sub_department");
       $timeissued =  \App\Credit::where('refno',$refno)->first();
       $timeis=date('h:i:s A',strtotime($timeissued->created_at));
       $tdate = \App\Dedit::where('refno',$refno)->first();
       $posted = \App\User::where('idno',$tdate->postedby)->first();
       return view("cashier.viewreceipt",compact('posted','timeis','tdate','student','debits','credits','status','debit_discount','debit_reservation','debit_cash','debit_dm'));
       
   }
   
   
    function printreceipt($refno, $idno){
       $student = \App\User::where('idno',$idno)->first();
       $status= \App\Status::where('idno',$idno)->first();
       $debits = \App\Dedit::where('refno',$refno)->get();
       $debit_discount = \App\Dedit::where('refno',$refno)->where('paymenttype','4')->first();
       $debit_reservation = \App\Dedit::where('refno',$refno)->where('paymenttype','5')->first();
       $debit_cash = \App\Dedit::where('refno',$refno)->where('paymenttype','1')->first();
       $debit_check = \App\Dedit::where('refno',$refno)->where('paymenttype','2')->first();
       $debit_dm = \App\Dedit::where('refno',$refno)->where('paymenttype','3')->first();
       $credits = DB::Select("select sum(amount) as amount, receipt_details, transactiondate, sub_department from credits "
               . "where refno = '$refno' group by receipt_details, transactiondate, sub_department");
       $timeissued =  \App\Credit::where('refno',$refno)->first();
       $timeis=date('h:i:s A',strtotime($timeissued->created_at));
       $tdate = \App\Dedit::where('refno',$refno)->first();
       $posted = \App\User::where('idno',$tdate->postedby)->first();
       $pdf = \App::make('dompdf.wrapper');
       $pdf->setPaper([0, 0, 336, 440], 'portrait');
       $pdf->loadView("cashier.printreceipt",compact('posted','timeis','tdate','student','debits','credits','status','debit_discount','debit_reservation','debit_cash','debit_dm'));
       return $pdf->stream();
        
    
}

function otherpayment($idno){
    $student =  \App\User::where('idno',$idno)->first();
    $status = \App\Status::where('idno',$idno)->first();
    $acct_departments = DB::Select('Select * from ctr_acct_dept order by sub_department');
    $advances = \App\AdvancePayment::where("idno",$idno)->where("status",'2')->get();
    $advance=0;
    if(count($advances)>0){    
        foreach($advances as $adv){
           $advance=$advance+$adv->amount;
        }
    }
    $accounttypes = DB::Select("select distinct accounttype from ctr_other_payments order by accounttype");
    $paymentothers = DB::Select("select sum(amount) as amount, receipt_details from credits where idno ='" . $idno . "' and (categoryswitch = '7' OR categoryswitch = '9') and isreverse = '0' group by receipt_details");
    return view('cashier.otherpayment',compact('acct_departments','student','status','accounttypes','advance','paymentothers'));
}

    function othercollection(Request $request){
        $or = $this->getOR();
        $refno = $this->getRefno();
        $status = \App\Status::where('idno',$request->idno)->where('status','2')->first();
        $student=  \App\User::where('idno',$request->idno)->first();
        
        $this->reset_or();
        if($request->reservation > 0 ){
            $newreservation = new \App\AdvancePayment;
            $newreservation->idno = $request->idno;
            $newreservation->transactiondate = Carbon::now();
            $newreservation->refno = $refno;
            $newreservation->amount = $request->reservation;
            $newreservation->status = '2';
            $newreservation->postedby = \Auth::user()->idno;
            $newreservation->save();
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode="Reservation";
            $creditreservation->description="Reservation";
            $creditreservation->receipt_details = "Reservation";
            $creditreservation->amount = $request->reservation;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->save();     
        }
        
        if($request->amount1 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount1;
            $creditreservation->description=$request->particular1;
            $creditreservation->receipt_details = $request->particular1;
            $creditreservation->amount = $request->amount1;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department1;
            $creditreservation->save(); 
            
        }
        
           if($request->amount2 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount2;
            $creditreservation->description=$request->particular2;
            $creditreservation->receipt_details = $request->particular2;
            $creditreservation->amount = $request->amount2;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department2;
            $creditreservation->save(); 
            
        }
        
         if($request->amount3 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount3;
            $creditreservation->description=$request->particular3;
            $creditreservation->receipt_details = $request->particular3;
            $creditreservation->amount = $request->amount3;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department3;
            $creditreservation->save(); 
            
        }
         if($request->amount4 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount4;
            $creditreservation->description=$request->particular4;
            $creditreservation->receipt_details = $request->particular4;
            $creditreservation->amount = $request->amount4;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department4;
            $creditreservation->save(); 
            
        }
        
         if($request->amount5 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount5;
            $creditreservation->description=$request->particular5;
            $creditreservation->receipt_details = $request->particular5;
            $creditreservation->amount = $request->amount5;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department5;
            $creditreservation->save(); 
            
        }
        
         if($request->amount6 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount6;
            $creditreservation->description=$request->particular6;
            $creditreservation->receipt_details = $request->particular6;
            $creditreservation->amount = $request->amount6;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department6;
            $creditreservation->save(); 
            
        }
        
         if($request->amount7 > 0){
            $creditreservation = new \App\Credit;
            $creditreservation->idno = $request->idno;
            $creditreservation->transactiondate = Carbon::now();
            $creditreservation->refno = $refno;
            $creditreservation->receiptno = $or;
            $creditreservation->categoryswitch = '9';
            $creditreservation->acctcode=$request->groupaccount7;
            $creditreservation->description=$request->particular7;
            $creditreservation->receipt_details = $request->particular7;
            $creditreservation->amount = $request->amount7;
            $creditreservation->postedby = \Auth::user()->idno;
            $creditreservation->sub_department = $request->acct_department7;
            $creditreservation->save(); 
            
        }
        
        //debit
        $iscbc = 0;
         if($request->iscbc =="cbc"){
                    $iscbc = 1;
                }
        $debit = new \App\Dedit;
        $debit->idno = $request->idno;
        $debit->transactiondate = Carbon::now();
        $debit->refno = $refno;
        $debit->receiptno = $or;
        $debit->paymenttype= "1";
        $debit->bank_branch=$request->bank_branch;
        $debit->check_number=$request->check_number;
        $debit->iscbc=$iscbc;
        $debit->amount = $request->totalcredit - $request->check;
        $debit->receiveamount = $request->cash;
        $debit->checkamount=$request->check;
        $debit->receivefrom=$student->lastname . ", " . $student->firstname . " " . $student->extensionname . " " .$student->middlename;
        $debit->depositto=$request->depositto;
        $debit->remarks=$request->remarks;
        $debit->postedby= \Auth::user()->idno;
        $debit->save();
        
        
        return $this->viewreceipt($refno, $request->idno);
        
        //return redirect(url('/viewreceipt',array($refno,$request->idno)));
    }
    
    function collectionreport($transactiondate){

        $matchfields = ['postedby'=>\Auth::user()->idno, 'transactiondate'=>$transactiondate];
        //$collections = \App\Dedit::where($matchfields)->get();
        $collections = DB::Select("select sum(dedits.amount) as amount, sum(dedits.checkamount) as checkamount, users.idno, users.lastname, users.firstname,"
                . " dedits.transactiondate, dedits.isreverse, dedits.receiptno, dedits.refno from users, dedits where users.idno = dedits.idno and"
                . " dedits.postedby = '".\Auth::user()->idno."' and dedits.transactiondate = '" 
                . $transactiondate . "' and dedits.paymenttype = '1' group by users.idno, dedits.transactiondate, users.lastname, users.firstname, dedits.isreverse,dedits.receiptno,dedits.refno order by dedits.refno" );
        //$collections = \App\User::where('postedby',\Auth::user()->idno)->first()->dedits->where('transactiondate',date('Y-m-d'))->get();
        return view('cashier.collectionreport', compact('collections','transactiondate'));
    }
    
    function printcollection($idno,$transactiondate){
        
         $matchfields = ['postedby'=>$idno, 'transactiondate'=>$transactiondate];
        //$collections = \App\Dedit::where($matchfields)->get();
        $collections = DB::Select("select sum(dedits.amount) as amount, sum(dedits.checkamount) as checkamount, users.idno, users.lastname, users.firstname,"
                . " dedits.transactiondate, dedits.isreverse, dedits.receiptno, dedits.refno from users, dedits where users.idno = dedits.idno and"
                . " dedits.postedby = '".\Auth::user()->idno."' and dedits.transactiondate = '" 
                . $transactiondate . "' and dedits.paymenttype = '1' group by users.idno, dedits.transactiondate, users.lastname, users.firstname, dedits.isreverse,dedits.receiptno,dedits.refno" );
        
        $teller=\Auth::user()->firstname." ". \Auth::user()->lastname;
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView("print.printcollection",compact('collections','transactiondate','teller'));
        return $pdf->stream();  
    }
    function cancell($refno,$idno){
        
        $credits = \App\Credit::where('refno',$refno)->get();
        foreach($credits as $credit){
          
         
         $ledger = \App\Ledger::find($credit->referenceid);
        
         if(isset($ledger->payment)){
         $ledger->payment = $ledger->payment - $credit->amount + $ledger->plandiscount + $ledger->otherdiscount;
         $ledger->save();
         }
         
         
         if($credit->description == "Reservation"){
             \App\AdvancePayment::where('refno',$refno)->delete();
         }
         }
        
         
        $matchfield=["refno"=>$refno,"paymenttype"=>"5"];
        $debitreservation = \App\Dedit::where($matchfield)->first();
        if(count($debitreservation)>0){
          $res=\App\AdvancePayment::where('idno',$idno)->where('status','1')->first();
          if(isset($res->status)){
          $res->status = '0';
          $res->save();//->update(['status'=>'0']);
         
        }
        }
        \App\Credit::where('refno',$refno)->update(['isreverse'=>'1','reversedate'=>  Carbon::now(), 'reverseby'=> \Auth::user()->idno]);
        \App\Dedit::where('refno',$refno)->update(['isreverse'=>'1']);
        
        return $this->view($idno);
        //return redirect(url('cashier',$idno));
    
    }
    
    function restore($refno,$idno){
        
        $credits = \App\Credit::where('refno',$refno)->get();
        foreach($credits as $credit){
        $ledger = \App\Ledger::find($credit->referenceid);
        if(isset($ledger->payment)){
        $ledger->payment = $ledger->payment + $credit->amount - $ledger->plandiscount - $ledger->otherdiscount;
        $ledger->save();
        }
         if($credit->description == "Reservation"){
            $res = new \App\AdvancePayment;
            $res->idno = $idno;
            $res->transactiondate = Carbon::now();
            $res->refno = $refno;
            $res->amount=$credit->amount;
            $debrest=  \App\Dedit::where('refno',$refno)->where('paymenttype','5')->first();
            if(count($debrest)>0){
            $res->status="1";    
            }else{
            $res->status="2";
            }
            $res->postedby = \Auth::user()->idno;
            $res->save();
         }
        }
        
       $debitreservation = \App\Dedit::where('refno',$refno)->where('paymenttype','5')->first();
        if(count($debitreservation)>0){
           
            \App\AdvancePayment::where('idno',$idno)->where('status','0')->update(['status'=>'1']);
        }
        
        \App\Credit::where('refno',$refno)->update(['isreverse'=>'0','reversedate'=>  '0000-00-00', 'reverseby'=> '']);
        \App\Dedit::where('refno',$refno)->update(['isreverse'=>'0']);
       // \App\AdvancePayment::where('refno',$refno)->where('idno',$idno)->update(['status' => '0']);
        return $this->view($idno);
        //return redirect(url('cashier',$idno));
    }
    
    function postencashment(Request $request){
        $refno = $this->getRefno();
        $encashment = new \App\Encashment;
        $encashment->transactiondate= Carbon::now();
        $encashment->refno = $refno;
        $encashment->payee = $request->payee;
        $encashment->whattype = $request->whattype;
        //$encashment->depositto = $request->depositto;
        $encashment->withdrawfrom =$request->withdrawfrom;
        $encashment->bank_branch=$request->bank_branch;
        $encashment->check_number=$request->check_number;
        $encashment->amount=$request->amount;
        $encashment->postedby = \Auth::user()->idno;
        $encashment->save();
        
        $resetor = \App\User::where('idno', \Auth::user()->idno)->first();
        $resetor->reference_number = $resetor->reference_number + 1;
        $resetor->save();
        return $this->viewencashmentdetail($encashment->refno);
        //return redirect(url('viewencashmentdetail',$encashment->refno));
        
        //return view('cashier.viewencashment',compact('encashment'));
        
    }
    
    function viewencashmentdetail($refno){
        $encashment = \App\Encashment::where('refno',$refno)->first();
        return view('cashier.viewencashment',compact('encashment'));
    }
    
    function encashment(){
        
        return view('cashier.encashment');
    }
    
    function encashmentreport(){
        $matchfields=['postedby'=>\Auth::user()->idno, 'transactiondate' => date('Y-m-d')];
        $encashmentreports = \App\Encashment::where($matchfields)->get();
        return view('cashier.viewencashmentreport',compact('encashmentreports'));
    }
    
    function printencashment($idno){
        
        $matchfields=['postedby'=>$idno, 'transactiondate' => date('Y-m-d')];
        $encashmentreports = \App\Encashment::where($matchfields)->get();
        $pdf = \App::make('dompdf.wrapper');
      
        $pdf->loadView("print.printencashment",compact('encashmentreports'));
       return $pdf->stream();
        
        
    }
    
    function reverseencashment($refno){
        $encashment = \App\Encashment::where('refno',$refno)->first();
        if($encashment->isreverse == '0'){
            $encashment->isreverse = '1';
        } else {
            $encashment->isreverse = '0';
        }
        $encashment->save();
        return $this->encashmentreport();
        
        //return redirect(url('encashmentreport'));
    }
    
    function previous($idno){
        $student = \App\User::where('idno',$idno)->first();
        $schoolyears = DB::Select("select distinct schoolyear from ledgers where idno = '$idno'");
        return view('cashier.previous',compact('student','schoolyears'));
    }
    function actualcashcheck($batch,$transactiondate){
        $cbcash=0;
        $cbcheck=0;
        $bpi1cash=0;
        $bpi1check=0;
        $bpi2cash=0;
        $bpi2check=0;
        $action="add";
        $totalissued=0;
        
        $actual = \App\ActualDeposit::where('postedby',\Auth::user()->idno)->where('transactiondate',$transactiondate)->where('batch',$batch)->first();
        
        if(count($actual)>0){
          $action="update";  
        }
        
        
        $chinabank = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" . $transactiondate . "' and paymenttype = '1' and "
                . " depositto = 'China Bank' and isreverse = '0' and batch='$batch'");
        
        if(count($chinabank)>0){
            foreach($chinabank as $cb){
                $cbcash = $cbcash + $cb->amount;
                $cbcheck = $cbcheck + $cb->checkamount;
            }
        }
        
        $bpi1 = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" .$transactiondate. "' and paymenttype = '1' and "
                . " depositto = 'BPI 1' and isreverse = '0' and batch='$batch'");
        
        if(count($bpi1)>0){
            foreach($bpi1 as $cb){
                $bpi1cash = $bpi1cash + $cb->amount;
                $bpi1check = $bpi1check + $cb->checkamount;
            }
        }
        
        $bpi2 = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" . $transactiondate. "' and paymenttype = '1' and "
                . " depositto = 'BPI 2' and isreverse = '0' and batch='$batch'");
        
        if(count($bpi2)>0){
            foreach($bpi2 as $cb){
                $bpi2cash = $bpi2cash + $cb->amount;
                $bpi2check = $bpi2check + $cb->checkamount;
            }
        }
        
        //$totalissued = $cbcash + $cbcheck + $bpi1cash + $bpi1check + $bpi2cash + $bpi2check;
        
        $encashments = DB::Select("select sum(amount) as amount, withdrawfrom from encashments where postedby = '" . \Auth::user()->idno. "' "
                . " and transactiondate = '". $transactiondate."' and isreverse = '0' group by withdrawfrom");
        
        $encashcbc=0;
        $encashbpi1=0;
        $encashbpi2=0;
        
        foreach($encashments as $encash){
            if($encash->withdrawfrom == "China Bank"){
                $encashcbc = $encashcbc + $encash->amount;
            }
            if($encash->withdrawfrom == "BPI 1"){
                $encashbpi1 = $encashbpi1 + $encash->amount;
            }
            if($encash->withdrawfrom == "BPI 2"){
                $encashbpi2 = $encashbpi2 + $encash->amount;
            }
        }
        
        $totalissued = $cbcash + $cbcheck + $bpi1cash + $bpi1check + $bpi2cash + $bpi2check;
        //$totalissued = $totalissued - $encashcbc - $encashbpi1 - $encashbpi2;
        return view('cashier.actualcashcheck',compact('chinabank','bpi1','bpi2',
                'chinabank1','encashments','transactiondate','cbcash','cbcheck','bpi1cash',
                'bpi1check','bpi2cash','bpi2check','encashcbc','encashbpi1','encashbpi2','actual','action','transactiondate','totalissued','batch'));
        
    }
    function nonstudent(){
       
        $accounttypes = DB::Select("select distinct accounttype from ctr_other_payments");
        return view('cashier.nonstudent', compact('accounttypes'));
        
    }
    
    function postnonstudent(Request $request){
       $refno = $this->getRefno();
       $or = $this->getOR(); 
       $newcredit = new \App\Credit;
       $newcredit->idno="9999999";
       $newcredit->transactiondate = Carbon::now();
       $newcredit->referenceid = $idledger;
       $newcredit->refno = $refno;
       $newcredit->receiptno=$or;
       $newcredit->categoryswitch = '7';
       $newcredit->acctcode = 'Others';
       $newcredit->description = $request->particular;
       $newcredit->receipt_details = $request->particular;
       $newcredit->amount=$request->amount;
       $newcredit->postedby=\Auth::user()->idno;
       $newcredit->save();
        
       $debit = new \App\Dedit;
        $debit->idno = "9999999";
        $debit->transactiondate = Carbon::now();
        $debit->refno = $refno;
        $debit->receiptno = $or;
        $debit->paymenttype= "1";
        $debit->bank_branch=$request->bank_branch;
        $debit->check_number=$request->check_number;
        $debit->iscbc=$iscbc;
        $debit->amount = $request->cash;
        $debit->checkamount=$request->check;
        $debit->receivefrom=$student->lastname . ", " . $student->firstname . " " . $student->extensionname . " " .$student->middlename;
        $debit->depositto=$request->depositto;
        $debit->postedby= \Auth::user()->idno;
        $debit->save();
        
    }
    function checklist($trandate){
        $checklists = DB::Select("select bank_branch, check_number, sum(checkamount) as checkamount, receiptno, receivefrom  from dedits "
                . "where paymenttype = '1' and isreverse = '0' and postedby = '" . \Auth::user()->idno . "'"
                . " and transactiondate = '" .$trandate . "' group by bank_branch, check_number, receiptno, receivefrom order by transactiondate, refno");
      //$checklist = DB::Select("select * from dedits");
        
        return view('cashier.checklist', compact('checklists')); 
    }
    function postactual(Request $request){
        if($request->action1 == "add"){
            $postactual = new \App\ActualDeposit;
            $postactual->postedby = \Auth::user()->idno;
            $postactual->transactiondate = $request->transactiondate;
            $postactual->cbccash = $request->actualcbccash;
            $postactual->cbccheck = $request->actualcbccheck;
            $postactual->bpi1cash = $request->actualbpi1cash;
            $postactual->bpi1check = $request->actualbpi1check;
            $postactual->bpi2cash = $request->actualbpi2cash;
            $postactual->bpi2check = $request->actualbpi2check;
            $postactual->encashcbc = $request->actualencashcbc;
            $postactual->encashbpi1 = $request->actualencashbpi1;
            $postactual->encashbpi2 = $request->actualencashbpi2;
            $postactual->variance = $request->actualcbccash + $request->actualcbccheck +
                    $request->actualbpi1cash + $request->actualbpi1check +
                    $request->actualbpi2cash + $request->actualencashcbc + $request->actualencashbpi1 +
                    $request->actualencashbpi2 + $request->actualbpi2check - $request->totalissued;
            
            $postactual->save();
        }
          else  
          {
          $postactual = \App\ActualDeposit::where('postedby',\Auth::user()->idno)->where('transactiondate',$request->transactiondate)->where('batch',$request->batch)->first();   
            $postactual->postedby = \Auth::user()->idno;
            $postactual->transactiondate = $request->transactiondate;
            $postactual->cbccash = $request->actualcbccash;
            $postactual->cbccheck = $request->actualcbccheck;
            $postactual->bpi1cash = $request->actualbpi1cash;
            $postactual->bpi1check = $request->actualbpi1check;
            $postactual->bpi2cash = $request->actualbpi2cash;
            $postactual->bpi2check = $request->actualbpi2check;
            $postactual->encashcbc = $request->actualencashcbc;
            $postactual->encashbpi1 = $request->actualencashbpi1;
            $postactual->encashbpi2 = $request->actualencashbpi2;
            $postactual->variance = $request->actualcbccash + $request->actualcbccheck +
            $request->actualbpi1cash + $request->actualbpi1check +
            $request->actualbpi2cash + $request->actualbpi2check - $request->totalissued;
               $postactual->update();
        }
        return $this->actualcashcheck($request->batch, $request->transactiondate);
        //return redirect(url('actualcashcheck',array($request->batch,$request->transactiondate)));
    }
    
    function printactualcash($transactiondate){
        $cbcash=0;
        $cbcheck=0;
        $bpi1cash=0;
        $bpi1check=0;
        $bpi2cash=0;
        $bpi2check=0;
        $action="add";
        $totalissued=0;
        $actual = \App\ActualDeposit::where('postedby',\Auth::user()->idno)->where('transactiondate',$transactiondate)->first();
        
        if(count($actual)>0){
          $action="update";  
        }
        
        
        $chinabank = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" . $transactiondate . "' and paymenttype = '1' and "
                . " depositto = 'China Bank' and isreverse = '0'");
        
        if(count($chinabank)>0){
            foreach($chinabank as $cb){
                $cbcash = $cbcash + $cb->amount;
                $cbcheck = $cbcheck + $cb->checkamount;
            }
        }
        
        $bpi1 = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" .$transactiondate. "' and paymenttype = '1' and "
                . " depositto = 'BPI 1' and isreverse = '0'");
        
        if(count($bpi1)>0){
            foreach($bpi1 as $cb){
                $bpi1cash = $bpi1cash + $cb->amount;
                $bpi1check = $bpi1check + $cb->checkamount;
            }
        }
        
        $bpi2 = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                . " from dedits where postedby = '".\Auth::user()->idno . "' and "
                . " transactiondate = '" . $transactiondate. "' and paymenttype = '1' and "
                . " depositto = 'BPI 2' and isreverse = '0'");
        
        if(count($bpi2)>0){
            foreach($bpi2 as $cb){
                $bpi2cash = $bpi2cash + $cb->amount;
                $bpi2check = $bpi2check + $cb->checkamount;
            }
        }
        
        $totalissued = $cbcash + $cbcheck + $bpi1cash + $bpi1check + $bpi2cash + $bpi2check;
        
        $encashments = DB::Select("select sum(amount) as amount, withdrawfrom from encashments where postedby = '" . \Auth::user()->idno. "' "
                . " and transactiondate = '". $transactiondate."' and isreverse = '0' group by withdrawfrom");
        
        $encashcbc=0;
        $encashbpi1=0;
        $encashbpi2=0;
        
        foreach($encashments as $encash){
            if($encash->withdrawfrom == "China Bank"){
                $encashcbc = $encashcbc + $encash->amount;
            }
            if($encash->withdrawfrom == "BPI 1"){
                $encashbpi1 = $encashbpi1 + $encash->amount;
            }
            if($encash->withdrawfrom == "BPI 2"){
                $encashbpi2 = $encashbpi2 + $encash->amount;
            }
        }
        
        $totalissued = $cbcash + $cbcheck + $bpi1cash + $bpi1check + $bpi2cash + $bpi2check;
        $totalissued = $totalissued - $encashcbc - $encashbpi1 - $encashbpi2;
        
        
       $pdf = \App::make('dompdf.wrapper');
       //$pdf->setPaper([0, 0, 336, 440], 'portrait');
       $pdf->loadView("print.printactualcash",compact('chinabank','bpi1','bpi2',
       'chinabank1','encashments','transactiondate','cbcash','cbcheck','bpi1cash',
       'bpi1check','bpi2cash','bpi2check','encashcbc','encashbpi1','encashbpi2','actual','action','transactiondate','totalissued'));
       return $pdf->stream();
        
        
        
        
    }
    function actualdeposit($transactiondate){
          
            $debits = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount, "
                    . " depositto from dedits where paymenttype = '1' and isreverse = '0' and postedby = '"
                    . \Auth::user()->idno . "' and transactiondate = '$transactiondate' group by depositto");
            $encashments = DB::Select("select whattype, sum(amount) as amount from encashments where "
                    . "isreverse = '0' and postedby ='". \Auth::user()->idno ."' and transactiondate = '$transactiondate' "
                    . "group by whattype");
            
            $debitstotal= DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                    . "  from dedits where paymenttype = '1' and isreverse = '0' and postedby = '"
                    . \Auth::user()->idno . "' and transactiondate = '$transactiondate'");
            $totaldebit = 0;
            if(count($debitstotal)>0){
                foreach($debitstotal as $dt){
            $totaldebit = $totaldebit + $dt->amount + $dt->checkamount;
            }
            } 
            
            $deposit_slips = \App\DepositSlip::where('transactiondate', $transactiondate)
                    ->where('postedby',\Auth::user()->idno)->orderBy('bank')->get();
                    
            
            return view('cashier.actualdeposit',compact('deposit_slips','transactiondate','debits','encashments','totaldebit'));       
        
            
    }
    
    function printactualdeposit($transactiondate){
        
        $debits = DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount, "
                    . " depositto from dedits where paymenttype = '1' and isreverse = '0' and postedby = '"
                    . \Auth::user()->idno . "' and transactiondate = '$transactiondate' group by depositto");
            $encashments = DB::Select("select whattype, sum(amount) as amount from encashments where "
                    . "isreverse = '0' and postedby ='". \Auth::user()->idno ."' and transactiondate = '$transactiondate' "
                    . "group by whattype");
            
            $debitstotal= DB::Select("select sum(amount) as amount, sum(checkamount) as checkamount "
                    . "  from dedits where paymenttype = '1' and isreverse = '0' and postedby = '"
                    . \Auth::user()->idno . "' and transactiondate = '$transactiondate'");
            $totaldebit = 0;
            if(count($debitstotal)>0){
                foreach($debitstotal as $dt){
            $totaldebit = $totaldebit + $dt->amount + $dt->checkamount;
            }
            } 
            
            $deposit_slips = \App\DepositSlip::where('transactiondate', $transactiondate)
                    ->where('postedby',\Auth::user()->idno)->orderBy('bank')->get();
    
             $pdf = \App::make('dompdf.wrapper');
       $pdf->loadView("cashier.printactualdeposit",compact('deposit_slips','transactiondate','debits','encashments','totaldebit'));
       return $pdf->stream();
                }
    
    
    
    
    function cutoff($transactiondate){
        $batch = \App\Dedit::where('transactiondate',$transactiondate)->where('postedby',\Auth::user()->idno)->orderBy('batch','desc')->first();
        //return $batch->batch +1;
        
        $updatecreditbatch = \App\Credit::where('transactiondate',$transactiondate)->where('postedby',\Auth::user()->idno)
                ->where('batch','0')->get();
        foreach($updatecreditbatch as $updatecredit){
            $updatecredit->batch=$batch->batch+1;
            $updatecredit->update();
        }
        
        $updatedebitbatch = \App\Dedit::where('transactiondate',$transactiondate)->where('postedby',\Auth::user()->idno)
                ->where('batch','0')->get();
        
        foreach($updatedebitbatch as $updatedebit){
            $updatedebit->batch=$batch->batch+1;
            $updatedebit->update();
        }
            
        
        
        $newactual = new \App\ActualDeposit;
        $newactual->batch=$batch->batch+1;
        $newactual->transactiondate = $transactiondate;
        $newactual->postedby=\Auth::user()->idno;
        $newactual->save();
        
        return $this->actualdeposit($transactiondate);
        //return redirect(url('actualdeposit',$transactiondate));
        
    }
    function addtoaccount($studentid){
        $accounts = \App\CtrOtherPayment::orderBy('particular')->get();
        $studentdetails = \App\User::where('idno',$studentid)->first();
        $tatuses = \App\Status::where('idno',$studentid)->first();
        $ledgers = DB::Select("Select * from ledgers where idno='$studentid' AND categoryswitch = '7' and amount > payment ");
        return view('cashier.addtoaccount',compact('studentid','accounts','studentdetails','statuses','ledgers'));
        
    }
    
    function posttoaccount(Request $request){
        $idno = $request->studentid;
        $status = \App\Status::where('idno',$request->idno)->first();
        $newledger = new \App\Ledger;
        $acctcode = \App\CtrOtherPayment::where('particular',$request->accttype)->first();
        $myacct=$acctcode->accounttype;
        if(count($status)>0){
        $newledger->level=$status->level;
        $newledger->course=$status->course;
        $newledger->strand=$status->strand;
        $newledger->department = $status->department;
        $newledger->schoolyear=$status->schoolyear;
        $newledger->period=$status->period;
        }
        $newledger->idno = $request->idno;
        $newledger->categoryswitch = '7';
        $newledger->transactiondate = Carbon::now();       
        $newledger->acctcode=$myacct;
        $newledger->description=$request->accttype;
        $newledger->postedby=\Auth::user()->idno;
        $newledger->receipt_details=$request->accttype;
        $newledger->duetype="0";
        $newledger->duedate=Carbon::now();
        $newledger->amount=$request->amount;
        $newledger->save();
        return $this->addtoaccount($request->idno);
        //return redirect(url('addtoaccount',$request->idno));
    }
    function addtoaccountdelete($id){
        $account = \App\Ledger::where('id',$id)->first();
        if($account->payment+$account->debitmemo==0){
        $account->delete();    
        }
        return $this->addtoaccount($account->idno);
        //return redirect(url('addtoaccount',$account->idno));
    }
    
   }
