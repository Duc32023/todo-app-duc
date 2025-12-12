@extends('layouts.app')

@section('content')
<div
  id="react-task-list"
  data-tasks='@json($tasks)'>
</div>
@endsection
