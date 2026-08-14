@extends('layouts.user')
@section('title', 'Galeri Misi')
@section('content')
    @include('partials.gallery-grid', ['fotos' => $fotos])
@endsection
