(function () {
	var header = document.querySelector('.dl-site-header');
	var toggle = document.querySelector('.dl-menu-toggle');
	var menu = document.getElementById('dl-primary-menu');

	if (!header || !toggle || !menu) {
		return;
	}

	function setOpen(isOpen) {
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		menu.classList.toggle('is-open', isOpen);
		header.classList.toggle('is-menu-open', isOpen);
	}

	header.classList.add('dl-nav-ready');

	toggle.addEventListener('click', function () {
		setOpen(toggle.getAttribute('aria-expanded') !== 'true');
	});

	menu.addEventListener('click', function (event) {
		if (event.target.closest('a')) {
			setOpen(false);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			setOpen(false);
		}
	});

	window.addEventListener('resize', function () {
		if (window.matchMedia('(min-width: 721px)').matches) {
			setOpen(false);
		}
	});
}());
