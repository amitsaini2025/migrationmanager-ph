{{-- Populate Compose Email From dropdowns with SendGrid verified senders --}}
<script>
(function() {
	var sendersUrl = '{{ route("crm.sendgrid.senders") }}';
	var cachedData = null;
	var cachedError = false;
	var fetchPromise = null;

	function collectSelects() {
		return document.querySelectorAll('.email-from-sendgrid');
	}

	function isSelectFilled(select) {
		return select.getAttribute('data-sendgrid-from-loaded') === '1';
	}

	function applyToSelect(select) {
		if (!select || isSelectFilled(select)) {
			return;
		}

		var previous = (select.value || '').trim();

		if (cachedError) {
			select.innerHTML = '<option value="">SendGrid unavailable – check SENDGRID_API_KEY</option>';
			select.setAttribute('data-sendgrid-from-loaded', '1');
			return;
		}

		if (!cachedData) {
			return;
		}

		var senders = cachedData.senders || [];
		var defaultFrom = (cachedData.default_from || '').trim();
		select.innerHTML = '<option value="">Select From</option>';
		if (senders.length > 0) {
			senders.forEach(function(s) {
				var opt = document.createElement('option');
				opt.value = s.email || '';
				opt.textContent = (s.name && s.name !== s.email) ? (s.name + ' <' + s.email + '>') : (s.email || '');
				select.appendChild(opt);
			});
		} else if (defaultFrom) {
			var fallback = document.createElement('option');
			fallback.value = defaultFrom;
			fallback.textContent = defaultFrom;
			select.appendChild(fallback);
		} else {
			select.innerHTML = '<option value="">No SendGrid senders found</option>';
		}

		select.setAttribute('data-sendgrid-from-loaded', '1');

		if (previous) {
			var matched = Array.prototype.some.call(select.options, function(opt) {
				return (opt.value || '') === previous;
			});
			select.value = matched ? previous : '';
		} else {
			select.value = '';
		}
	}

	function applyToAll() {
		collectSelects().forEach(applyToSelect);
	}

	function refreshEmailFromSenders() {
		applyToAll();

		if (cachedData || cachedError) {
			return fetchPromise || Promise.resolve();
		}

		if (fetchPromise) {
			return fetchPromise;
		}

		fetchPromise = fetch(sendersUrl, {
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		})
			.then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function(data) {
				cachedData = data || { senders: [], default_from: '' };
				applyToAll();
			})
			.catch(function() {
				cachedError = true;
				applyToAll();
			});

		return fetchPromise;
	}

	window.refreshEmailFromSenders = refreshEmailFromSenders;

	refreshEmailFromSenders();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refreshEmailFromSenders);
	}

	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('shown.bs.modal', '.modal', function() {
			var select = this.querySelector('.email-from-sendgrid');
			if (!select) {
				return;
			}
			refreshEmailFromSenders();
			if (select.options.length > 0) {
				select.value = '';
			}
		});
	}
})();
</script>
