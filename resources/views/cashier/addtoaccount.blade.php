@extends('appcashier')
@section('content')

<div class="container">
    
    
    <div class="col-md-6">
        <table class="table table-striped">
            <tr><td>Student No</td><td>{{$studentid}}</td></tr>
             <tr><td>Name</td><td>{{$studentdetails->lastname}}, {{$studentdetails->firstname}}</td></tr>
        </table>    
    <form method="POST" action="{{url('addtoaccount')}}">
        {!! csrf_field() !!} 
        <div class="form-group">
            <label>Account name</label>
            <input type="hidden" name='idno' value="{{$studentid}}">
            <select name="accttype" class="form form-control" onkeypress="document.getElementById('amount').focus()">
                @foreach($accounts as $account)
                <option = "{{$account->particular}}">{{$account->particular}}</option>
                @endforeach
            </select>    
        </div>
        <div class="form-group">
            <label>Amount</label>
            <input type="text" class="form form-control" id="amount" name="amount" onkeypress ="validate(event)" style="text-align: right">
        </div>    
         <div class="form-group">
            <input type="submit" class="form form-control btn btn-primary" name="submit"  id="submit" value="Add to account">
        </div> 
    </form>    
        <div class="form-group">
            <a href="{{url('cashier',$studentid)}}" class="btn btn-primary">Back To ledger</a>
        </div>    
        </div>
        <div class="col-md-6">
            <h5>Balance for Other Collections</h5>
            @if(count($ledgers)>0)
            <table class="table table-striped">
                <tr><td>Description</td><td>Amount</td><td></td></tr>
                @foreach($ledgers as $ledger)
                <tr><td>{{$ledger->receipt_details}}</td><td align="right">{{number_format($ledger->amount,2)}}</td><td><a href="{{url('addtoaccountdelete',$ledger->id)}}" >Delete</a></td></tr>
                @endforeach
            </table>    
            @endif
         </div>      
</div>
@stop

<script>
function validate(evt) {
  var theEvent = evt || window.event;
  var key = theEvent.keyCode || theEvent.which;
        if ((key < 48 || key > 57) && !(key == 8 || key == 9 || key == 13 || key == 37 || key == 39 || key == 46) ){ 
            theEvent.returnValue = false;
                if (theEvent.preventDefault) theEvent.preventDefault();
        }
        
        if(key == 13){
            document.getElementById("submit").focus()            
            theEvent.preventDefault();
            return false;
            
        }
    }  
</script>  