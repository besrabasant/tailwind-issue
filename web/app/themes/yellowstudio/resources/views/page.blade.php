@extends('layouts.app')

@section('content')
<section class="section">
	<div class="container">
		@while(have_posts()) @php the_post() @endphp
		@include('partials.page-header')
		@include('partials.content-page')
		@endwhile
	</div>
</section>
@endsection
