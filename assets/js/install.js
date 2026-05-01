// assets/js/install.js — Bootstrap-style validation for install form

document.addEventListener('DOMContentLoaded', () => {
	const forms = document.querySelectorAll('.needs-validation');

	forms.forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (!form.checkValidity()) {
				event.preventDefault();
				event.stopPropagation();
			}

			form.classList.add('was-validated');
		});
	});
});
