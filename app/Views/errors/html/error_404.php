<!doctype html>
<html lang="en" data-look="dusk">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title>404 &middot; Page not found</title>

	<link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Inter:wght@400;450;500&display=swap">

	<link rel="stylesheet" href="/assets/css/tokens.css">
	<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

	<main class="error-page">
		<div class="error-page__code">404</div>
		<h1>Nothing here</h1>
		<p class="page-head__sub">
			<?php if (! empty($message) && $message !== '(null)') : ?>
				<?= nl2br(esc($message)) ?>
			<?php else : ?>
				That page is not part of the panel.
			<?php endif ?>
		</p>
		<div class="row gap-2 mt-5">
			<a class="btn btn--primary" href="/dashboard" data-press>Go to dashboard</a>
			<a class="btn btn--ghost" href="/keys" data-press>Keys</a>
		</div>
	</main>

</body>
</html>
