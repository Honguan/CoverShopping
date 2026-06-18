@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('business_profile.store') }}" method="post">
        @csrf
        <h1>企業會員資料</h1>
        @if($profile)
            <p>審核狀態：{{ $profile->status }}</p>
        @endif
        <label>公司名稱<input name="company_name" value="{{ old('company_name', $profile->company_name ?? '') }}" required></label>
        <label>統一編號<input name="tax_id" value="{{ old('tax_id', $profile->tax_id ?? '') }}" required></label>
        <label>聯絡人<input name="contact_name" value="{{ old('contact_name', $profile->contact_name ?? '') }}" required></label>
        <label>聯絡電話<input name="contact_phone" value="{{ old('contact_phone', $profile->contact_phone ?? '') }}" required></label>
        <label>帳務 Email<input name="billing_email" type="email" value="{{ old('billing_email', $profile->billing_email ?? '') }}"></label>
        <button type="submit">送出審核</button>
    </form>
@endsection
