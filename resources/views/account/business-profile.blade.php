@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('business_profile.store') }}" method="post">
        @csrf
        <h1>{{ __('ui.business_profile_details') }}</h1>
        @if($profile)
            <p>{{ __('ui.review_status') }}：{{ __('ui.status_'.$profile->status) }}</p>
        @endif
        <label>{{ __('ui.company_name') }}<input name="company_name" value="{{ old('company_name', $profile->company_name ?? '') }}" required></label>
        <label>{{ __('ui.tax_id') }}<input name="tax_id" value="{{ old('tax_id', $profile->tax_id ?? '') }}" required></label>
        <label>{{ __('ui.contact_name') }}<input name="contact_name" value="{{ old('contact_name', $profile->contact_name ?? '') }}" required></label>
        <label>{{ __('ui.contact_phone') }}<input name="contact_phone" value="{{ old('contact_phone', $profile->contact_phone ?? '') }}" required></label>
        <label>{{ __('ui.billing_email') }}<input name="billing_email" type="email" value="{{ old('billing_email', $profile->billing_email ?? '') }}"></label>
        <button type="submit">{{ __('ui.submit_for_review') }}</button>
    </form>
@endsection
