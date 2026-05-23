/**
 * Premium Admin Scripting for GitHub to WP Sync & Auto-Updater
 */
(function($) {
	'use strict';

	// State Management
	let repos = gh_wp_updater.repos || {};
	let logs = gh_wp_updater.logs || [];

	// Document Ready
	$(document).ready(function() {
		init();
	});

	/**
	 * Initialize Dashboard Events and Views
	 */
	function init() {
		// Render initial lists
		renderRepos();
		renderLogs();

		// Modal Controls
		$('#gh-wp-add-repo-btn').on('click', function() {
			openModal('add');
		});

		$('.gh-wp-modal-close, .gh-wp-modal-close-btn, .gh-wp-modal-overlay').on('click', closeModal);

		// Form Submission
		$('#gh-wp-repo-form').on('submit', handleFormSubmit);

		// Event Delegation for Cards (Sync, Edit, Delete, Copy)
		$('#gh-wp-repos-container')
			.on('click', '.btn-sync', handleManualSync)
			.on('click', '.btn-edit', handleEditRepo)
			.on('click', '.btn-delete', handleDeleteRepo)
			.on('click', '.gh-wp-btn-copy', handleCopyText)
			.on('click', '.toggle-secret-visibility', handleToggleSecret);

		// Clear Logs Event
		$('#gh-wp-clear-logs-btn').on('click', handleClearLogs);
	}

	/**
	 * Open Add/Edit Modal
	 */
	function openModal(mode, slug = '') {
		const $modal = $('#gh-wp-repo-modal');
		const $form = $('#gh-wp-repo-form')[0];
		
		$form.reset();
		$('#gh-wp-action-mode').val(mode);
		
		if (mode === 'add') {
			$('#gh-wp-modal-title').text('Add Repository');
			$('#gh-wp-slug').prop('readonly', false);
			$('#gh-wp-old-slug').val('');
			$('#gh-wp-token').attr('placeholder', 'Enter token (leave blank for public repos)');
		} else if (mode === 'edit' && repos[slug]) {
			const repo = repos[slug];
			$('#gh-wp-modal-title').text('Edit Repository');
			$('#gh-wp-old-slug').val(slug);
			
			$('#gh-wp-owner-repo').val(repo.owner_repo);
			$('#gh-wp-type').val(repo.type);
			$('#gh-wp-branch').val(repo.branch);
			$('#gh-wp-slug').val(repo.slug).prop('readonly', true); // Slug cannot be changed on edit to prevent path issues
			
			// Show dummy placeholder for password to represent that it is stored
			if (repo.token) {
				$('#gh-wp-token').attr('placeholder', '●●●●●●●● (Saved)');
			} else {
				$('#gh-wp-token').attr('placeholder', 'Enter token (leave blank for public repos)');
			}
		}

		$modal.addClass('open');
	}

	/**
	 * Close Modal
	 */
	function closeModal() {
		$('#gh-wp-repo-modal').removeClass('open');
	}

	/**
	 * Save/Edit Form Handler
	 */
	function handleFormSubmit(e) {
		e.preventDefault();

		const mode = $('#gh-wp-action-mode').val();
		const oldSlug = $('#gh-wp-old-slug').val();
		const ownerRepo = $('#gh-wp-owner-repo').val().trim();
		const type = $('#gh-wp-type').val();
		const branch = $('#gh-wp-branch').val().trim();
		const slug = $('#gh-wp-slug').val().trim();
		const token = $('#gh-wp-token').val();

		const $saveBtn = $('#gh-wp-save-btn');
		$saveBtn.prop('disabled', true).text('Saving...');

		$.ajax({
			url: gh_wp_updater.ajax_url,
			method: 'POST',
			data: {
				action: 'gh_wp_save_repo',
				security: gh_wp_updater.security,
				mode: mode,
				old_slug: oldSlug,
				owner_repo: ownerRepo,
				type: type,
				branch: branch,
				slug: slug,
				token: token
			},
			success: function(response) {
				$saveBtn.prop('disabled', false).text('Save Repository');
				if (response.success) {
					repos = response.data.repos;
					renderRepos();
					closeModal();
					showToast('Repository configuration saved successfully.', 'success');
				} else {
					showToast(response.data.message || 'Error occurred while saving.', 'error');
				}
			},
			error: function() {
				$saveBtn.prop('disabled', false).text('Save Repository');
				showToast('Failed to connect to the server.', 'error');
			}
		});
	}

	/**
	 * Render Repositories Cards
	 */
	function renderRepos() {
		const $container = $('#gh-wp-repos-container');
		$container.empty();

		const keys = Object.keys(repos);
		if (keys.length === 0) {
			$container.append(`
				<div class="gh-wp-empty-state">
					<span class="dashicons dashicons-cloud"></span>
					<h3>No Repositories Registered</h3>
					<p>Register your first public or private GitHub repository to start syncing files and setting up auto-updates.</p>
					<button type="button" class="gh-wp-btn gh-wp-btn-primary" id="gh-wp-empty-add-btn">
						<span class="dashicons dashicons-plus"></span> Register Repository
					</button>
				</div>
			`);
			
			$('#gh-wp-empty-add-btn').on('click', function() {
				openModal('add');
			});
			return;
		}

		keys.forEach(function(key) {
			const repo = repos[key];
			const webhookUrl = gh_wp_updater.webhook_base + repo.slug;
			const typeLabel = repo.type.charAt(0).toUpperCase() + repo.type.slice(1);
			const badgeClass = repo.type === 'theme' ? 'gh-wp-badge-theme' : 'gh-wp-badge-plugin';

			const cardHtml = `
				<div class="gh-wp-repo-card type-${repo.type}" data-slug="${repo.slug}">
					<div class="gh-wp-repo-card-top">
						<div class="gh-wp-repo-identity">
							<div class="gh-wp-repo-icon">
								<span class="dashicons dashicons-${repo.type === 'theme' ? 'admin-appearance' : 'admin-plugins'}"></span>
							</div>
							<div class="gh-wp-repo-details">
								<h4>${escapeHtml(repo.owner_repo)}</h4>
								<span class="repo-meta">${escapeHtml(repo.slug)} (${escapeHtml(repo.branch)})</span>
							</div>
						</div>
						<span class="gh-wp-badge ${badgeClass}">${typeLabel}</span>
					</div>

					<div class="gh-wp-repo-card-middle">
						<div class="gh-wp-info-row">
							<label>Webhook Endpoint URL</label>
							<div class="gh-wp-copy-field">
								<code>${escapeHtml(webhookUrl)}</code>
								<button type="button" class="gh-wp-btn-copy" data-copy-val="${escapeHtml(webhookUrl)}" title="Copy URL">
									<span class="dashicons dashicons-admin-page"></span>
								</button>
							</div>
						</div>
						
						<div class="gh-wp-info-row">
							<label>Webhook Secret Key</label>
							<div class="gh-wp-copy-field">
								<code class="secret-text" data-secret="${escapeHtml(repo.webhook_secret)}">••••••••••••••••••••••••</code>
								<div style="display:flex; gap: 4px;">
									<button type="button" class="gh-wp-btn-copy toggle-secret-visibility" title="Show/Hide Secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" class="gh-wp-btn-copy" data-copy-val="${escapeHtml(repo.webhook_secret)}" title="Copy Secret">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								</div>
							</div>
						</div>
					</div>

					<div class="gh-wp-repo-card-bottom">
						<div class="gh-wp-card-actions">
							<button type="button" class="gh-wp-btn-icon btn-sync" title="Synchronize Now">
								<span class="dashicons dashicons-update-alt"></span>
							</button>
							<button type="button" class="gh-wp-btn-icon btn-edit" title="Edit Configurations">
								<span class="dashicons dashicons-edit"></span>
							</button>
						</div>
						<button type="button" class="gh-wp-btn-icon btn-delete" title="Delete Configuration">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			`;
			$container.append(cardHtml);
		});
	}

	/**
	 * Toggle Webhook Secret Text Visibility
	 */
	function handleToggleSecret() {
		const $btn = $(this);
		const $icon = $btn.find('span');
		const $code = $btn.closest('.gh-wp-copy-field').find('.secret-text');
		const secret = $code.attr('data-secret');

		if ($icon.hasClass('dashicons-visibility')) {
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
			$code.text(secret);
		} else {
			$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			$code.text('••••••••••••••••••••••••');
		}
	}

	/**
	 * Render Timeline Sync Logs
	 */
	function renderLogs() {
		const $container = $('#gh-wp-logs-container');
		$container.empty();

		if (logs.length === 0) {
			$container.append(`
				<div class="gh-wp-logs-empty">
					No synchronization logs recorded yet. Webhook activities and manual deployments will appear here.
				</div>
			`);
			return;
		}

		logs.forEach(function(log) {
			const dateStr = new Date(log.timestamp * 1000).toLocaleString();
			const statusIcon = log.status === 'success' ? 'yes' : 'warning';
			const isError = log.status === 'failed';
			
			let errorHtml = '';
			if (isError && log.error) {
				errorHtml = `<div class="gh-wp-log-error-banner">${escapeHtml(log.error)}</div>`;
			}

			// Short Commit link representation
			let commitLink = `<span>SHA: <strong>${escapeHtml(log.commit_sha)}</strong></span>`;
			if (log.commit_long && log.commit_long !== 'manual' && log.commit_long !== 'unknown') {
				const githubUrl = `https://github.com/${log.repo_name}/commit/${log.commit_long}`;
				commitLink = `<a href="${githubUrl}" target="_blank" title="View Commit on GitHub" class="gh-wp-commit-link">
					<span class="dashicons dashicons-external"></span> ${escapeHtml(log.commit_sha)}
				</a>`;
			}

			const logHtml = `
				<div class="gh-wp-log-item status-${log.status}">
					<div class="gh-wp-log-status-dot">
						<span class="dashicons dashicons-${statusIcon}"></span>
					</div>
					<div class="gh-wp-log-info">
						<div class="gh-wp-log-header">
							<span class="gh-wp-log-repo">${escapeHtml(log.repo_slug)}</span>
							<span class="gh-wp-log-time">${dateStr}</span>
						</div>
						<div class="gh-wp-log-commit-msg">${escapeHtml(log.message)}</div>
						<div class="gh-wp-log-meta">
							<span>Author: <strong>${escapeHtml(log.author)}</strong></span>
							${commitLink}
						</div>
						${errorHtml}
					</div>
				</div>
			`;
			$container.append(logHtml);
		});
	}

	/**
	 * Handle Manual Sync Trigger
	 */
	function handleManualSync() {
		const $btn = $(this);
		const $card = $btn.closest('.gh-wp-repo-card');
		const slug = $card.attr('data-slug');

		$btn.addClass('syncing');
		showToast(`Beginning synchronization for ${slug}...`, 'success');

		$.ajax({
			url: gh_wp_updater.ajax_url,
			method: 'POST',
			data: {
				action: 'gh_wp_trigger_sync',
				security: gh_wp_updater.security,
				slug: slug
			},
			success: function(response) {
				$btn.removeClass('syncing');
				if (response.success) {
					logs = response.data.logs;
					renderLogs();
					showToast(`${slug} updated successfully!`, 'success');
				} else {
					// We refresh logs because a failed attempt still logs the event
					refreshLogsList();
					showToast(`Sync failed: ${response.data.message}`, 'error');
				}
			},
			error: function() {
				$btn.removeClass('syncing');
				showToast('Failed to connect to the server during synchronization.', 'error');
			}
		});
	}

	/**
	 * Retrieve and refresh the logs list dynamically
	 */
	function refreshLogsList() {
		$.ajax({
			url: gh_wp_updater.ajax_url,
			method: 'POST',
			data: {
				action: 'gh_wp_get_logs',
				security: gh_wp_updater.security
			},
			success: function(response) {
				if (response.success) {
					logs = response.data.logs;
					renderLogs();
				}
			}
		});
	}

	/**
	 * Edit Repo Configuration
	 */
	function handleEditRepo() {
		const slug = $(this).closest('.gh-wp-repo-card').attr('data-slug');
		openModal('edit', slug);
	}

	/**
	 * Delete Repo Configuration
	 */
	function handleDeleteRepo() {
		const slug = $(this).closest('.gh-wp-repo-card').attr('data-slug');

		if (!confirm(gh_wp_updater.strings.confirm_delete)) {
			return;
		}

		$.ajax({
			url: gh_wp_updater.ajax_url,
			method: 'POST',
			data: {
				action: 'gh_wp_delete_repo',
				security: gh_wp_updater.security,
				slug: slug
			},
			success: function(response) {
				if (response.success) {
					repos = response.data.repos;
					renderRepos();
					showToast('Repository deleted successfully.', 'success');
				} else {
					showToast(response.data.message || 'Failed to delete repository.', 'error');
				}
			},
			error: function() {
				showToast('Connection error occurred.', 'error');
			}
		});
	}

	/**
	 * Clear History
	 */
	function handleClearLogs() {
		if (logs.length === 0) {
			return;
		}

		if (!confirm(gh_wp_updater.strings.confirm_clear)) {
			return;
		}

		$.ajax({
			url: gh_wp_updater.ajax_url,
			method: 'POST',
			data: {
				action: 'gh_wp_clear_logs',
				security: gh_wp_updater.security
			},
			success: function(response) {
				if (response.success) {
					logs = [];
					renderLogs();
					showToast('History logs cleared.', 'success');
				}
			}
		});
	}

	/**
	 * Copy Text to Clipboard Helper
	 */
	function handleCopyText() {
		const $btn = $(this);
		const val = $btn.attr('data-copy-val');

		if (!val) {
			return;
		}

		navigator.clipboard.writeText(val).then(function() {
			$btn.addClass('copied');
			const $icon = $btn.find('span');
			$icon.removeClass('dashicons-admin-page').addClass('dashicons-yes');
			
			showToast('Copied to clipboard!', 'success');

			setTimeout(function() {
				$btn.removeClass('copied');
				$icon.removeClass('dashicons-yes').addClass('dashicons-admin-page');
			}, 2000);
		}).catch(function() {
			showToast('Failed to copy to clipboard.', 'error');
		});
	}

	/**
	 * Display custom sliding Toast notifications
	 */
	function showToast(message, type = 'success') {
		// Clean existing toasts
		$('.gh-wp-toast').remove();

		const $toast = $(`
			<div class="gh-wp-toast toast-${type}">
				<span class="dashicons dashicons-${type === 'success' ? 'yes' : 'warning'}"></span>
				<span>${escapeHtml(message)}</span>
			</div>
		`);

		$('body').append($toast);

		// Trigger show transition
		setTimeout(function() {
			$toast.addClass('show');
		}, 100);

		// Automatically remove after 4 seconds
		setTimeout(function() {
			$toast.removeClass('show');
			setTimeout(function() {
				$toast.remove();
			}, 300);
		}, 4000);
	}

	/**
	 * Helper function to escape HTML special characters
	 */
	function escapeHtml(str) {
		if (!str) return '';
		return str
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}

})(jQuery);
