<!doctype html>
<html class="bg-white antialiased" @php language_attributes() @endphp>
@include('partials.head')
<body @php body_class() @endphp>
    @php do_action('get_header') @endphp
    <div class="site">
        <div id="app" role="document">
            @include('partials.navbar')
            <main>
                @yield('content')
            </main>
        </div>
        @php do_action('get_footer') @endphp
        @include('partials.footer')
    </div>
    @php wp_footer() @endphp
</body>
</html>
