<nav role="navigation" class="navbar" aria-label="main navigation">
	<div class="container flex">
		<div class="flex-1">
			<a class="brand" href="{{ home_url('/') }}">
				{{ get_bloginfo('name', 'display') }}
			</a>
		</div>
		<div class="flex-1 flex items-center justify-end">
			@if (has_nav_menu('primary_navigation'))
			{!! wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'sage-menu']) !!}
			@endif
		</div>
	</div>
</nav>
