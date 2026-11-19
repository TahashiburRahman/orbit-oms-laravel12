@extends('layouts.app')
@section('title','Edit scheduled post')
@section('content')
<div class="page-header"><h1>Edit scheduled post</h1><a href="{{ route('campaigns.show',$post->campaign) }}">Back to campaign</a></div><form class="card form-card" method="post" action="{{ route('posts.update',$post) }}">@csrf @method('PUT') @include('marketing.campaigns.post-fields')<button class="btn">Save post</button></form>
@endsection
